<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\bank;

use equal\orm\Domain;
use equal\orm\Model;
use sale\pay\Funding;
use sale\pay\Payment;

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
                'foreign_object'    => 'realestate\property\Condominium',
                //'readonly'          => true
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
                'foreign_object'    => 'sale\pay\Payment',
                'foreign_field'     => 'statement_line_id',
                'description'       => 'The list of payments this line relates to.',
                'onupdate'          => 'onupdatePaymentsIds',
                'ondetach'          => 'delete'
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date at which the statement was issued.',
                'readonly'          => true,
                'required'          => true
            ],

            'message' => [
                'type'              => 'string',
                'description'       => 'Message from the payer (or ref from the bank).',
                'readonly'          => true
            ],

            'structured_message' => [
                'type'              => 'string',
                'description'       => 'Structured message, if any.',
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

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',              // requires a review
                    'ignored',              // has been manually processed but does not relate to a booking
                    'reconciled'            // has been processed and assigned to a payment
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
        $self->read(['condo_id', 'bank_statement_id' => ['bank_account_iban'], 'structured_message', 'message', 'date', 'amount', 'account_iban']);

        foreach($self as $id => $bankStatementLine) {

            $amount = $bankStatementLine['amount'];
            $iban = $bankStatementLine['account_iban'];
            $is_outgoing = $bankStatementLine['amount'] < 0.0;

            $structured = trim(str_replace(['+', '/', ' '], '', $bankStatementLine['structured_message'] ?? ''));
            $message = trim(str_replace(['+', '/', ' '], '', $bankStatementLine['message'] ?? ''));

            $domain = [
                ['is_cancelled', '=', false],
                ['status', '<>', 'balanced']
            ];

            if($is_outgoing) {
                $domain[] = ['due_amount', '=', $amount];
                $domain[] = ['bank_account_iban', '=', $bankStatementLine['bank_statement_id']['bank_account_iban']];
                $domain[] = ['counterpart_bank_account_iban', '=', $iban];
            }
            else {
                $domain[] = ['due_amount', '=', -$amount];
                $domain[] = ['bank_account_iban', '=', $iban];
                $domain[] = ['counterpart_bank_account_iban', '=', $bankStatementLine['bank_statement_id']['bank_account_iban']];
            }

            $funding = null;

            if(strlen($structured) > 0) {
                $funding = Funding::search(
                    array_merge(
                        $domain,
                        [['payment_reference', '=', $structured]]
                    ))
                    ->first();
            }

            if(!$funding) {
                if(strlen($message) > 0) {
                    $funding = Funding::search(
                        array_merge(
                            $domain,
                            [['payment_reference', '=', $message]]
                        ))
                        ->first();
                }

            }

            if(!$funding) {
                continue;
            }

            $communication = (strlen($structured) > 0) ? $structured : $message;

            Payment::create([
                    'condo_id'          => $bankStatementLine['condo_id'],
                    'amount'            => $bankStatementLine['amount'],
                    'communication'     => $communication,
                    'receipt_date'      => $bankStatementLine['date'],
                    'payment_origin'    => 'bank',
                    'payment_method'    => 'wire_transfer',
                    'statement_line_id' => $id,
                    'funding_id'        => $funding['id']
                ]);
        }
    }

    /**
     * Update status according to the payments attached to the line.
     * Line is considered 'reconciled' if its amount matches the sum of its payments.
     *
     */
    protected static function onupdatePaymentsIds($om, $ids, $values, $lang) {
        $lines = $om->read(self::getType(), $ids, ['amount', 'payments_ids.amount']);

        if($lines > 0) {
            foreach($lines as $lid => $line) {
                $sum = 0;
                $payments = (array) $line['payments_ids.amount'];
                foreach($payments as $pid => $payment) {
                    $sum += $payment['amount'];
                }
                $status = 'pending';
                if($sum == $line['amount']) {
                    $status = 'reconciled';
                }
                $om->update(self::getType(), $lid, ['status' => $status, 'remaining_amount' => null]);
            }
        }
    }

    protected static function calcRemainingAmount($self) {
        $result = [];
        $self->read(['payments_ids', 'amount']);
        foreach($self as $lid => $line) {
            $sum = 0.0;
            $payments = Payment::ids($line['payments_ids'])->read(['amount']);

            foreach($payments as $pid => $payment) {
                $sum += $payment['amount'];
            }

            $result[$lid] = $line['amount'] - $sum;
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