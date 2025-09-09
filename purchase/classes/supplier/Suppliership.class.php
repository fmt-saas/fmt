<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace purchase\supplier;

use finance\accounting\Account;
use finance\bank\SuppliershipBankAccount;
use fmt\setting\Setting;
use realestate\purchase\accounting\invoice\PurchaseInvoice;

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
                'dependents'        => ['code', 'name']
            ],

            'supplier_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'description'       => "Supplier the Suppliership relates to.",
                'required'          => true,
                'dependents'        => ['name', 'code'],
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
                'help'              => "Code is assigned automatically, cannot be changed, and is intended to internal use.",
                'readonly'          => true
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

            // #memo - a Suppliership might be linked to several Accounts of the Accounting Chart
            // 'accounting_account_id' => [

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
     * #memo - realestate.main.suppliership.sequence is initialized at Condominium creation (doGenerateSequences)
     */
    public static function calcSuppliershipCode($self) {
        $result = [];
        $self->read(['state', 'condo_id', 'supplier_id']);
        foreach($self as $id => $suppliership) {
            if($suppliership['state'] != 'instance') {
                continue;
            }

            /*
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
            */
            $result[$id] = sprintf("%05d", $suppliership['supplier_id']);
        }
        return $result;
    }

    public static function doImportBankAccount($self) {
        $self->read(['condo_id', 'supplier_id' => ['identity_id' => ['bank_accounts_ids' /*=> ['@domain' => ['is_primary', '=', true]] */]]]);
        foreach($self as $id => $suppliership) {
            if(!$suppliership['supplier_id']) {
                continue;
            }
            if(!isset($suppliership['supplier_id']['identity_id']['bank_accounts_ids']) || count($suppliership['supplier_id']['identity_id']['bank_accounts_ids']) <= 0) {
                continue;
            }
            foreach($suppliership['supplier_id']['identity_id']['bank_accounts_ids'] as $bank_account_id) {
                SuppliershipBankAccount::create([
                        'condo_id'          => $suppliership['condo_id'],
                        'suppliership_id'   => $id,
                        'bank_account_id'   => $bank_account_id
                    ]);
            }
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
                        ['operation_assignment', '=', $operation_assignment],
                        ['suppliership_id', '=', null]
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
                            'operation_assignment'  => $operation_assignment,
                            'suppliership_id'       => $id
                        ])
                        ->read(['name'])
                        ->first();
                }

            }

        }
    }

    protected static function onafterValidate($self) {
        $self
            ->do('generate_accounts')
            ->do('import_bank_account');
    }

    public static function candelete($self) {
        foreach($self as $id => $suppliership) {
            $count_invoices = PurchaseInvoice::search(['suppliership_id', '=', $id])->count();
            if($count_invoices) {
                return ['id' => ['non_removable' => 'Supplier referenced in Accounting cannot be removed.']];
            }
        }
        return parent::candelete($self);
    }

}
