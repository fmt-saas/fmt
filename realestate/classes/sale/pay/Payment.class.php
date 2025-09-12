<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\sale\pay;

class Payment extends \sale\pay\Payment {

    public static function getColumns() {
        return [
            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'description'       => 'The funding the payment relates to, if any.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'order'             => 'issue_date',
                'sort'              => 'asc'
            ],

            'receipt_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'The Bank account the payment relates to.',
                'help'              => 'This is the bank account to which payment was actually received or sent, and might differ from the Funding banK-account_id.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

        ];
    }

    public static function getWorkflow() {
        return array_merge(parent::getWorkflow(), [
            'proforma' => [
                'description' => 'Payment being created.',
                'help'        => 'Status change is triggered by the parent BankStatementLine, which also generates the subsequent accounting entries.',
                'icon'        => 'draw',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the payment status to `payment`.',
                        'status'      => 'posted'
                    ]
                ]
            ]
        ]);
    }


}
