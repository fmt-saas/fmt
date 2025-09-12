<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use equal\orm\Model;
use finance\accounting\Account;
use finance\accounting\Journal;
use finance\accounting\MiscOperation;
use finance\accounting\MiscOperationLine;
use realestate\sale\pay\Funding;
use realestate\sale\pay\Payment;
use finance\bank\BankStatement;
use finance\bank\CondominiumBankAccount;
use purchase\supplier\Suppliership;
use realestate\finance\accounting\MoneyRefund;
use realestate\finance\accounting\MoneyTransfer;
use realestate\ownership\Ownership;
use realestate\purchase\accounting\AccountingEntry;
use realestate\purchase\accounting\AccountingEntryLine;

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
                'description'       => 'Sequence number of the line.',
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
                'default'           => 'free'
            ],

            // #todo - handle recurring payments (SEPA mandates)
            'mandate_identifier' => [
                'type'              => 'string',
                'description'       => 'Mandate identifier for SEPA direct debit.'
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount of the transaction.',
                'required'          => true
            ],

            'amount_currency' => [
                'type'              => 'string',
                'description'       => 'Currency of the statement.',
                'default'           => 'EUR'
            ],

            'transaction_type' => [
                'type'              => 'string',
                'description'       => 'Type of transaction of the line.',
                'default'           => 'sepa_direct_debit'
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
                'help'              => 'In theory, this field should be provided, but it might be missing for manually encoded statements.'
                // 'required'          => true
            ],

            'account_bic' => [
                'type'              => 'string',
                'description'       => 'Counterparty IBAN, if any.',
                'help'              => 'In theory, this field should be provided, but it might be missing for manually encoded statements.'
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
                'help'              => "This flag is used as indicator for granting the posting of the line.",
                'function'          => 'calcIsReconciled',
                'store'             => true,
                'instant'           => true
            ],

            'accounting_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => 'Accounting account the statement line relates to.',
                'help'              => "This value can only be set manually and targets the accounting account to use for the counterpart movement.
                    When set, the counterpart accounting document is created automatically when posting the line.
                    This is an accounting account, not to be mixed up with bank accounts.",
                'ondelete'          => 'null',
                'dependents'        => ['accounting_account_code', 'is_misc', 'is_expense', 'is_supplier', 'is_owner', 'ownership_id', 'suppliership_id'],
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

            'is_transfer' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the line as being a transfer between accounts.',
                'help'              => "This field depends on the selected accounting account. When set to true, the line implies the creation of a MoneyTransfer operation.",
                'function'          => 'calcIsTransfer',
                'store'             => true,
                'instant'           => true
            ],

            'is_expense' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the line as an unexpected expense or income.',
                'help'              => "When set to true, the line implies a Miscellaneous Operation.",
                'function'          => 'calcIsExpense',
                'store'             => true,
                'instant'           => true
            ],

            'is_supplier' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the line as a payment from/to a supplier.',
                'help'              => "When set to true, the line implies a link with a Funding.",
                'function'          => 'calcIsSupplier',
                'store'             => true,
                'instant'           => true
            ],

            'is_owner' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the line as a payment from/to an owner(ship).',
                'help'              => "When set to true, the line implies a link with a Funding.",
                'function'          => 'calcIsOwner',
                'store'             => true,
                'instant'           => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_statutory', '=', false], ['is_active', '=', true], ['status', '=', 'validated']],
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [['accounting_account_id', '<>', null], ['is_expense', '=', true]]
            ],

            'owner_share'           => [
                'type'              => 'integer',
                'default'           => 100,
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [['accounting_account_id', '<>', null], ['is_expense', '=', true]]
            ],

            'tenant_share'          => [
                'type'              => 'integer',
                'default'           => 0,
                'description'       => "Default value, in percent, of the amount to be imputed to the tenant when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [['accounting_account_id', '<>', null], ['is_expense', '=', true]]
            ],

            'vat_rate' => [
                'type'              => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'VAT rate to be applied.',
                'default'           => 0.0,
                'visible'           => [['accounting_account_id', '<>', null], ['is_expense', '=', true]]
            ],

            'ownership_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcOwnershipId',
                'store'             => true,
                'instant'           => true,
                'description'       => "The ownership that the funding refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'ondelete'          => 'cascade',
                'domain'            => [['condo_id', '=', 'object.condo_id']],
                'visible'           => [['is_owner', '=', true]]
            ],

            'suppliership_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcSuppliershipId',
                'store'             => true,
                'instant'           => true,
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'description'       => 'The supplier the funding relates to.',
                'domain'            => [['condo_id', '=', 'object.condo_id']],
                'visible'           => [['is_supplier', '=', true]]
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',              // requires a review
                    // #deprecated
                    'reconciled',           // has been processed and assigned to a payment
                    'posted'                // has been posted to accounting and cannot be changed anymore
                ],
                'description'       => 'Status of the line.',
                'default'           => 'pending'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Payment being created.',
                'help'        => 'Status change is triggered by the parent BankStatementLine, which also generates the subsequent accounting entries.',
                'icon'        => 'draw',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the payment status to `payment`.',
                        'onbefore'    => 'onbeforePost',
                        'status'      => 'posted'
                    ]
                ]
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
            'generate_accounting_entry' => [
                'description'   => 'Creates accounting entries according to operation lines.',
                'policies'      => [ 'can_generate_accounting_entry' ],
                'function'      => 'doGenerateAccountingEntry'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_generate_accounting_entry' => [
                'description' => 'Verifies that the proforma can be invoiced.',
                'function'    => 'policyCanGenerateAccountingEntry'
            ]
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

    /**
     * Attempts to reconcile by searching for matching Fundings (based on payment_reference) and, if found, by creating corresponding Payments.
     *
     */
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
                'bank_statement_id' => ['bank_account_iban', 'bank_account_id' => ['id', 'accounting_account_id']],
                'is_misc',
                'is_expense',
                'is_owner',
                'is_supplier'
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

            $selected_funding_id = 0;

            $amount = $bankStatementLine['amount'];
            $reference = trim(str_replace(['+', '/', ' '], '', $bankStatementLine['communication'] ?? ''));

            // attempt to match with an existing Funding
            if(strlen($reference) > 0) {
                // #memo - amount can be positive or negative
                $domain = [
                    ['is_cancelled', '=', false],
                    ['status', '<>', 'balanced'],
                    // #memo - we support the possibility that the payment be made on another bank account than the one linked to the funding (see below)
                    // ['bank_account_iban', '=', $bankStatementLine['bank_statement_id']['bank_account_iban']],
                    // #memo - funding payment reference is computed (depends on funding_type)
                    ['payment_reference', '=', $reference]
                ];

                // #memo - this is not relevant for filtering, but must be verified, depending on the found Funding(s)
                // $funding = Funding::search(array_merge($domain, [['counterpart_bank_account_iban', '=', $bankStatementLine['account_iban']]]))->first();

                // pass-1 - preliminary match
                // #memo - in case of multiple candidates, the oldest one prevails
                $candidateFundings = Funding::search($domain, ['sort' => ['due_date' => 'asc']])
                    ->read(['funding_type', 'bank_account_iban', 'counterpart_bank_account_iban', 'remaining_amount']);

                // pass-2 - validate candidates based on remaining amount and counterpart_bank_account_iban, when mandatory
                // #memo - the payment reference is the main criteria, additional checks can be applied but not sure yet how to handle manual encoding
                foreach($candidateFundings as $funding_id => $funding) {
                    $valid = false;
                    if($amount < 0) {
                        $valid = ($funding['remaining_amount'] <= $amount);
                    }
                    else {
                        $valid = ($funding['remaining_amount'] >= $amount);
                    }

                    if($valid && strlen($bankStatementLine['account_iban'] ?? '') > 0) {
                        if(in_array($funding['funding_type'], ['refund','transfer','invoice'], true)) {
                            // payment was received on another bank account than the one expected
                            if($funding['bank_account_iban'] <> $bankStatementLine['account_iban']) {
                                // $valid = false;
                            }
                        }
                        if(in_array($funding['funding_type'], ['refund','transfer','invoice'], true)) {
                            // #memo - counterpart_bank_account_iban is computed from counterpart_bank_account_id
                            // #memo - for manual encoding, statement line might not hold an account IBAN (might be unknown)
                            if($funding['counterpart_bank_account_iban'] <> $bankStatementLine['account_iban']) {
                                // $valid = false;
                            }
                        }
                    }
                    if($valid) {
                        $selected_funding_id = $funding_id;
                        break;
                    }
                }

            }

            // #todo - move this somewhere else - requires confirmation

            if(!$selected_funding_id) {
                // retrieve the BANK accounting journal
                $journal = Journal::search([['journal_type', '=', 'BANK'], ['condo_id', '=', $bankStatementLine['condo_id']]])->first();

                if($bankStatementLine['is_misc']) {
                    // 1) create a Misc Operation
                    $bank_account_accounting_account_id = $bankStatementLine['bank_statement_id']['bank_account_id']['accounting_account_id'];

                    $miscOperation = MiscOperation::create([
                            'condo_id'          => $bankStatementLine['condo_id'],
                            'description'       => 'reprise de compte epargne',
                            'posting_date'      => time(),
                            'journal_id'        => $journal['id'],
                            'operation_type'    => 'misc'
                        ])
                        ->first();

                    MiscOperationLine::create([
                            'condo_id'          => $bankStatementLine['condo_id'],
                            'misc_operation_id' => $miscOperation['id'],
                            'account_id'        => $bank_account_accounting_account_id,
                            'debit'             => $amount > 0 ? abs($amount) : 0,
                            'credit'            => $amount < 0 ? abs($amount) : 0,
                        ]);

                    MiscOperationLine::create([
                            'condo_id'          => $bankStatementLine['condo_id'],
                            'misc_operation_id' => $miscOperation['id'],
                            'account_id'        => $bankStatementLine['accounting_account_id'],
                            'debit'             => $amount < 0 ? abs($amount) : 0,
                            'credit'            => $amount > 0 ? abs($amount) : 0,
                        ]);

                    // 2) create a Funding for this Misc Op
                    $funding = Funding::create([
                            'condo_id'                      => $bankStatementLine['condo_id'],
                            'misc_operation_id'             => $miscOperation['id'],
                            'funding_type'                  => 'misc',
                            'due_amount'                    => $amount,
                            'bank_account_id'               => $bankStatementLine['bank_statement_id']['bank_account_id']['id']
                            // #memo - payment_reference is a computed field
                        ])
                        ->first();

                    $selected_funding_id = $funding['id'];
                }
                elseif($bankStatementLine['is_expense']) {

                    // il faut créer une OD + répartir les charges entre les copropriétaires
                    // si montant < 0 -> comme une facture d'achat
                    // si montant > 0 -> comme une facture de vente
                }
            }


            if($selected_funding_id) {
                trigger_error("APP::matching funding ({$selected_funding_id}) found for bank statement line {$id} with reference {$reference}.", EQ_REPORT_DEBUG);
                Payment::create([
                        'condo_id'                  => $bankStatementLine['condo_id'],
                        'amount'                    => $bankStatementLine['amount'],
                        'communication'             => $bankStatementLine['communication'],
                        'receipt_date'              => $bankStatementLine['date'],
                        'receipt_bank_account_id'   => $bankStatementLine['bank_statement_id']['bank_account_id']['id'],
                        'payment_origin'            => 'bank',
                        'payment_method'            => 'wire_transfer',
                        'statement_line_id'         => $id,
                        'accounting_account_id'     => $bankStatementLine['accounting_account_id'],
                        'funding_id'                => $selected_funding_id
                    ]);

                self::id($id)->update(['is_reconciled' => null]);
            }



        }
    }

    protected static function calcRemainingAmount($self) {
        $result = [];
        $self->read(['payments_ids' => ['amount'], 'amount']);
        foreach($self as $id => $bankStatementLine) {
            $sum = 0.0;

            foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {
                $sum += $payment['amount'];
            }

            $result[$id] = round($bankStatementLine['amount'] - $sum, 2);
        }
        return $result;
    }

    protected static function calcOwnershipId($self) {
        $result = [];
        $self->read(['condo_id', 'is_owner', 'accounting_account_id' => ['ownership_id']]);
        foreach($self as $id => $bankStatementLine) {
            if(!$bankStatementLine['is_owner']) {
                continue;
            }
            $result[$id] = $bankStatementLine['accounting_account_id']['ownership_id'];
        }
        return $result;
    }

    protected static function calcSuppliershipId($self) {
        $result = [];
        $self->read(['condo_id', 'is_supplier', 'accounting_account_id' => ['suppliership_id']]);
        foreach($self as $id => $bankStatementLine) {
            if(!$bankStatementLine['is_supplier']) {
                continue;
            }
            $result[$id] = $bankStatementLine['accounting_account_id']['suppliership_id'];
        }
        return $result;
    }

    private static function computeIsTransfer($accounting_account_id) {
        $result = false;
        if($accounting_account_id) {
            $account = Account::id($accounting_account_id)->read(['operation_assignment'])->first();
            if($account && $account['operation_assignment'] === 'bank_transfer') {
                $result = true;
            }
        }
        return $result;
    }

    private static function computeIsExpense($accounting_account_id) {
        $result = false;
        if($accounting_account_id) {
            $account = Account::id($accounting_account_id)->read(['code'])->first();
            if($account) {
                $account_class_digit = substr($account['code'], 0, 1);
                // expense or income
                $result = ($account_class_digit === '6' || $account_class_digit === '7');
            }
        }
        return $result;
    }

    private static function computeIsSupplier($accounting_account_id) {
        $result = false;
        if($accounting_account_id) {
            $account = Account::id($accounting_account_id)->read(['code'])->first();
            if($account) {
                $account_class_digits_two = substr($account['code'], 0, 2);
                $result = ($account_class_digits_two === '44');
            }
        }
        return $result;
    }

    private static function computeIsOwner($accounting_account_id) {
        $result = false;
        if($accounting_account_id) {
            $account = Account::id($accounting_account_id)->read(['code'])->first();
            if($account) {
                $account_class_digits_two = substr($account['code'], 0, 2);
                $result = ($account_class_digits_two === '41');
            }
        }
        return $result;
    }

    protected static function calcIsTransfer($self) {
        $result = [];
        $self->read(['accounting_account_id']);
        foreach($self as $id => $bankStatementLine) {
            $result[$id] = self::computeIsTransfer($bankStatementLine['accounting_account_id']);
        }
        return $result;
    }

    protected static function calcIsExpense($self) {
        $result = [];
        $self->read(['accounting_account_id']);
        foreach($self as $id => $bankStatementLine) {
            $result[$id] = self::computeIsExpense($bankStatementLine['accounting_account_id']);
        }
        return $result;
    }

    protected static function calcIsSupplier($self) {
        $result = [];
        $self->read(['accounting_account_id']);
        foreach($self as $id => $bankStatementLine) {
            $result[$id] = self::computeIsSupplier($bankStatementLine['accounting_account_id']);
        }
        return $result;
    }

    protected static function calcIsOwner($self) {
        $result = [];
        $self->read(['accounting_account_id']);
        foreach($self as $id => $bankStatementLine) {
            $result[$id] = self::computeIsOwner($bankStatementLine['accounting_account_id']);
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

            $result[$id] = ($sum === $statementLine['amount']);
        }
        return $result;
    }

    protected static function onbeforePost($self) {
        $self->do('generate_accounting_entry');
    }

    public static function onchange($event, $values, $view) {
        $result = [];

        // check VAT
        if(isset($event['vat_rate']) && $event['vat_rate'] >= 1) {
            $result['vat_rate'] = round($event['vat_rate'] / 100, 2);
            $event['vat_rate'] = $result['vat_rate'];
        }

        if(isset($event['accounting_account_id']) && $event['accounting_account_id']) {
            $account = Account::id($event['accounting_account_id'])->read(['code'])->first();
            if($account) {
                $result['accounting_account_code'] = $account['code'];
                $result['is_expense'] = self::computeIsExpense($event['accounting_account_id']);
                $result['is_misc'] = self::computeIsMisc($event['accounting_account_id']);
                $result['is_owner'] = self::computeIsOwner($event['accounting_account_id']);
                $result['is_supplier'] = self::computeIsSupplier($event['accounting_account_id']);
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

    protected static function policyCanGenerateAccountingEntry($self) {
        $result = [];
        $self->read([
                'status',
                'bank_statement_id',
                'payments_ids' => ['funding_id']
            ]);
        foreach($self as $id => $bankStatementLine) {
            if($bankStatementLine['status'] !== 'pending') {
                $result[$id] = [
                    'invalid_status' => 'Only pending bank statement lines can be posted.'
                ];
                continue;
            }
            foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {
                if(!$payment['funding_id']) {
                    $result[$id] = [
                        'payment_with_no_funding' => 'Unexpected statement line payment not linked to any funding.'
                    ];
                    continue 2;
                }
            }

            $bankStatement = BankStatement::id($bankStatementLine['bank_statement_id'])
                ->read(['bank_account_id'])
                ->first();

            $bankAccount = CondominiumBankAccount::id($bankStatement['bank_account_id'])->read(['accounting_account_id'])->first();

            if(!$bankAccount) {
                $result[$id] = [
                    'mismatch_bank_account_with_condominium' => 'Bank Statement IBAN not amongst targeted Condominium accounts.'
                ];
                continue;
            }
        }
        return $result;
    }

    /**
     * Unlike invoices, Accounting Entries related to bank statement are made on the children objects (lines) to allow accurate reconciliation 
     * while allowing progressive processing of a given statement (i.e. not having to reconciling all lines before posting bank movements).
     *
     */
    protected static function doGenerateAccountingEntry($self) {
        $self->read([
                'condo_id',
                'bank_statement_id',
                'payments_ids' => [
                    'amount',
                    'journal_id',
                    'receipt_date',
                    'fiscal_year_id',
                    'fiscal_period_id',
                    'accounting_account_id',
                    'has_funding',
                    'funding_id' => [
                        'funding_type',
                        'money_transfer_id',
                        'money_refund_id',
                        'ownership_id',
                        'suppliership_id',
                        'bank_account_id',
                        'counterpart_bank_account_id'
                    ]
                ]
            ]);

        foreach($self as $id => $bankStatementLine) {
            BankStatementLine::id($id)->update(['remaining_amount' => null]);
            $bankStatement = BankStatement::id($bankStatementLine['bank_statement_id'])
                ->read(['bank_account_id'])
                ->first();

            $bankAccount = CondominiumBankAccount::id($bankStatement['bank_account_id'])->read(['accounting_account_id'])->first();
            $journal = Journal::search([['journal_type', '=', 'BANK'], ['condo_id', '=', $bankStatementLine['condo_id']]])->first();

            foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {

                try {

                    // #memo -  on garantit en amont que 1 Payment a toujours 1 Funding


                    /*
                        special cases : "atomic" accounting entry
                    */

                    if($payment['funding_id']['funding_type'] === 'transfer') {
                        // create a single accounting entry, only if transfer is complete
                        MoneyTransfer::id($payment['funding_id']['money_transfer_id'])->do('attempt_posting');
                        continue;
                    }
                    elseif($payment['funding_id']['funding_type'] === 'refund') {
                        // create a single accounting entry, only if refund is complete
                        MoneyRefund::id($payment['funding_id']['money_refund_id'])->do('attempt_posting');
                        continue;
                    }

                    /*
                        generic case
                    */

                    $accountingEntry = AccountingEntry::create([
                            'condo_id'              => $bankStatementLine['condo_id'],
                            'entry_date'            => $payment['receipt_date'],
                            'origin_object_class'   => self::getType(),
                            'origin_object_id'      => $id,
                            'journal_id'            => $journal['id'],
                            'fiscal_year_id'        => $payment['fiscal_year_id'],
                            'fiscal_period_id'      => $payment['fiscal_period_id']
                        ])
                        ->first();

                    switch($payment['funding_id']['funding_type']) {
                        case 'transfer':
                        case 'refund':
                            // #memo - we should not end up here : refund & transfer are handled as special cases
                            break;
                        case 'installment':
                            // #todo - invoiced installment are a specific type of invoice (deposit)
                            // #memo - non-invoiced downpayment should not generate an accounting entry (received amount should be placed in temp account)
                            throw new \Exception('non_supported_funding_type', EQ_ERROR_INVALID_PARAM);
                            break;
                        case 'misc':
                            // transfert manuel : on inverse le débit et le crédit
                            $credit_account_id = $payment['accounting_account_id'];
                            $debit_account_id  = $bankAccount['accounting_account_id'];
                            break;
                        case 'invoice':
                            // purchase invoice : payment to the supplier
                            $suppliershipAccount = Account::search([
                                    ['condo_id', '=', $payment['condo_id']],
                                    ['suppliership_id', '=', $payment['funding_id']['suppliership_id']]
                                ])
                                ->first();

                            if(!$suppliershipAccount) {
                                throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                            }

                            $debit_account_id = $suppliershipAccount['id'];
                            // #memo - the source of truth is the bank statement, not the funding
                            // $credit_account_id = $payment['funding_id']['bank_account_id'];
                            $credit_account_id = $bankAccount['accounting_account_id'];
                            break;
                        case 'fund_request':
                            // payment from the owner(ship)
                            $ownershipAccount = Account::search([
                                    ['condo_id', '=', $payment['condo_id']],
                                    ['ownership_id', '=', $payment['funding_id']['ownership_id']],
                                    ['operation_assignment', '=', 'co_owners_reserve_fund']
                                ])
                                ->first();

                            if(!$ownershipAccount) {
                                throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                            }

                            $debit_account_id = $bankAccount['accounting_account_id'];
                            $credit_account_id = $ownershipAccount['id'];
                            break;
                        case 'expense_statement':
                            // payment from the owner(ship)
                            $ownershipAccount = Account::search([
                                    ['condo_id', '=', $payment['condo_id']],
                                    ['ownership_id', '=', $payment['funding_id']['ownership_id']],
                                    ['operation_assignment', '=', 'co_owners_working_fund']
                                ])
                                ->first();

                            if(!$ownershipAccount) {
                                throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                            }

                            $debit_account_id = $bankAccount['accounting_account_id'];
                            $credit_account_id = $ownershipAccount['id'];
                            break;
                        default:
                            throw new \Exception('invalid_funding_type', EQ_ERROR_INVALID_PARAM);
                    }

// #todo - on devrait générer les écritures sur base de l'objet lié : MiscOp, PurchaseInvoice, ...

                    $amount = round($payment['amount'], 2);

                    // debit line
                    AccountingEntryLine::create([
                                'condo_id'               => $bankStatementLine['condo_id'],
                                'account_id'             => $debit_account_id,
                                'debit'                  => $amount > 0 ? abs($amount) : 0,
                                'credit'                 => $amount < 0 ? abs($amount) : 0,
                                'accounting_entry_id'    => $accountingEntry['id'],
                                'bank_statement_line_id' => $id,
                            ]);

                    // credit line
                    AccountingEntryLine::create([
                                'condo_id'               => $bankStatementLine['condo_id'],
                                'account_id'             => $credit_account_id,
                                'debit'                  => $amount < 0 ? abs($amount) : 0,
                                'credit'                 => $amount > 0 ? abs($amount) : 0,
                                'accounting_entry_id'    => $accountingEntry['id'],
                                'bank_statement_line_id' => $id,
                            ]);

                    Funding::id($payment['funding_id']['id'])->do('refresh_status');

                }
                catch(\Exception $e) {
                    trigger_error("APP::doGenerateAccountingEntry: Error while creating accounting entries for payment #{$id} : " . $e->getMessage(), EQ_REPORT_ERROR);
                }
            }
        }

    }

    public static function onafterupdate($self) {
        $self->read(['bank_statement_id']);
        $map_statements_ids = [];
        foreach($self as $id => $bankStatementLine) {
            $map_statements_ids[$bankStatementLine['bank_statement_id']] = true;
        }
        BankStatement::ids(array_keys($map_statements_ids))->do('update_document_json');
    }


}