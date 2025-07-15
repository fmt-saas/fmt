<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\finance\budget;

use finance\accounting\Account;

class CondoBudgetEntry extends \equal\orm\Model {

    public static function getName() {
        return 'Budget Entry';
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Short description of the budget entry.',
                'required'          => true
            ],

            'condo_budget_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\budget\CondoBudget',
                'description'       => 'Fiscal year in which the budget is planned.',
                'required'          => true,
                'dependents'        => ['fiscal_year_id']
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => 'Budget the entry relates to.',
                'relation'          => ['condo_budget_id' => 'fiscal_year_id'],
                'store'             => true
            ],

            'entry_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to (optional).",
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['code', 'like', '6%'], ['is_control_account', '=', true]]
            ],

            'entry_type' => [
                'type'              => 'string',
                'selection'         => [
                    'current_expenses',
                    'exceptional_expenses',
                    'condo_funds'
                ],
                'description'       => "Type of budget entry.",
                'default'           => 'current_expenses'
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Expected amount of expense for the budget entry.',
                'required'          => true
            ]

        ];
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['entry_account_id'])) {
            $account = Account::id($event['entry_account_id'])
                ->read(['description'])
                ->first();

            if($account) {
                $result['name'] = $account['description'];
            }
        }
        if(isset($event['entry_type'])) {
            if($event['entry_type'] === 'current_expense') {
                $result['entry_account_id']['domain'] = [
                        ['condo_id', '=', 'object.condo_id'], ['code', 'like', '6%'], ['is_control_account', '=', true]
                    ];
            }
            elseif($event['entry_type'] === 'exceptional_expenses') {
                $result['entry_account_id']['domain'] = [
                        [
                            ['condo_id', '=', 'object.condo_id'], ['code', 'like', '613%'], ['is_control_account', '=', false]
                        ],
                        [
                            ['condo_id', '=', 'object.condo_id'], ['code', 'like', '67%'], ['is_control_account', '=', true]
                        ]
                    ];
            }
            elseif($event['entry_type'] === 'condo_funds') {
                $result['entry_account_id']['domain'] = [
                        ['condo_id', '=', 'object.condo_id'], ['code', 'like', '160%'], ['is_control_account', '=', false]
                    ];
            }
        }
        return $result;
    }


}
