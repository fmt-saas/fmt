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

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the balance line relates to.",
                'required'          => true,
                'ondelete'          => 'null'
            ],

            'debit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be debited on the account.',
                'default'           => 0.0
            ],

            'credit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be credited on the account.',
                'default'           => 0.0
            ]
        ];
    }

}