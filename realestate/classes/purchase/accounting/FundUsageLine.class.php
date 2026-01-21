<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\purchase\accounting;

use finance\accounting\Account;
use realestate\finance\accounting\AccountingEntryLine;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\PropertyLot;

class FundUsageLine extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'readonly'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short optional description of the fund usage line.',
                'onupdate'          => 'onupdateDescription'
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Invoice the line is related to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'fund_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account of the fund to use.",
                'required'          => true,
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_control_account', '=', false], ['operation_assignment', 'in', ['reserve_fund', 'special_reserve_fund']]],
                'dependents'        => ['expense_account_id', 'apportionment_id']
            ],

            'expense_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'function'          => 'calcExpenseAccountId',
                'store'             => true,
                'domain'            => [['condo_id', '=', 'object.condo_id']],
            ],

            'apportionment_id' => [
                'type'              => 'computed',
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'relation'          => ['fund_account_id' => ['apportionment_id']],
                'store'             => true,
                'readonly'          => true,
                'domain'            => [['condo_id', '=', 'object.condo_id']]
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the line.',
                'required'          => true
            ]

        ];
    }

    private static function computeExpenseAccountId($fund_account_id) {
        $result = null;
        $fundAccount = Account::id($fund_account_id)
            ->read(['id', 'code', 'condo_id'])
            ->first();

        if($fundAccount) {
            $expense_account_code = '68' . $fundAccount['code'] . '1';
            $expenseAccount = Account::search([['condo_id', '=', $fundAccount['condo_id']], ['code', '=', $expense_account_code]])->first();
            if($expenseAccount) {
                $result = $expenseAccount['id'];
            }
        }

        return $result;
    }

    public static function calcExpenseAccountId($self) {
        $result = [];
        $self->read(['condo_id', 'fund_account_id']);
        foreach($self as $id => $fundUsageLine) {
            if($fundUsageLine['fund_account_id']) {
                $expense_account_id = self::computeExpenseAccountId($fundUsageLine['fund_account_id']);
                if($expense_account_id) {
                    $result[$id] = $expense_account_id;
                }
            }
        }
        return $result;
    }

    protected static function onupdateDescription($self) {
        $self->read(['description']);
        foreach($self as $id => $fundUsageLine) {
            AccountingEntryLine::search([['fund_usage_line_id', '=', $id]])
                ->update(['description' => $fundUsageLine['description']]);
        }
    }

    public static function onchange($event, $values) {
        $result = [];
        if(array_key_exists('fund_account_id', $event)) {
            $expense_account_id = self::computeExpenseAccountId($event['fund_account_id']);
            if($expense_account_id) {
                $expenseAccount = Account::id($expense_account_id)->read(['id', 'name', 'apportionment_id'])->first();
                $result['expense_account_id'] = [
                        'id'    => $expenseAccount['id'],
                        'name'  => $expenseAccount['name']
                    ];
            }
            $fundAccount = Account::id($event['fund_account_id'])->read(['id', 'name', 'apportionment_id'])->first();
            if($fundAccount && isset($fundAccount['apportionment_id'])) {
                $apportionment = Apportionment::id($fundAccount['apportionment_id'])->read(['id', 'name'])->first();
                $result['apportionment_id'] = [
                        'id'    => $apportionment['id'],
                        'name'  => $apportionment['name']
                    ];
            }

        }
        return $result;
    }
}
