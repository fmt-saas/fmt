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
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the payment relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

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
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'The Bank account the payment relates to.',
                'help'              => 'This is the bank account on which movement was actually performed (received or sent), and might differ from the Funding banK-account_id.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Payment being created.',
                'help'        => 'Status change is triggered by the parent BankStatementLine, which also generates the subsequent accounting entries.',
                'icon'        => 'draw',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the payment status to `payment`.',
                        'policies'    => ['can_post'],
                        'onafter'     => 'onafterPost',
                        'status'      => 'posted'
                    ]
                ]
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_post' => [
                'description' => 'Verifies that the proforma can be invoiced.',
                'function'    => 'policyCanPost'
            ]
        ];
    }

    protected static function policyCanPost($self) {
        $result = [];
        $self->read([
                'status',
                'bank_statement_line_id' => ['status', 'bank_statement_id']
            ]);

        foreach($self as $id => $payment) {
            if($payment['status'] !== 'proforma') {
                $result[$id] = [
                    'invalid_status' => 'Only pending payment can be posted.'
                ];
                continue;
            }
            if($payment['bank_statement_line_id']['status'] !== 'posted') {
                $result[$id] = [
                    'statement_line_not_posted' => 'Payment can only be posted once related bank statement line already is.'
                ];
                continue;
            }
            if( !($payment['bank_statement_line_id']['bank_statement_id'] ?? null) ) {
                $result[$id] = [
                    'missing_mandatory_bank_statement' => 'Payment not linked to any bank statement.'
                ];
                continue;
            }

        }
        return $result;
    }

    protected static function onafterPost($self) {
        $self->read(['funding_id' => ['funding_type']]);
        foreach($self as $id => $payment) {
            Funding::id($payment['funding_id']['id'])->do('refresh_status');
        }
    }

}
