<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class BalanceLine extends Model {

    public static function getName() {
        return "Balance line";
    }

    public static function getDescription() {
        return "A balance line provides the state of a specific account at the date of the balance.
        This class in an abstraction for the CurrentBalanceLine and ClosingBalanceLine entities.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry line refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['account_id' => 'name'],
                'store'             => true,
                'description'       => 'Label for identifying the line.',
            ],

            'balance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Balance',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'description'       => "The fiscal year the line refers to (from balance).",
                'foreign_object'    => 'finance\accounting\FiscalYear',
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the balance line relates to.",
                'required'          => true
            ],

            'debit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'The total amount recorded on the debit side of the account during the fiscal period.',
                'default'           => 0.0
            ],

            'credit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'The total amount recorded on the credit side of the account during the fiscal period.',
                'default'           => 0.0
            ],

            'debit_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'The remaining balance on the account if the total debits exceed the total credits.',
                'default'           => 0.0
            ],

            'credit_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'The remaining balance on the account if the total credits exceed the total debits.',
                'default'           => 0.0
            ],

            'entry_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntryLine',
                'description'       => "Accounting entry lines impacting the account.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['fiscal_year_id', '=', 'object.fiscal_year_id'], ['account_id', '=', 'object.account_id']]
            ]

        ];
    }

    public function getUnique() {
        return [
            ['balance_id', 'account_id']
        ];
    }

}