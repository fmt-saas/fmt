<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace purchase\supplier;

use finance\accounting\Account;
use finance\bank\SuppliershipBankAccount;
use fmt\setting\Setting;

class Suppliership extends \equal\orm\Model {

    public static function getName() {
        return 'Supplier';
    }

    public static function getDescription() {
        return 'A suppliership describes a relation between a supplier and a condominium.';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'dependents'        => ['code', 'name', 'suppliership_account_id']
            ],

            'supplier_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'description'       => "Supplier the Suppliership relates to.",
                'required'          => true,
                'dependents'        => ['name', 'code', 'suppliership_account_id'],
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The condominium the property lot belongs to.",
                'function'          => 'calcName',
                'store'             => true
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcSuppliershipCode',
                'store'             => true,
                'description'       => "Code of the supplier for the Condominium.",
                'help'              => "Code is assigned automatically and cannot be changed, and is intended to internal use.",
                'readonly'          => true
            ],

            'suppliership_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'function'          => 'calcSuppliershipAccountId',
                'store'             => true
            ],

            'suppliership_contracts_ids' => [
                'type'              => 'one2many',
                'description'       => "The contracts of the condominium for the supplier.",
                'foreign_object'    => 'purchase\supplier\SuppliershipContract',
                'foreign_field'     => 'suppliership_id',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'suppliership_references_ids' => [
                'type'              => 'one2many',
                'description'       => "The references used by the supplier for targeting the condominium.",
                'foreign_object'    => 'purchase\supplier\SuppliershipReference',
                'foreign_field'     => 'suppliership_id',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'suppliership_bank_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\SuppliershipBankAccount',
                'foreign_field'     => 'suppliership_id',
                'description'       => "The bank accounts of the supplier.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the Ownership.',
                'selection'         => [
                    'pending',
                    'validated'
                ],
                'default'           => 'pending'
            ]

        ];
    }

    public function getUnique() {
        return [
            ['condo_id', 'supplier_id']
        ];
    }


    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Ownership being completed, waiting to be validated.',
                'icon'        => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the Ownership to `validated`.',
                        'policies'    => ['is_valid'],
                        'onafter'     => 'onafterValidate',
                        'status'      => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Validated Ownership, ready to be used.',
                'icon'        => 'done',
                'transitions' => [
                    'revert' => [
                        'description' => 'Revert to `pending` to allow changes.',
                        'policies'    => [/* #todo */],
                        'status'      => 'pending'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return [
            'generate_accounts' => [
                'description'   => 'Generate mandatory accounting Accounts for Suppliership.',
                'policies'      => [],
                'function'      => 'doGenerateAccounts'
            ],
            'import_bank_account' => [
                'description'   => 'Import the primary bank account of the Supplier as a first Suppliership Bank Account.',
                'policies'      => [],
                'function'      => 'doImportBankAccount'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the mandatory values are present for Condominium validation.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    protected static function policyIsValid($self) {
        $result = [];

        $self->read(['condo_id', 'supplier_id']);
        foreach($self as $id => $suppliership) {

            if(!$suppliership['condo_id']) {
                $result[$id] = [
                    'missing_cond_id' => 'The condominium must be provided.'
                ];
            }

            if(!$suppliership['supplier_id']) {
                $result[$id] = [
                    'missing_supplier_id' => "The supplier must be provided [{$id}]."
                ];
            }
        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['state', 'code', 'supplier_id' => ['name']]);
        foreach($self as $id => $suppliership) {
            if($suppliership['state'] != 'instance') {
                continue;
            }
            if($suppliership['supplier_id'] && strlen($suppliership['supplier_id']['name'])) {
                $result[$id] = sprintf("%s - %s", $suppliership['code'], $suppliership['supplier_id']['name']);
            }
        }
        return $result;
    }

    /**
     * Retrieve the accounting account dedicated to owner (working fund)
     */
    protected static function calcSuppliershipAccountId($self) {
        $result = [];
        $self->read(['condo_id', 'code']);
        foreach($self as $id => $suppliership) {

            // find the suppliers account based on operation_assignment
            $account = Account::search([
                    ['condo_id', '=', $suppliership['condo_id']],
                    ['operation_assignment', '=', 'suppliers']
                ])
                ->read(['code'])
                ->first();

            if(!$account) {
                trigger_error("APP::unable to find a match for assignment `suppliers` for suppliership {$suppliership['condo_id']}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_supplier_assignment_account", EQ_ERROR_INVALID_CONFIG);
            }

            if($account) {
                $supplierAccount = Account::search([
                        ['condo_id', '=', $suppliership['condo_id']],
                        ['code', '=', $account['code'] . $suppliership['code']]
                    ])
                    ->first();
                $result[$id] = $supplierAccount['id'] ?? null;
            }
        }
        return $result;
    }

    /**
     * #memo - realestate.main.suppliership.sequence is initialized at Condominium creation (doGenerateSequences)
     */
    public static function calcSuppliershipCode($self) {
        $result = [];
        $self->read(['state', 'condo_id']);
        foreach($self as $id => $suppliership) {
            if($suppliership['state'] != 'instance') {
                continue;
            }

            $sequence = Setting::fetch_and_add(
                    'realestate',
                    'organization',
                    "suppliership.sequence",
                    1,
                    [
                        'condo_id' => $suppliership['condo_id']
                    ]
                );

            if($sequence) {
                $result[$id] = sprintf("%05d", $sequence);
            }
        }
        return $result;
    }

    public static function doImportBankAccount($self) {
        $self->read(['condo_id', 'supplier_id' => ['identity_id' => ['bank_accounts_ids' => ['@domain' => ['is_primary', '=', true]]]]]);
        foreach($self as $id => $suppliership) {
            if(!$suppliership['supplier_id']) {
                continue;
            }
            if(!isset($suppliership['supplier_id']['identity_id']['bank_accounts_ids']) || count($suppliership['supplier_id']['identity_id']['bank_accounts_ids']) <= 0) {
                continue;
            }
            $bank_account_id = current($suppliership['supplier_id']['identity_id']['bank_accounts_ids']);
            SuppliershipBankAccount::create([
                    'condo_id'          => $suppliership['condo_id'],
                    'suppliership_id'   => $id,
                    'bank_account_id'   => $bank_account_id
                ]);
        }
    }

    /**
     * Upon creation of a suppliership, it is necessary to create accounts for:
     * - 440xxxxx:        suppliers
     */
    public static function doGenerateAccounts($self) {
        $self->read(['condo_id', 'name', 'code']);
        foreach($self as $id => $suppliership) {
            if(!$suppliership['condo_id']) {
                continue;
            }
            $operation_assignments = [
                    'suppliers',
                ];

            foreach($operation_assignments as $operation_assignment) {
                // find the account based on operation_assignment to use it as "template"
                $assignmentAccount = Account::search([
                        ['condo_id', '=', $suppliership['condo_id']],
                        ['operation_assignment', '=', $operation_assignment]
                    ])
                    ->read(['code', 'account_category', 'account_chart_id'])
                    ->first();

                if(!$assignmentAccount) {
                    throw new \Exception("missing_mandatory_account", EQ_ERROR_INVALID_CONFIG);
                }

                $account_exists = (bool) count(Account::search([['condo_id', '=', $suppliership['condo_id']], ['code', '=', $assignmentAccount['code'] . $suppliership['code']]])->ids());

                if(!$account_exists) {
                    Account::create([
                            'code'                  => $assignmentAccount['code'] . $suppliership['code'],
                            'condo_id'              => $suppliership['condo_id'],
                            'parent_account_id'     => $assignmentAccount['id'],
                            'account_chart_id'      => $assignmentAccount['account_chart_id'],
                            'account_category'      => $assignmentAccount['account_category'],
                            'description'           => $suppliership['name'],
                            // make sure the account will not be used as template
                            'operation_assignment'  => ''
                        ])
                        ->read(['name']);
                }

            }

        }
    }

    protected static function onafterValidate($self) {
        $self
            ->do('generate_accounts')
            ->do('import_bank_account');
    }
}
