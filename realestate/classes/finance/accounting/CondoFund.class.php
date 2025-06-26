<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\finance\accounting;

use finance\accounting\Account;
use realestate\property\Apportionment;

class CondoFund extends \equal\orm\Model {

    public static function getName() {
        return 'Condominium Fund';
    }

    public static function getDescription() {
        return "A Condominium Fund is used a as a link between a fund account, an utilization account and an apportionment key.";
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
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['fund_account_id' => 'name'],
                'description'       => "Short description of the request, based on fiscal year and period.",
                'instant'           => true,
                'store'             => true
            ],

            'fund_type' => [
                'type'              => 'string',
                'selection'         => [
                    'working_fund',             // working capital
                    'reserve_fund',             // reserve fund for general expense as agreed in GA
                    'special_reserve_fund'      // special reserve fund for specific planned work
                ],
                'description'       => "Type of fund the entry relates to.",
                'help'              => "There might be as many funds as necessary, for each type of fund."
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the request, based on fiscal year and period.",
            ],

            'fund_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the fund relates to.",
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['operation_assignment', 'in', ['working_fund', 'reserve_fund', 'special_reserve_fund']]],
                'dependents'        => ['name']
            ],

            'expense_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account for fund utilization.",
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id']],
                'dependents'        => ['account_code']
            ],

            'expense_account_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['expense_account_id' => 'code'],
                'description'       => "Code of the expense account associated to the Reserve Fund.",
                'store'             => true,
                'instant'           => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Apportionment',
                'description'       => "Default apportionment to use when creating accounting entries on this account.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['status', '=', 'validated']]
            ],

            'total_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'relation'          => ['apportionment_id' => 'total_shares'],
                'description'       => "Total shares of the apportionment.",
            ]

        ];
    }

    protected static function computeExpenseAccountId($fund_account_id) {
        $result = null;
        $fundAccount = Account::id($fund_account_id)
            ->read(['id', 'code', 'condo_id'])
            ->first();

        if($fundAccount) {
            // #todo #accounting - we should not hard coding this
            $expense_account_code = '68' . $fundAccount['code'] . '1';
            $expenseAccount = Account::search([['condo_id', '=', $fundAccount['condo_id']], ['code', '=', $expense_account_code]])->first();
            if($expenseAccount) {
                $result = $expenseAccount['id'];
            }
        }

        return $result;
    }

    protected static function calcExpenseAccountId($self) {
        $result = [];
        $self->read(['fund_account_id']);
        foreach($self as $id => $reserveFund) {
            if($reserveFund['fund_account_id']) {
                $result[$id] = static::computeExpenseAccountId($reserveFund['fund_account_id']);
            }
        }
        return $result;
    }

    protected static function onchange($event, $values) {
        $result = [];
        if(array_key_exists('fund_account_id', $event)) {
            $expense_account_id = static::computeExpenseAccountId($event['fund_account_id']);
            if($expense_account_id) {
                $expenseAccount = Account::id($expense_account_id)->read(['id', 'name', 'apportionment_id'])->first();

                $result['expense_account_id'] = [
                        'id'    => $expenseAccount['id'],
                        'name'  => $expenseAccount['name']
                    ];

                if($expenseAccount['apportionment_id']) {
                    $apportionment = Apportionment::id($expenseAccount['apportionment_id'])->read(['id', 'name'])->first();
                    $result['apportionment_id'] = [
                            'id'    => $apportionment['id'],
                            'name'  => $apportionment['name']
                        ];
                }
            }
        }
        return $result;
    }

}
