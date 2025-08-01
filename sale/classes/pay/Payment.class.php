<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\pay;

use equal\orm\Model;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\bank\BankStatement;
use finance\bank\BankStatementLine;
class Payment extends Model {

    public static function getDescription() {
        return 'A payment is an amount of money that was paid by a customer for a product or service.'
            .' It can origin form the cashdesk or a bank transfer. If it is from a bank transfer it is linked to a bank statement line.';
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the payment relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the payment originates from, if any.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'ondelete'          => 'cascade',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'readonly'          => true
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => "The customer to whom the payment relates."
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount paid or received (whatever the origin).',
                'dependents'        => ['funding_id' => ['is_paid', 'paid_amount', 'remaining_amount']]
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Description of the operation.",
                'help'              => "This can be a message from the counterpart or a description for the accounting entry.",
            ],

            'receipt_date' => [
                'type'              => 'datetime',
                'description'       => "Time of reception of the payment.",
                'default'           => time()
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => 'Fiscal year in which the operation is recorded.',
                'function'          => 'calcFiscalYearId',
                'store'             => true,
                'instant'           => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => 'Accounting period derived from the posting date.',
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true
            ],

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
                    'bank_card',            // electronic payment with bank (or credit) card
                    'voucher',              // gift or coupon
                    'wire_transfer'         // transfer between bank account
                ],
                'description'       => "The method used for payment at the cashdesk.",
                'visible'           => ['payment_origin', '=', 'cashdesk'],
                'default'           => 'cash'
            ],

            'statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'description'       => 'The bank statement line the payment relates to.',
                'visible'           => ['payment_origin', '=', 'bank']
            ],

            'voucher_ref' => [
                'type'              => 'string',
                'description'       => 'The reference of the voucher the payment relates to.',
                'visible'           => [ ['payment_origin', '=', 'cashdesk'], ['payment_method', '=', 'voucher'] ]
            ],

            'has_funding' => [
                'type'              => 'boolean',
                'description'       => 'Is the payment linked to an expected operation or not.',
                'default'           => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'description'       => 'The funding the payment relates to, if any.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'visible'           => ['has_funding', '=', true]
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\Invoice',
                'description'       => 'The invoice targeted by the payment, if any.',
                'domain'            => ['status', '=', 'posted']
            ],

            'is_exported' => [
                'type'              => 'boolean',
                'description'       => 'Mark the payment as exported (part of an export to elsewhere).',
                'default'           => false
            ],

            'journal_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => 'Accounting journal used for the Money Transfer.',
                'help'              => 'Money transfer between internal accounts is a bank operation and is always put in the BNK/FIN journal.',
                'store'             => true,
                'function'          => 'calcJournalId',
                'readonly'          => true
            ],

            'accounting_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "The accounting account the payment relates to.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false]],
                'visible'           => ['has_funding', '=', false]
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
                'icon'        => 'draw',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the payment status to `payment`.',
                        'status'      => 'posted'
                    ]
                ]
            ]
        ];
    }

    public static function oncreate($self) {
        $self->read(['funding_id', 'statement_line_id']);
        foreach($self as $id => $payment) {
            if($payment['funding_id']) {
                Funding::id($payment['funding_id'])->update(['paid_amount' => null, 'remaining_amount' => null, 'is_paid' => null]);
            }
            if($payment['statement_line_id']) {
                BankStatement::id($payment['statement_line_id'])->update(['remaining_amount' => null]);
            }
        }
    }

    public static function onafterupdate($self) {
        $self->read(['funding_id', 'statement_line_id']);
        foreach($self as $id => $payment) {
            if($payment['funding_id']) {
                Funding::id($payment['funding_id'])->update(['paid_amount' => null, 'remaining_amount' => null, 'is_paid' => null]);
            }
            if($payment['statement_line_id']) {
                BankStatement::id($payment['statement_line_id'])->update(['remaining_amount' => null]);
            }
        }
    }

    public static function candelete($self) {
        $self->read(['status']);
        foreach($self as $payment) {
            if($payment['status'] != 'proforma') {
                return ['status' => ['non_editable' => 'Non-proforma payments cannot be deleted manually.']];
            }
        }
        return parent::candelete($self);
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['payment_origin'])) {
            switch($event['payment_origin']) {
                case 'cashdesk':
                    $result['statement_line_id'] = null;
                    break;
                case 'bank':
                    $result['payment_method'] = 'cash';
                    $result['voucher_ref'] = null;
                    break;
            }
        }

        if(array_key_exists('funding_id', $event)) {
            if(!isset($event['funding_id'])) {
                $result['has_funding'] = false;
            }
            else {
                $funding = Funding::id($event['funding_id'])
                    ->read(['type', 'due_amount', 'invoice_id' => ['customer_id' => ['name']]])
                    ->first();

                if(!is_null($funding)) {
                    if($funding['funding_type'] == 'invoice' && isset($funding['invoice_id']['customer_id']))  {
                        $result['customer_id'] = [
                            'id'   => $funding['invoice_id']['customer_id']['id'],
                            'name' => $funding['invoice_id']['customer_id']['name']
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

    public static function canupdate($self, $values) {
        $self->read(['status', 'is_exported', 'payment_origin', 'amount', 'statement_line_id' => ['amount', 'remaining_amount']]);
        foreach($self as $payment) {
            if($payment['is_exported']) {
                return ['is_exported' => ['non_editable' => 'Once exported a payment can no longer be updated.']];
            }

            if($payment['status'] != 'proforma' && (count($values) > 1 || !isset($values['status']) ) ) {
                return ['status' => ['non_editable' => 'Non proforma payment cannot be updated.']];
            }

            $payment_origin = $values['payment_origin'] ?? $payment['payment_origin'];

            if($payment_origin == 'bank' && isset($values['amount']) && (isset($values['statement_line_id']) || isset($payment['statement_line_id']))) {

                $statement_line = $payment['statement_line_id'];

                if(isset($values['statement_line_id'])) {
                    $statement_line = BankStatementLine::id($values['statement_line_id'])
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

    private static function computeFiscalYearId($condo_id, $posting_date) {
        $result = null;

        $fiscalYear = FiscalYear::search([['condo_id', '=', $condo_id], ['date_from', '<=', $posting_date], ['date_to', '>=', $posting_date]])
            ->read(['fiscal_periods_ids' => ['date_from', 'date_to']])
            ->first();

        if($fiscalYear) {
            $result = $fiscalYear['id'];
        }

        return $result;
    }

    private static function computeFiscalPeriodId($condo_id, $posting_date) {
        $result = null;

        $fiscalYear = FiscalYear::search([['condo_id', '=', $condo_id], ['date_from', '<=', $posting_date], ['date_to', '>=', $posting_date]])
            ->read(['fiscal_periods_ids' => ['date_from', 'date_to']])
            ->first();

        if(!$fiscalYear) {
            return $result;
        }

        foreach($fiscalYear['fiscal_periods_ids'] ?? [] as $period_id => $period) {
            if($posting_date >= $period['date_from'] && $posting_date <= $period['date_to']) {
                $result = $period_id;
                break;
            }
        }

        return $result;
    }

    protected static function calcFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'receipt_date']);
        foreach($self as $id => $payment) {
            $result[$id] = self::computeFiscalYearId($payment['condo_id'], $payment['receipt_date']);
        }
        return $result;
    }

    protected static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['condo_id', 'receipt_date']);
        foreach($self as $id => $payment) {
            $result[$id] = self::computeFiscalPeriodId($payment['condo_id'], $payment['receipt_date']);
        }
        return $result;
    }

    protected static function calcJournalId($self) {
        $result = [];
        $self->read(['condo_id']);
        foreach($self as $id => $payment) {
            if(!$payment['condo_id']) {
                continue;
            }
            $journal = Journal::search([['condo_id', '=', $payment['condo_id']], ['journal_type', '=', 'CASH']])->first();

            if($journal) {
                $result[$id] = $journal['id'];
            }
        }
        return $result;
    }

}
