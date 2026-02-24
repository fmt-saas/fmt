<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class AccountBalanceChange extends Model {

    public static function getName() {
        return "Account Balance Change";
    }

    public static function getDescription() {
        return "AccountBalanceChange lines represent the cumulative balance of an account at each date where at least one accounting transaction has been recorded.
            Each line reflects the total debit and credit amounts of the account after all transactions of that date have been applied. Rather than storing a balance for every calendar day, a line is created only when the account balance effectively changes.
            These records act as an incremental time-series of cumulative balances. They allow the system to retrieve the balance of an account at any arbitrary date by simply selecting the most recent balance entry prior to (or equal to) that date, without recomputing all underlying accounting entries.
            The logic is independent of fiscal years or accounting periods. It is purely driven by transactions recorded on the account and is updated in real time whenever transactions are validated or cancelled.
            This mechanism ensures both performance and consistency, while always reflecting the exact financial position of an account at a given point in time.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry line refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the balance line relates to.",
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date at which the cumulative balance is valid, after applying all transactions recorded on that day.',
                'required'          => true
            ],

            'debit_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total cumulative debit amount of the account up to and including this date.',
                'default'           => 0.0,
                'required'          => true
            ],

            'credit_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total cumulative credit amount of the account up to and including this date.',
                'default'           => 0.0,
                'required'          => true
            ]

        ];
    }

    public function getUnique() {
        return [
            ['account_id', 'condo_id', 'date']
        ];
    }

}
