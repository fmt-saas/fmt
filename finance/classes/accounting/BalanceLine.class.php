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
                'default'           => 0.0,
                'dependents'        => ['debit_balance']
            ],

            'credit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'The total amount recorded on the credit side of the account during the fiscal period.',
                'default'           => 0.0,
                'dependents'        => ['credit_balance']
            ],

            'debit_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcDebitBalance',
                'description'       => 'The remaining balance on the account if the total debits exceed the total credits.',
                'store'             => true
            ],

            'credit_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcCreditBalance',
                'description'       => 'The remaining balance on the account if the total credits exceed the total debits.',
                'store'             => true
            ]

        ];
    }

    public function getUnique() {
        return [
            ['balance_id', 'account_id']
        ];
    }

    public static function calcCreditBalance($self) {
        $result = [];
        $self->read(['debit', 'credit']);
        foreach($self as $id => $balance) {
            $delta = round($balance['debit'] - $balance['credit'], 4);
            $result[$id] = ($delta < 0.0) ? abs($delta) : 0.0;
        }
        return $result;
    }

    public static function calcDebitBalance($self) {
        $result = [];
        $self->read(['debit', 'credit']);
        foreach($self as $id => $balance) {
            $delta = round($balance['debit'] - $balance['credit'], 4);
            $result[$id] = ($delta > 0.0) ? abs($delta) : 0.0;
        }
        return $result;
    }
}