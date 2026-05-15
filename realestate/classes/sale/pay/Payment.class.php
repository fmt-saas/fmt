<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\sale\pay;

use finance\bank\BankStatementLine;

class Payment extends \realestate\sale\pay\FundingAllocation {

    public static function getDescription() {
        return 'A payment is an amount of money that was paid by a customer for a product or service.'
            .' It can origin form the cashdesk or a bank transfer. If it is from a bank transfer it is linked to a bank statement line.';
    }

    public static function getColumns() {
        return [
            'payment_origin' => [
                'type'              => 'string',
                'selection'         => [
                    'cashdesk',             // money was received at the cashdesk
                    'bank'                  // money was received on a bank account
                ],
                'description'       => "Origin of the received money.",
                'default'           => 'bank'
            ],

            'payment_method' => [
                'type'              => 'string',
                'selection'         => [
                    'cash',                 // cash money
                    'bank_card',            // electronic payment with credit card
                    'voucher',              // gift or coupon
                    'wire_transfer'         // transfer between bank account
                ],
                'description'       => "The method used for payment at the cashdesk.",
                'default'           => 'wire_transfer'
            ],

            'receipt_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'The Bank account the payment relates to.',
                'help'              => 'This is the bank account on which movement was actually performed (received or sent), and might differ from the Funding banK-account_id.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'bank_statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'description'       => 'The bank statement line the payment originates from.',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'visible'           => [['origin_object_class', '=', 'finance\bank\BankStatementLine'], ['payment_origin', '=', 'bank']]
            ],

            'voucher_ref' => [
                'type'              => 'string',
                'description'       => 'The reference of the voucher the payment relates to.',
                'visible'           => [ ['payment_origin', '=', 'cashdesk'], ['payment_method', '=', 'voucher'] ]
            ],


            'is_exported' => [
                'type'              => 'boolean',
                'description'       => 'Mark the payment as exported (part of an export to elsewhere).',
                'help'              => 'Meant for due payments, in order to process actual payment.',
                'default'           => false
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'proforma',
                    'posted'
                ],
                'default'           => 'proforma',
                'description'       => 'Status of the payment (updated by parent bank statement).',
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
                'description' => 'Verifies that the state of the Payment allows posting.',
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
        $self->read(['funding_id']);
        foreach($self as $id => $payment) {
            Funding::id($payment['funding_id'])
                ->do('refresh_status');
        }
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['payment_origin'])) {
            switch($event['payment_origin']) {
                case 'cashdesk':
                    $result['bank_statement_line_id'] = null;
                    break;
                case 'bank':
                    $result['payment_method'] = 'cash';
                    $result['voucher_ref'] = null;
                    break;
            }
        }

        if(array_key_exists('funding_id', $event)) {
            if(isset($event['funding_id'])) {
                $funding = Funding::id($event['funding_id'])
                    ->read(['type', 'due_amount', 'invoice_id' => ['customer_id' => ['name']]])
                    ->first();

                if(!is_null($funding)) {
                    if($funding['funding_type'] == 'sale_invoice' && isset($funding['invoice_id']['customer_id']))  {
                        $result['customer_id'] = [
                            'id'   => $funding['sale_invoice_id']['customer_id']['id'],
                            'name' => $funding['sale_invoice_id']['customer_id']['name']
                        ];
                    }

                    if(isset($values['amount']) && $values['amount'] > $funding['due_amount']) {
                        $result['amount'] = $funding['due_amount'];
                    }
                }
            }
        }

        return $result;
    }

    public static function candelete($self) {
        $self->read(['status']);
        foreach($self as $payment) {
            if($payment['status'] != 'proforma') {
                return ['status' => ['non_removable' => 'Non-proforma payments cannot be deleted manually.']];
            }
        }
        return parent::candelete($self);
    }

    public static function canupdate($self, $values) {
        $self->read(['status', 'is_exported', 'payment_origin', 'amount', 'bank_statement_line_id' => ['amount', 'remaining_amount']]);
        foreach($self as $payment) {
            if($payment['is_exported']) {
                return ['is_exported' => ['non_editable' => 'Once exported a payment can no longer be updated.']];
            }

            if($payment['status'] != 'proforma' && (count($values) > 1 || !isset($values['status']) ) ) {
                return ['status' => ['non_editable' => 'Non proforma payment cannot be updated.']];
            }

            $payment_origin = $values['payment_origin'] ?? $payment['payment_origin'];

            if($payment_origin == 'bank' && isset($values['amount']) && (isset($values['bank_statement_line_id']) || isset($payment['bank_statement_line_id']))) {

                $statement_line = $payment['bank_statement_line_id'];

                if(isset($values['bank_statement_line_id'])) {
                    $statement_line = BankStatementLine::id($values['bank_statement_line_id'])
                        ->read(['amount', 'remaining_amount'])
                        ->first();
                }

                $sign_line = intval($statement_line['amount'] > 0) - intval($statement_line['amount'] < 0);
                $sign_payment = intval($values['amount'] > 0) - intval($values['amount'] < 0);

                // #memo - we prevent creating payment that do not decrease the remaining amount
                if($sign_line != $sign_payment) {
                    return ['amount' => ['incompatible_sign' => "Payment amount ({$values['amount']}) and statement line amount ({$statement_line['amount']}) must have the same sign."]];
                }

                if(round($statement_line['amount'], 2) < 0) {
                    if(round($statement_line['remaining_amount'] + $payment['amount'] - $values['amount'], 2) > 0) {
                        return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$statement_line['remaining_amount']}) (err#3)."]];
                    }
                }
                else {
                    if(round($statement_line['remaining_amount'] + $payment['amount'] - $values['amount'], 2) < 0) {
                        return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$statement_line['remaining_amount']}) (err#4)."]];
                    }
                }

            }
        }

        return parent::canupdate($self, $values);
    }
}
