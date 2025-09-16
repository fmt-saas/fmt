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
use realestate\finance\accounting\AccountingEntry;
use realestate\finance\accounting\AccountingEntryLine;

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
                'foreign_field'     => 'bank_statement_line_id',
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
                'dependents'        => ['accounting_account_code', 'is_misc', 'is_transfer', 'is_expense', 'is_income', 'is_supplier', 'is_owner', 'ownership_id', 'suppliership_id'],
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
                'help'              => "When set to true, the line implies a stand alone purchase operation.",
                'function'          => 'calcIsExpense',
                'store'             => true,
                'instant'           => true
            ],

            'is_income' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the line as an unexpected expense or income.',
                'help'              => "When set to true, the line implies a stand alone sale operation.",
                'function'          => 'calcIsIncome',
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
                'visible'           => [
                    [['accounting_account_id', '<>', null], ['is_expense', '=', true]],
                    [['accounting_account_id', '<>', null], ['is_income', '=', true]]
                ]
            ],

            'owner_share'           => [
                'type'              => 'integer',
                'default'           => 100,
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [
                    [['accounting_account_id', '<>', null], ['is_expense', '=', true]],
                    [['accounting_account_id', '<>', null], ['is_income', '=', true]]
                ]
            ],

            'tenant_share'          => [
                'type'              => 'integer',
                'default'           => 0,
                'description'       => "Default value, in percent, of the amount to be imputed to the tenant when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [
                    [['accounting_account_id', '<>', null], ['is_expense', '=', true]],
                    [['accounting_account_id', '<>', null], ['is_income', '=', true]]
                ]
            ],

            'vat_rate' => [
                'type'              => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'VAT rate to be applied.',
                'default'           => 0.0,
                'visible'           => [
                    [['accounting_account_id', '<>', null], ['is_expense', '=', true]],
                    [['accounting_account_id', '<>', null], ['is_income', '=', true]]
                ]
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
                        'policies'    => ['can_post'],
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
            'validate' => [
                'description'   => 'Creates accounting entries according to operation lines.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doValidate'
            ],
            'reconcile_with_funding' => [
                'description'   => 'Creates Funding and related Payment that use the Bank Statement line itself as accounting document.',
                'policies'      => [],
                'function'      => 'doReconcileWithFunding'
            ],
            'generate_orphan_operation' => [
                'description'   => 'Creates Funding and related Payment that use the Bank Statement line itself as accounting document.',
                'policies'      => [ 'can_generate_orphan_operation' ],
                'function'      => 'doGenerateOrphanOperation'
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
            'can_post' => [
                'description' => 'Verifies that the bank statement line is fully reconciled.',
                'function'    => 'policyCanPost'
            ],
            'can_generate_orphan_operation' => [
                'description' => 'Verifies that statement line can be reconciled as a stand alone operation.',
                'function'    => 'policyCanGenerateOrphanOperation'
            ],
            'can_generate_accounting_entry' => [
                'description' => 'Verifies that the accounting entry for the stand alone statement line can be generated.',
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
     * Il y a une distinction entre validate et post, mais dans les deux cas, la ligne est marquée comme posted à la fin de l'opération.
     * La transition 'post' ne devrait être invoquée que via un attempt_post_accounting_document du Funding associé, dans le cas ou la ligne d'extrait est considérée comme une pièce comptable .
     */
    protected static function doValidate($self) {

        $self->read(['payments_ids' => ['funding_id']]);
        foreach($self as $id => $bankStatementLine) {
            foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {
                // retrouver la pièce comptable liée au funding, si elle n'est pas postée, la poster
                // ceci pourrait aboutir au 'post' de la ligne en cours
                Funding::id($payment['funding_id'])->do(['attempt_post_accounting_document']);
                Payment::id($payment_id)->do('post');
            }
        }

        $self->update(['status' => 'posted']);
    }

    /**
     *  The line is expected to be assigned to an accounting account, but with no Payment (nor Funding)
     */
    protected static function doGenerateOrphanOperation($self) {
        $self->read([
                'condo_id',
                'is_reconciled',
                'amount',
                'account_iban',
                'communication',
                'is_misc',
                'is_income',
                'is_expense',
                'is_income',
                'is_transfer',
                'is_owner',
                'is_supplier',
                'accounting_account_id',
                'payments_ids',
                'bank_statement_id' => ['bank_account_id' => ['id', 'accounting_account_id']],
            ]);

        foreach($self as $id => $bankStatementLine) {

            if(count($bankStatementLine['payments_ids']) > 0) {
                continue;
            }

            if(!$bankStatementLine['accounting_account_id']) {
                continue;
            }

            $amount = $bankStatementLine['amount'];

            if($bankStatementLine['is_misc']) {
                // retrieve the BANK accounting journal
                $miscJournal = Journal::search([['journal_type', '=', 'MISC'], ['condo_id', '=', $bankStatementLine['condo_id']]])->first();

                // 1) create a Misc Operation
                $bank_account_accounting_account_id = $bankStatementLine['bank_statement_id']['bank_account_id']['accounting_account_id'];

                $miscOperation = MiscOperation::create([
                        'condo_id'          => $bankStatementLine['condo_id'],
                        'description'       => 'reprise sur compte bancaire',
                        'posting_date'      => time(),
                        'journal_id'        => $miscJournal['id'],
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
                // #memo - MiscOp can be made on any account, so there is no generic way to create fundings inherent to MiscOperation (no `create_fundings` action)
                $funding = Funding::create([
                        'condo_id'                          => $bankStatementLine['condo_id'],
                        'misc_operation_id'                 => $miscOperation['id'],
                        'funding_type'                      => 'misc',
                        'due_amount'                        => $amount,
                        'bank_account_id'                   => $bankStatementLine['bank_statement_id']['bank_account_id']['id'],
                        'counterpart_accounting_account_id' => $bankStatementLine['accounting_account_id'],
                        'due_date'                          => time() + 10 * 86400,
                        // #memo - payment_reference is a computed field
                    ])
                    ->first();

            }
            // movement from one banking accounting to another
            elseif($bankStatementLine['is_transfer']) {

                // si le montant est positif c'est du compte contrepartie vers le compte de l'extrait
                // si le montant est négatif c'est du compte de l'extrait vers le compte contrepartie
                $condoBankAccount = CondominiumBankAccount::search([['condo_id', '=', $bankStatementLine['condo_id']], ['bank_account_iban', '=', $bankStatementLine['account_iban']]])->first();

                if($amount < 0) {
                    $bank_account_id = $bankStatementLine['bank_statement_id']['bank_account_id']['id'];
                    $counterpart_bank_account_id = $condoBankAccount['id'];
                }
                else {
                    $bank_account_id = $condoBankAccount['id'];
                    $counterpart_bank_account_id = $bankStatementLine['bank_statement_id']['bank_account_id']['id'];
                }

                // create MoneyTransfer
                $moneyTransfers = MoneyTransfer::create([
                        'condo_id'                      => $bankStatementLine['condo_id'],
                        'description'                   => $bankStatementLine['communication'],
                        'posting_date'                  => time(),
                        'amount'                        => $amount,
                        'bank_account_id'               => $bank_account_id,
                        'counterpart_bank_account_id'   => $counterpart_bank_account_id
                    ]);

                // create funding
                $moneyTransfers->do('create_fundings');
                $moneyTransfer = $moneyTransfers->first();

                $funding = Funding::search([['money_transfer_id', '=', $moneyTransfer['id']]])->first();
            }
            // movement on ownership/suppliership accounts, without counterpart accounting document (bank statement line is the accounting document)
            elseif($bankStatementLine['is_owner'] || $bankStatementLine['is_supplier']) {
                // pour le moment, on ne gère pas ce cas (la comptabilisation est ambigue)
                $values = [
                        'condo_id'                          => $bankStatementLine['condo_id'],
                        'bank_statement_line_id'            => $id,
                        'funding_type'                      => 'statement_line',
                        'due_amount'                        => $bankStatementLine['amount'],
                        'bank_account_id'                   => $bankStatementLine['bank_statement_id']['bank_account_id']['id'],
                        'counterpart_accounting_account_id' => $bankStatementLine['accounting_account_id'],
                        // #memo - avoid any irrelevant alert
                        'due_date'                          => time() + (10 * 86400),
                        // #memo - payment_reference is a computed field
                    ];

                if($bankStatementLine['is_owner'] ) {
                    // retrieve ownership_id from given accounting_account_id
                    $account = Account::id($bankStatementLine['accounting_account_id'])->read(['ownership_id'])->first();
                    $values['ownership_id'] = $account['ownership_id'];
                }
                elseif($bankStatementLine['is_supplier'] ) {
                    // retrieve suppliership_id from given accounting_account_id
                    $account = Account::id($bankStatementLine['accounting_account_id'])->read(['suppliership_id'])->first();
                    $values['suppliership_id'] = $account['suppliership_id'];
                }

                $funding = Funding::create($values)->first();
            }
            // movement on expense/income accounts, without counterpart accounting document
            elseif($bankStatementLine['is_expense'] || $bankStatementLine['is_income']) {

                $funding = Funding::create([
                    'condo_id'                          => $bankStatementLine['condo_id'],
                    'bank_statement_line_id'            => $id,
                    'funding_type'                      => 'statement_line',
                    'due_amount'                        => $bankStatementLine['amount'],
                    'bank_account_id'                   => $bankStatementLine['bank_statement_id']['bank_account_id']['id'],
                    'counterpart_accounting_account_id' => $bankStatementLine['accounting_account_id'],
                    // #memo - avoid any irrelevant alert
                    'due_date'                          => time() + (10 * 86400),
                    // #memo - payment_reference is a computed field
                ])
                ->first();
            }

            // create the related Payment
            Payment::create([
                    'condo_id'                  => $bankStatementLine['condo_id'],
                    'amount'                    => $bankStatementLine['amount'],
                    'communication'             => $bankStatementLine['communication'],
                    'receipt_date'              => $bankStatementLine['date'],
                    'receipt_bank_account_id'   => $bankStatementLine['bank_statement_id']['bank_account_id']['id'],
                    'payment_origin'            => 'bank',
                    'payment_method'            => 'wire_transfer',
                    'bank_statement_line_id'    => $id,
                    'funding_id'                => $funding['id']
                ]);

        }
    }

    /**
     * Reconcile the line by creating a Payment.
     * Link the line with a Payment attached to given funding.
     *
     */
    protected static function doReconcileWithFunding($self, $values) {
        if(!isset($values['funding_id'])) {
            throw new \Exception('missing_mandatory_funding_id', EQ_ERROR_INVALID_PARAM);
        }

        $funding = Funding::id($values['funding_id'])->first();

        if(!$funding) {
            throw new \Exception('provided_funding_not_found', EQ_ERROR_INVALID_PARAM);
        }

        $self->read(['condo_id', 'amount', 'communication', 'date',
                'bank_statement_id' => ['bank_account_id' ]
            ]);

        foreach($self as $id => $bankStatementLine) {
            Payment::create([
                    'condo_id'                  => $bankStatementLine['condo_id'],
                    'amount'                    => $bankStatementLine['amount'],
                    // #memo - communication might not be a payment reference but an arbitrary comment or description
                    'communication'             => $bankStatementLine['communication'],
                    'receipt_date'              => $bankStatementLine['date'],
                    'receipt_bank_account_id'   => $bankStatementLine['bank_statement_id']['bank_account_id'],
                    'payment_origin'            => 'bank',
                    'payment_method'            => 'wire_transfer',
                    'bank_statement_line_id'    => $id,
                    'funding_id'                => $funding['id']
                ]);
        }
    }

    /**
     * This method either links the line with a Funding through a Payment,
     * or generates an orphan operation referencing current line as accounting document.
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
                'bank_statement_id' => ['bank_account_iban', 'bank_account_id' => ['id', 'accounting_account_id']]
            ]);

        foreach($self as $id => $bankStatementLine) {
            if($bankStatementLine['status'] !== 'pending') {
                continue;
            }

            // in all situations, abort attempt if some payment have already been created
            if($bankStatementLine['payments_ids']->count() > 0) {
                continue;
            }

            // 1) attempt to reconcile with a matching Funding
            $matching_funding_id = self::computeMatchingFunding($bankStatementLine['amount'], $bankStatementLine['communication'], $bankStatementLine['bank_statement_id']['bank_account_iban'], $bankStatementLine['account_iban']);
            if($matching_funding_id) {
                self::id($id)->do('reconcile_with_funding', ['funding_id' => $matching_funding_id]);
            }
            // 2) attempt to reconcile with a matching Funding
            elseif($bankStatementLine['accounting_account_id']) {
                self::id($id)->do('generate_orphan_operation');
            }
            self::id($id)->update(['is_reconciled' => null]);
        }

    }

    /**
     * Attempt to reconcile:
     *  - either by searching for matching Fundings (based on payment_reference) and, if found, by creating corresponding Payments
     *  - or, if no funding and accounting_account provided, by handling the line as an orphan operation
     *
     */
    private static function computeMatchingFunding($amount, $communication, $account_iban, $counterpart_iban) {
        $selected_funding_id = 0;

        $reference = trim(str_replace(['+', '/', ' '], '', $communication));

        // attempt to match with an existing Funding
        if(strlen($reference) <= 0) {
            return $selected_funding_id;
        }
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

            if($valid) {
                if(strlen($account_iban) > 0 && in_array($funding['funding_type'], ['purchase_invoice','fund_request','expense_statement'], true)) {
                    // payment was received on another bank account than the one expected
                    // #memo - we accept a movement made to or from a distinct account than the one expected in the Funding (the actual account is referenced in the Payment as `receipt_bank_account_id`)
                    if($funding['bank_account_iban'] <> $account_iban) {
                        // $valid = false;
                    }
                }
                if(strlen($counterpart_iban) > 0 && in_array($funding['funding_type'], ['refund','transfer'], true)) {
                    // #memo - counterpart_bank_account_iban is computed from counterpart_bank_account_id
                    // #memo - for manual encoding, statement line might not hold an account IBAN (might be unknown)
                    if($funding['counterpart_bank_account_iban'] <> $counterpart_iban) {
                        // $valid = false;
                    }
                }
            }

            if($valid) {
                trigger_error("APP::matching funding ({$selected_funding_id}) found for bank statement line with reference {$reference}.", EQ_REPORT_DEBUG);
                $selected_funding_id = $funding_id;
                break;
            }
        }

        return $selected_funding_id;
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
            if(!$bankStatementLine['is_owner'] || !$bankStatementLine['accounting_account_id']['ownership_id']) {
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
            if(!$bankStatementLine['is_supplier'] || !isset($bankStatementLine['accounting_account_id']['suppliership_id'])) {
                continue;
            }
            $result[$id] = $bankStatementLine['accounting_account_id']['suppliership_id'];
        }
        return $result;
    }

    private static function computeIsReconciled($id) {
        $bankStatementLine = self::id($id)->read(['amount', 'payments_ids' => ['amount']])->first();
        $sum = 0.0;

        foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {
            $sum += $payment['amount'];
        }

        $result[$id] = (abs($sum - $bankStatementLine['amount']) < 0.01);
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

    private static function computeIsIncome($accounting_account_id) {
        $result = false;
        if($accounting_account_id) {
            $account = Account::id($accounting_account_id)->read(['code'])->first();
            if($account) {
                $account_class_digit = substr($account['code'], 0, 1);
                $result = ($account_class_digit === '7');
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
                $result = ($account_class_digit === '6');
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

    protected static function calcIsIncome($self) {
        $result = [];
        $self->read(['accounting_account_id']);
        foreach($self as $id => $bankStatementLine) {
            $result[$id] = self::computeIsIncome($bankStatementLine['accounting_account_id']);
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
            $result[$id] = self::computeIsReconciled($id);
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
                $result['is_income'] = self::computeIsIncome($event['accounting_account_id']);
                $result['is_misc'] = self::computeIsMisc($event['accounting_account_id']);
                $result['is_transfer'] = self::computeIsTransfer($event['accounting_account_id']);
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

    protected static function policyCanPost($self) {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $bankStatementLine) {
            if($bankStatementLine['status'] !== 'pending') {
                $result[$id] = [
                    'invalid_status' => 'Only non-posted bank statement lines can be posted.'
                ];
                continue;
            }

            if(!self::isReconciled($id)) {
                $result[$id] = [
                    'invalid_reconcile_state' => 'Only reconciled bank statement lines can be posted.'
                ];
                continue;
            }
        }
        return $result;
    }

    /**
     * La création d'une écriture comtpable à partir d'une ligne de relevé est un cas particulier, uniquement lorsqu'il n'y a qu'un seul paiement, et que ce paiement est lié à un funding de type 'statement_line'
     */
    protected static function policyCanGenerateAccountingEntry($self) {
        $result = [];
        $self->read([
                'condo_id',
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

            if($bankStatementLine['payments_ids']->count() <= 0) {
                $result[$id] = [
                    'invalid_status' => 'Only bank statement without payment cannot be posted.'
                ];
                continue;
            }

            if($bankStatementLine['payments_ids']->count() > 1) {
                $result[$id] = [
                    'invalid_status' => 'Only bank statement line with a single payment can be posted.'
                ];
                continue;
            }

            foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {
                $funding = Funding::id($payment['funding_id'])->read(['funding_type'])->first();
                if(!$funding) {
                    $result[$id] = [
                        'missing_mandatory_funding' => 'Unexpected statement line not linked to any funding.'
                    ];
                    continue 2;
                }
                if($funding['funding_type'] !== 'statement_line') {
                    $result[$id] = [
                        'invalid_target_funding' => 'Only bank statement lines targeting a statement_line Funding can be posted.'
                    ];
                    continue 2;
                }

            }

            $bankStatement = BankStatement::id($bankStatementLine['bank_statement_id'])
                ->read(['bank_account_id'])
                ->first();

            $bankAccount = CondominiumBankAccount::id($bankStatement['bank_account_id'])->read(['condo_id'])->first();

            if(!$bankAccount) {
                $result[$id] = [
                    'missing_bank_account_condominium' => 'No condominium found for Bank Statement IBAN.'
                ];
                continue;
            }

            if(!$bankAccount['condo_id'] != $bankStatementLine['condo_id']) {
                $result[$id] = [
                    'mismatch_bank_account_with_condominium' => 'Bank Statement IBAN not amongst targeted Condominium bank accounts.'
                ];
                continue;
            }

        }
        return $result;
    }

    protected static function policyCanGenerateOrphanOperation($self) {
        $result = [];

        $self->read([
                'condo_id', 'status', 'payments_ids',
                'accounting_account_id'
            ]);

        foreach($self as $id => $bankStatementLine) {

            if($bankStatementLine['status'] !== 'pending') {
                $result[$id] = [
                    'invalid_status' => 'Only pending bank statement lines can be modified.'
                ];

                continue;
            }

            if(count($bankStatementLine['payments_ids']) > 0) {
                $result[$id] = [
                    'invalid_status' => 'Only non-reconciled bank statement lines can be posted.'
                ];

                continue;
            }

            if(!$bankStatementLine['accounting_account_id']) {
                $result[$id] = [
                    'invalid_status' => 'Only bank statement lines assigned to an accounting account can be posted.'
                ];
                continue;
            }
        }
        return $result;
    }

    /**
     * There are two types of situations:
     *   - either a funding existed prior to the creation of the payment
     *   - or both the payment and the funding were created together (in this case, the document is the BankStatementLine)
     *
     * In the case where the BankStatementLine is considered as the original accounting document, it is responsible for generating the accounting entry.
     * Otherwise, the accounting document was pre-existing and the entries were already generated.
     *
     * The entries to be made in the financial journal (BANK) are always created in the Payment.
     */
    protected static function doGenerateAccountingEntry($self) {

        // ici on le fait à l'envers : on part de la ligne, et on va chercher le funding pour faire l'écriture comptable
        $self->read([
                'condo_id',
                'bank_statement_id' => ['bank_account_id'],
                'payments_ids' => [
                    'amount',
                    'journal_id',
                    'receipt_date',
                    'fiscal_year_id',
                    'fiscal_period_id',
                    'accounting_account_id',
                    'funding_id' => [
                        'funding_type',
                        'ownership_id',
                        'suppliership_id',
                        'bank_account_id',
                        'counterpart_bank_account_id',
                        'counterpart_accounting_account_id'
                    ]
                ]
            ]);

        foreach($self as $id => $bankStatementLine) {

            foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {

                try {

                    $funding = $payment['funding_id'];

                    if($funding['funding_type'] !== 'statement_line') {
                        continue;
                    }

                    if($payment['funding_id']['suppliership_id'] || $payment['funding_id']['ownership_id']) {
                        // le journal va dépendre du type d'opération
                        if($payment['funding_id']['ownership_id']) {
                            $journal = Journal::search([['condo_id', '=', $bankStatementLine['condo_id']], ['journal_type', '=', 'SALE']])->first();
                            // sale invoice : payment from an owner
                            $ownershipAccount = Account::search([
                                    ['condo_id', '=', $bankStatementLine['condo_id']],
                                    ['ownership_id', '=', $funding['ownership_id']]
                                ])
                                ->first();

                            if(!$ownershipAccount) {
                                throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                            }
                            $debit_account_id = $ownershipAccount['id'];
                            $credit_account_id = $funding['counterpart_accounting_account_id'];

                        }
                        elseif($payment['funding_id']['suppliership_id']) {
                            $journal = Journal::search([['condo_id', '=', $bankStatementLine['condo_id']], ['journal_type', '=', 'PURC']])->first();
                            // purchase invoice : payment to the supplier
                            $suppliershipAccount = Account::search([
                                    ['condo_id', '=', $bankStatementLine['condo_id']],
                                    ['suppliership_id', '=', $funding['suppliership_id']]
                                ])
                                ->first();

                            if(!$suppliershipAccount) {
                                throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                            }
                            $credit_account_id = $suppliershipAccount['id'];
                            $debit_account_id = $funding['counterpart_accounting_account_id'];
                        }

                    }
                    // expense / income
                    else {
                        // cas supportés
                        // si compte accounting_account_id = frais banquaires (65)
                        //      -> le suppliership est la banque du condo (correspondant au compte bancaire)
                        $bankAccount = CondominiumBankAccount::id($bankStatementLine['bank_statement_id']['bank_account_id'])->read(['bank_id'])->first();
                        if($bankAccount) {
                            throw new \Exception('missing_bank_account_for_condo', EQ_ERROR_INVALID_PARAM);
                        }
                        $suppliership = Suppliership::search([['condo_id', '=', $bankStatementLine['condo_id']], ['supplier_id', '=', $bankAccount['bank_id']]])->first();
                        if(!$suppliership) {
                            throw new \Exception('missing_suppliership_for_bank', EQ_ERROR_INVALID_PARAM);
                        }

                        // purchase invoice : payment to the supplier
                        $suppliershipAccount = Account::search([
                                ['condo_id', '=', $bankStatementLine['condo_id']],
                                ['suppliership_id', '=', $suppliership['id']]
                            ])
                            ->first();

                        if(!$suppliershipAccount) {
                            throw new \Exception('missing_bank_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                        }

                        $credit_account_id = $suppliershipAccount['id'];
                        $debit_account_id = $funding['counterpart_accounting_account_id'];

                        // la répartition se fait sur les types d'entrées comptables, directement basé sur les frais (? et les rentrées - comptes 7)
                    }

                    $amount = round($payment['amount'], 2);

                    $accountingEntry = AccountingEntry::create([
                            'condo_id'              => $bankStatementLine['condo_id'],
                            'entry_date'            => $payment['receipt_date'],
                            'origin_object_class'   => self::getType(),
                            'origin_object_id'      => $id,
                            'journal_id'            => $journal['id']
                        ])
                        ->first();

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

                }
                catch(\Exception $e) {
                    trigger_error("APP::doGenerateAccountingEntry: Error while creating accounting entries for Bank Statement Line #{$id} : " . $e->getMessage(), EQ_REPORT_ERROR);
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