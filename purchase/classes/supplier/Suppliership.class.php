<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace purchase\supplier;

use finance\accounting\Account;
use fmt\setting\Setting;
use purchase\supplier\Supplier;

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
                // 'required'          => true
                'dependents'        => ['code']
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

            'supplier_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'description'       => "Supplier the contract relates to."
            ]

        ];
    }

    public static function getActions() {
        return [
            'generate_accounts' => [
                'description'   => 'Generate mandatory accounting Accounts for Suppliership.',
                'policies'      => [],
                'function'      => 'doGenerateAccounts'
            ]
        ];
    }


    public static function calcName($self) {
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
}
