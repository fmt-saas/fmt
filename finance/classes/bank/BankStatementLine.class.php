<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use equal\orm\Model;
use realestate\sale\pay\Funding;
use realestate\sale\pay\Payment;

class BankStatementLine extends Model {

    public static function getName() {
        return 'Bank statement line';
    }

    public static function getDescription() {
        return 'A bank statement line represents one financial transaction on a bank account. It is a part of a bank statement.';
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry refers to.",
                'foreign_object'    => 'realestate\property\Condominium'
            ],

            'name' => [
                'type'              => 'alias',
                'alias'             => 'sequence_number'
            ],

            'sequence_number' => [
                'type'              => 'integer',
                'description'       => 'Structured message, if any.',
                'default'           => 'defaultSequenceNumber',
                'required'          => true
            ],

            'bank_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatement',
                'description'       => 'The bank statement the line relates to.',
                'required'          => true
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Payment',
                'foreign_field'     => 'statement_line_id',
                'description'       => 'The list of payments this line relates to.',
                'ondetach'          => 'delete',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date of the transaction as provided by the bank.',
                'readonly'          => true,
                'required'          => true
            ],

            'communication' => [
                'type'              => 'string',
                'description'       => 'Message from the payer (or ref from the bank).',
                'readonly'          => true
            ],

            'communication_type' => [
                'type'              => 'string',
                'selection'         => [
                    'free',
                    'RF',
                    'SCOR',
                    'VCS'
                ],
                'description'       => 'Message from the payer (or ref from the bank).',
                'readonly'          => true
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money',
                'description'       => 'Amount of the transaction.',
                'required'          => true
            ],

            'remaining_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money',
                'description'       => 'Amount that still needs to be assigned to payments.',
                'function'          => 'calcRemainingAmount',
                'store'             => true
            ],

            'account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'Counterparty IBAN, if any.',
                'required'          => true
            ],

            'account_holder' => [
                'type'              => 'string',
                'description'       => 'Name of the Person whom the payment originates.'
            ],

            'is_reconciled' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'A statement is balanced if all its lines are reconciled or ignored.',
                'function'          => 'calcIsReconciled'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',              // requires a review
                    'ignored',              // has been manually processed but does not relate to a booking
                    'reconciled',            // has been processed and assigned to a payment
                    'to_refund'
                ],
                'description'       => 'Status of the line.',
                'default'           => 'pending'
            ]

        ];
    }

    public static function getActions() {
        return [
            'reconcile' => [
                'description'   => 'Creates accounting entries according to operation lines.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doReconcile'
            ],
        ];
    }

    public static function defaultSequenceNumber($values) {
        $result = null;
        if(isset($values['bank_statement_id'])) {
            $statement = BankStatement::id($values['bank_statement_id'])->read(['statement_lines_ids' => ['@domain' => ['state', '=', 'instance']]])->first();
            if($statement) {
                $result = count($statement['statement_lines_ids']) + 1;
            }
        }
        return $result;
    }


    protected static function doReconcile($self) {
        $self->read(['is_reconciled', 'condo_id', 'bank_statement_id' => ['bank_account_iban'], 'communication', 'date', 'amount', 'account_iban']);

        foreach($self as $id => $bankStatementLine) {
            if($bankStatementLine['is_reconciled']) {
                continue;
            }

            $amount = $bankStatementLine['amount'];

            $reference = trim(str_replace(['+', '/', ' '], '', $bankStatementLine['communication'] ?? ''));

            if(strlen($reference) <= 0) {
                continue;
            }

            $domain = [
                ['is_cancelled', '=', false],
                ['status', '<>', 'balanced'],
                ['due_amount', '=', $amount],
                ['bank_account_iban', '=', $bankStatementLine['bank_statement_id']['bank_account_iban']],
                ['payment_reference', '=', $reference]
            ];

            $funding = Funding::search(array_merge($domain, [['counterpart_bank_account_iban', '=', $bankStatementLine['account_iban']]]))->first();

            if(!$funding) {
                $funding = Funding::search($domain)->first();
            }

            if(!$funding) {
                trigger_error("APP::no matching funding found for bank statement line {$id} with amount {$amount} and reference {$reference}.", EQ_REPORT_DEBUG);
                continue;
            }

            Payment::create([
                    'condo_id'          => $bankStatementLine['condo_id'],
                    'amount'            => $bankStatementLine['amount'],
                    'communication'     => $bankStatementLine['communication'],
                    'receipt_date'      => $bankStatementLine['date'],
                    'payment_origin'    => 'bank',
                    'payment_method'    => 'wire_transfer',
                    'statement_line_id' => $id,
                    'funding_id'        => $funding['id']
                ]);

            self::id($id)->update(['status' => 'reconciled']);

            // #memo - parent bank statement is_reconciled is not stored
        }
    }

    protected static function calcRemainingAmount($self) {
        $result = [];
        $self->read(['payments_ids', 'amount']);
        foreach($self as $id => $statementLine) {
            $sum = 0.0;
            $payments = Payment::ids($statementLine['payments_ids'])->read(['amount']);

            foreach($payments as $pid => $payment) {
                $sum += $payment['amount'];
            }

            $result[$id] = $statementLine['amount'] - $sum;
        }
        return $result;
    }

    protected static function calcIsReconciled($self) {
        $result = [];
        $self->read(['payments_ids', 'amount']);
        foreach($self as $id => $statementLine) {
            $sum = 0.0;
            $payments = Payment::ids($statementLine['payments_ids'])->read(['amount']);

            foreach($payments as $pid => $payment) {
                $sum += round($payment['amount'], 2);
            }

            $result[$id] = ($payments->count() > 0 && $sum === 0.0);
        }
        return $result;
    }

   /**
     * Check wether an object can be updated.
     * These tests come in addition to the unique constraints return by method `getUnique()`.
     * Checks whether the sum of the fundings of each booking remains lower than the price of the booking itself.
     *
     * @param  \equal\orm\ObjectManager     $orm        ObjectManager instance.
     * @param  array                        $ids        List of objects identifiers.
     * @param  array                        $values     Associative array holding the new values to be assigned.
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array            Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($orm, $ids, $values, $lang) {
        if(isset($values['payments_ids'])) {
            $new_payments_ids = array_map(function ($a) {return abs($a);}, $values['payments_ids']);
            $new_payments = $orm->read(Payment::getType(), $new_payments_ids, ['amount'], $lang);

            $new_payments_diff = 0.0;
            foreach(array_unique($values['payments_ids']) as $pid) {
                if($pid < 0) {
                    $new_payments_diff -= $new_payments[abs($pid)]['amount'];
                }
                else {
                    $new_payments_diff += $new_payments[$pid]['amount'];
                }
            }

            $lines = $orm->read(self::getType(), $ids, ['payments_ids', 'amount', 'remaining_amount'], $lang);

            if($lines > 0) {
                foreach($lines as $lid => $line) {
                    $payments = $orm->read(Payment::getType(), $line['payments_ids'], ['amount'], $lang);
                    $payments_sum = 0;
                    foreach($payments as $pid => $payment) {
                        $payments_sum += $payment['amount'];
                    }

                    if(abs($payments_sum+$new_payments_diff) > abs($line['amount'])) {
                        return ['amount' => ['exceeded_price' => "Sum of the payments cannot be higher than the line total."]];
                    }
                }
            }
            return parent::canupdate($orm, $ids, $values, $lang);
        }
    }

}