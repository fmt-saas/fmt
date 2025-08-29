<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use equal\orm\Model;
use finance\accounting\Account;
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
                'required'          => true,
                'default'           => function() {return time();}
            ],

            'communication' => [
                'type'              => 'string',
                'description'       => 'Message from the payer (or ref from the bank).',
                'help'              => "A single communication is handled, since this is implied by the SEPA format (despite some bank allow both free and structured communication on a statement line)."
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
                'usage'             => 'amount/money:2',
                'description'       => 'Amount of the transaction.',
                'required'          => true
            ],

            'remaining_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount that still needs to be assigned to payments.',
                'function'          => 'calcRemainingAmount',
                // 'store'             => true
            ],

            'account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'Counterparty IBAN, if any.',
                'help'              => 'In theory, this field should be provided, but it might not be the case for manually encoded statements.'
                // 'required'          => true
            ],

            'account_holder' => [
                'type'              => 'string',
                'description'       => 'Name of the Person whom the payment originates.'
            ],

            'is_reconciled' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'A line is reconciled if the sum of its payments matches its amount.',
                'function'          => 'calcIsReconciled',
                'store'             => true,
                'instant'           => true
            ],

            'accounting_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the statement line relates to.",
                'help'              => "This value can only be set manually and targets the accounting account to use for the counterpart movement. This is an accounting account, not to be mixed up with bank accounts.",
                'ondelete'          => 'null',
                'dependents'        => ['accounting_account_code', 'is_misc'],
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_control_account', '=', false]]
            ],

            'accounting_account_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Accounting account the statement line relates to.",
                'relation'          => ['accounting_account_id' => 'code'],
                'store'             => true,
                'instant'           => true
            ],

            'is_misc' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Accounting account the statement line relates to.",
                'function'          => 'calcIsMisc',
                'store'             => true,
                'instant'           => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_statutory', '=', false], ['is_active', '=', true], ['status', '=', 'validated']],
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [['accounting_account_id', '<>', null], ['is_misc', '=', false]]
            ],

            'owner_share'           => [
                'type'              => 'integer',
                'default'           => 100,
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [['accounting_account_id', '<>', null], ['is_misc', '=', false]]
            ],

            'tenant_share'          => [
                'type'              => 'integer',
                'default'           => 0,
                'description'       => "Default value, in percent, of the amount to be imputed to the tenant when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [['accounting_account_id', '<>', null], ['is_misc', '=', false]]
            ],

            'vat_rate' => [
                'type'              => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'VAT rate to be applied.',
                'default'           => 0.0,
                'visible'           => [['accounting_account_id', '<>', null], ['is_misc', '=', false]]
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',              // requires a review
                    'reconciled',           // has been processed and assigned to a payment
                    'posted'                // has been posted to accounting and cannot be changed anymore
                ],
                'description'       => 'Status of the line.',
                'default'           => 'pending'
            ]

        ];
    }

    public static function getActions() {
        return [
            'attempt_reconcile' => [
                'description'   => 'Creates accounting entries according to operation lines.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doAttemptReconcile'
            ],
        ];
    }

    protected static function defaultSequenceNumber($values) {
        $result = null;
        if(isset($values['bank_statement_id'])) {
            $statement = BankStatement::id($values['bank_statement_id'])->read(['statement_lines_ids' => ['@domain' => ['state', '=', 'instance']]])->first();
            if($statement) {
                $result = count($statement['statement_lines_ids']) + 1;
            }
        }
        return $result;
    }

    protected static function doAttemptReconcile($self) {
        $self->read([
                'status',
                'condo_id',
                'communication',
                'date',
                'amount',
                'account_iban',
                'accounting_account_id',
                'accounting_account_code',
                'payments_ids' => ['amount', 'status'],
                'bank_statement_id' => ['bank_account_iban']
            ]);

        foreach($self as $id => $bankStatementLine) {
            if($bankStatementLine['status'] !== 'pending') {
                continue;
            }

            // in all situations, abort attempt if some payment have already been created
            $payments = Payment::search(['statement_line_id', '=', $id]);

            if($payments->count() > 0) {
                continue;
            }

            // if statement line has been manually assigned to a specific account
            /*
            // #todo - not sure about this
            if($bankStatementLine['accounting_account_id']) {
                // expense
                if(substr($bankStatementLine['accounting_account_code'], 0, 1) === '6') {
                }
                // income
                elseif(substr($bankStatementLine['accounting_account_code'], 0, 1) === '7') {
                }
                // supplier
                elseif(substr($bankStatementLine['accounting_account_code'], 0, 2) === '44') {
                }
                // ownership
                elseif(substr($bankStatementLine['accounting_account_code'], 0, 2) === '41') {
                }
            }
            */

            $amount = $bankStatementLine['amount'];

            $reference = trim(str_replace(['+', '/', ' '], '', $bankStatementLine['communication'] ?? ''));

            // ignore attempt if no reference is provided
            if(strlen($reference) <= 0) {
                continue;
            }

            // #memo - amount can be positive or negative
            $domain = [
                ['is_cancelled', '=', false],
                ['status', '<>', 'balanced'],
                // ['remaining_amount', '=', $amount],
                ['bank_account_iban', '=', $bankStatementLine['bank_statement_id']['bank_account_iban']],
                // #memo - funding payment reference is computed (depends on funding_type)
                ['payment_reference', '=', $reference]
            ];

            // #memo - this is not relevant for filtering, but must be checked, depending on the found Funding(s)
            // $funding = Funding::search(array_merge($domain, [['counterpart_bank_account_iban', '=', $bankStatementLine['account_iban']]]))->first();

            // pass-1 - preliminary match
            $candidateFundings = Funding::search($domain, ['sort' => ['due_date' => 'asc']])->read(['funding_type', 'counterpart_bank_account_iban', 'remaining_amount']);

            $selected_funding_id = 0;

            // pass-2 - validate candidates based on remaining amount and counterpart_bank_account_iban, when mandatory
            foreach($candidateFundings as $funding_id => $funding) {
                $valid = false;
                if($amount < 0) {
                    $valid = ($funding['remaining_amount'] <= $amount);
                }
                else {
                    $valid = ($funding['remaining_amount'] >= $amount);
                }
                if($valid) {
                    if(in_array($funding['funding_type'], ['refund','transfer','invoice'], true)) {
                        // #memo - counterpart_bank_account_iban is computed from counterpart_bank_account_id
                        if($funding['counterpart_bank_account_iban'] <> $bankStatementLine['account_iban']) {
                            $valid = false;
                        }
                    }
                }
                if($valid) {
                    $selected_funding_id = $funding_id;
                    break;
                }
            }

            if($selected_funding_id) {
                trigger_error("APP::no matching funding found for bank statement line {$id} with reference {$reference}.", EQ_REPORT_DEBUG);
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
                    'funding_id'        => $selected_funding_id
                ]);

            self::id($id)->update(['is_reconciled' => null]);
        }
    }

    protected static function calcRemainingAmount($self) {
        $result = [];
        $self->read(['payments_ids' => ['amount'], 'amount']);
        foreach($self as $id => $statementLine) {
            $sum = 0.0;

            foreach($statementLine['payments_ids'] as $payment_id => $payment) {
                $sum += $payment['amount'];
            }

            $result[$id] = round($statementLine['amount'] - $sum, 2);
        }
        return $result;
    }


    protected static function isMisc($self) {
        $result = [];
        $self->read(['accounting_account_id', 'accounting_account_code']);
        foreach($self as $id => $bankStatementLine) {
            $result[$id] =  false;
            if($bankStatementLine['accounting_account_id']) {
                $account_class_digit = substr($bankStatementLine['accounting_account_code'], 0, 1);
                // expense or income
                if($account_class_digit === '6' || $account_class_digit === '7') {
                    $result[$id] = true;
                }
            }
        }
        return $result;
    }

    protected static function calcIsReconciled($self) {
        $result = [];
        $self->read(['payments_ids' => ['amount'], 'amount']);
        foreach($self as $id => $statementLine) {
            $sum = 0.0;

            foreach($statementLine['payments_ids'] as $payment_id => $payment) {
                $sum += round($payment['amount'], 2);
            }

            $result[$id] = ($sum === 0.0);
        }
        return $result;
    }

    public static function onchange($event, $values, $view) {
        $result = [];
        if(isset($event['accounting_account_id']) && $event['accounting_account_id']) {
            if(isset($values['condo_id'])) {
                $account = Account::search([['condo_id', '=', $values['condo_id']], ['id', '=', $event['accounting_account_id']]])->read(['code'])->first();
                if($account) {
                    $result['accounting_account_code'] = $account['code'];
                }
            }
        }
        return $result;
    }

    public static function canupdate($self) {
        $self->read(['status']);

        foreach($self as $id => $bankStatementLine) {
            if($bankStatementLine['status'] === 'posted') {
                return ['status' => ['posted_line' => "The line is posted and cannot be changed."]];
            }
        }

        return parent::canupdate($self);
    }

}