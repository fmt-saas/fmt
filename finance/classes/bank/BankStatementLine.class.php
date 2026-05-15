<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use equal\orm\Model;
use finance\accounting\Account;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\accounting\Matching;
use realestate\sale\pay\Funding;
use realestate\sale\pay\Payment;
use finance\bank\BankStatement;
use finance\bank\CondominiumBankAccount;
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
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'alias',
                'alias'             => 'communication'
            ],

            'sequence_number' => [
                'type'              => 'integer',
                'description'       => 'Sequence number of the line.',
                'default'           => 'defaultSequenceNumber',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'bank_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatement',
                'description'       => 'The bank statement the line relates to.',
                'required'          => true
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\finance\accounting\AccountingEntry',
                'description'       => "Accounting entry of the invoice."
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Payment',
                'foreign_field'     => 'bank_statement_line_id',
                'description'       => 'The list of payments this line relates to.',
                'ondetach'          => 'delete',
                'domain'            => [['condo_id', '=', 'object.condo_id'],['condo_id', '<>', null]]
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date of the transaction as provided by the bank.',
                'required'          => true,
                'default'           => 'defaultDate',
                'dependents'        => ['fiscal_year_id', 'name']
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the statement relates to.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'help'              => "Fiscal Year is automatically assigned based on date.",
                'function'          => 'calcFiscalYearId',
                'store'             => true,
                'instant'           => true
            ],

            'communication' => [
                'type'              => 'string',
                'usage'             => 'text/plain:255',
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
                'help'              => 'Positive amount for incoming transfer, negative amount for outgoing transfer.',
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
                'store'             => false
            ],

            'account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'Counterparty IBAN, if any.',
                'help'              => 'In theory, this field should be provided, but it might be missing for manually encoded statements.',
                // 'required'          => true
                'onupdate'          => 'onupdateAccountIban'
            ],

            'account_suffix' => [
                'type'              => 'string',
                'description'       => 'Proprietary or extended account identifier (e.g. ING sub-account, not SEPA-valid).',
                // #memo - so far this only applies to ING bank
                'domain'            => ['account_bic', '=', 'BBRUBEBB']
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
                'dependents'        => ['accounting_account_code', 'is_transfer', 'is_expense', 'is_income', 'is_supplier', 'is_owner', 'ownership_id', 'suppliership_id'],
                'domain'            => [
                    [
                        ['condo_id', '=', 'object.condo_id'], ['is_control_account', '=', false], ['operation_assignment', 'not in', ['co_owners_owner_reserve_fund', 'co_owners_owner_working_fund']]
                    ],
                    [
                        ['condo_id', '=', 'object.condo_id'], ['ownership_id', '<>', null], ['is_control_account', '=', true]
                    ]
                ],
                'onupdate'          => 'onupdateAccountingAccountId'
            ],

            'accounting_account_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Accounting account the statement line relates to.",
                'relation'          => ['accounting_account_id' => 'code'],
                'store'             => true,
                'instant'           => true
            ],

            'matching_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Matching',
                'description'       => 'Matching to which the accounting entry lines of the statement line must be linked, if any.',
                'help'              => "If set, a single lettering reconciles manually picked non-balanced accounting entry lines with the statement line.",
                'visible'           => ['accounting_account_id', '<>', null]
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

            'accounting_entry_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntryLine',
                'foreign_field'     => 'bank_statement_line_id',
                'description'       => "Accounting entries linked to the statement line."
            ],

            'logs' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Logs of the accounting entry generation.'
            ],

            'reconciliation_status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',          // no Funding reconciliation has been performed yet; relevant mainly once the line is posted
                    'part',             // part of the line amount is reconciled with Fundings and requires follow-up
                    'full',             // the full line amount is reconciled with Fundings
                    'not_applicable'    // line not candidate to reconciliation (not required)
                ],
                'description'       => 'Funding reconciliation status of the bank statement line. This status only reflects reconciliation with Fundings and does not represent accounting entry matching.',
                'default'           => 'pending'
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
                        'onafter'     => 'onafterPost',
                        'status'      => 'posted'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return [
            'attempt_reconcile' => [
                'description'   => 'Attempts to find a suitable Funding and, if so, creates a Payment to link the line to it.',
                // 'help'          => 'Accepts an arbitrary funding for manual reconcile.',
                'policies'      => [],
                'function'      => 'doAttemptReconcile'
            ],
            'generate_accounting_entry' => [
                'description'   => 'Creates accounting entries according to operation lines.',
                'help'          => 'This is run only when posting (in `onbeforePost`).',
                'policies'      => [],
                'function'      => 'doGenerateAccountingEntry'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_post' => [
                'description' => 'Verifies that the bank statement line is fully reconciled.',
                'function'    => 'policyCanPost'
            ]
        ];
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['date', 'sequence_number', 'description']);
        foreach($self as $id => $bankStatementLine) {
            $result[$id] = date('Y-m-d', $bankStatementLine['date']) . $bankStatementLine['sequence_number'] . $bankStatementLine['description'];
        }
        return $result;
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
     * This method either links the line with a given Funding through a Payment,
     * .
     * If one or several fundings are found, action `reconcile_with_fundings` is called with them.
     */
    protected static function doAttemptReconcile($self, $values) {

        $self->read([
                'status',
                'condo_id',
                'communication',
                'amount',
                'date',
                'account_iban',
                'bank_statement_id' => ['bank_account_id'],
                'accounting_account_id',
                'is_supplier',
                'is_owner',
                'is_transfer',
                'ownership_id'
            ]);

        foreach($self as $id => $bankStatementLine) {
            if($bankStatementLine['status'] !== 'pending') {
                continue;
            }

            // remove any pre-existing pending Payments related to the statement line
            // #memo - payments are validated/posted in method `self::onafterPost`
            Payment::search([
                    ['condo_id', '=', $bankStatementLine['condo_id']],
                    ['bank_statement_line_id', '=', $id],
                    ['status', '=', 'proforma']
                ])
                ->delete(true);

            if(isset($values['funding_ids']) && is_array($values['funding_ids']) && count($values['funding_ids'])) {
                $funding_ids = $values['funding_ids'];
            }
            else {
                $funding_ids = self::computeFundingCandidates($id);
            }

            $candidateFundings = Funding::ids($funding_ids)->read(['id', 'due_date', 'remaining_amount']);
            $remaining_amount = round((float) $bankStatementLine['amount'], 2);

            foreach($candidateFundings as $funding) {
                if(abs($remaining_amount) < 0.01) {
                    break;
                }

                if(abs($funding['remaining_amount']) < 0.01) {
                    continue;
                }

                if(($remaining_amount > 0 && $funding['remaining_amount'] <= 0) || ($remaining_amount < 0 && $funding['remaining_amount'] >= 0)) {
                    continue;
                }

                $allocatable = min(abs($funding['remaining_amount']), abs($remaining_amount));
                $allocated = round(($funding['remaining_amount'] > 0 ? 1 : -1) * $allocatable, 2);

                if(abs($remaining_amount - $allocated) > abs($remaining_amount)) {
                    continue;
                }

                if(abs($allocated) < 0.01) {
                    continue;
                }

                Payment::create([
                        'condo_id'                  => $bankStatementLine['condo_id'],
                        'amount'                    => $allocated,
                        // #memo - communication might not be a payment reference but an arbitrary comment or description
                        'communication'             => $bankStatementLine['communication'],
                        'receipt_date'              => $bankStatementLine['date'],
                        'receipt_bank_account_id'   => $bankStatementLine['bank_statement_id']['bank_account_id'],
                        'payment_origin'            => 'bank',
                        'payment_method'            => 'wire_transfer',
                        'bank_statement_line_id'    => $id,
                        'funding_id'                => $funding['id']
                    ]);

                $remaining_amount = round($remaining_amount - $allocated, 2);
            }

            // if some amount is remaining : an unexpected amount has been received
            if(abs($remaining_amount) > 0.01) {
                // -> create a Funding relating to the BankStatementLine with a due amount of 0.0 EUR
                // -> attach a Payment to it with remaining_amount

                $funding = Funding::create([
                        'condo_id'                  => $bankStatementLine['condo_id'],
                        'description'               => (strlen($bankStatementLine['communication']) > 0) ? $bankStatementLine['communication'] : 'trop payé',
                        'bank_statement_line_id'    => $id,
                        'accounting_account_id'     => $bankStatementLine['accounting_account_id'],
                        'ownership_id'              => $bankStatementLine['ownership_id']?? null,
                        'bank_account_id'           => $bankStatementLine['bank_statement_id']['bank_account_id'],
                        'issue_date'                => $bankStatementLine['date'],
                        'due_date'                  => $bankStatementLine['date'],
                        'due_amount'                => 0.0,
                        'funding_type'              => 'statement_line'
                    ])
                    ->first();

                Payment::create([
                        'condo_id'                  => $bankStatementLine['condo_id'],
                        'amount'                    => $remaining_amount,
                        'communication'             => (strlen($bankStatementLine['communication']) > 0) ? $bankStatementLine['communication'] : 'trop payé',
                        'receipt_date'              => $bankStatementLine['date'],
                        'receipt_bank_account_id'   => $bankStatementLine['bank_statement_id']['bank_account_id'],
                        'payment_origin'            => 'bank',
                        'payment_method'            => 'wire_transfer',
                        'bank_statement_line_id'    => $id,
                        'funding_id'                => $funding['id']
                    ]);

            }

        }

    }

    /**
     * #memo - BankStatementLine objects are considered as accounting documents, but do not generate
     *  Funding objects of their own since the movement is inherent from a Bank operation.
     */
    protected static function doCreateFundings($self) {
    }

    /**
     * Identify candidate fundings for a statement line.
     *
     * Search logic depends on the nature of the selected accounting account.
     */
    private static function computeFundingCandidates($bank_statement_line_id): array {
        $bankStatementLine = self::id($bank_statement_line_id)
            ->read([
                'status',
                'condo_id',
                'communication',
                'amount',
                'date',
                'account_iban',
                'bank_statement_id' => ['bank_account_id'],
                'accounting_account_id',
                'is_supplier',
                'is_owner',
                'is_transfer'
            ])
            ->first();

        if(!isset($bankStatementLine['condo_id']) || !isset($bankStatementLine['accounting_account_id'])) {
            return [];
        }

        if($bankStatementLine['is_supplier']) {
            // first, attempt to find a match with reference AND amount
            $fundings_ids = Funding::search([
                        ['condo_id', '=', $bankStatementLine['condo_id']],
                        ['accounting_account_id', '=', $bankStatementLine['accounting_account_id']],
                        ['status', '<>', 'balanced'],
                        ['is_cancelled', '=', false],
                        ['remaining_amount', '>=', $bankStatementLine['amount']],
                        ['payment_reference', '=', $bankStatementLine['communication']],
                    ], ['sort' => ['issue_date' => 'asc']]
                )
                ->ids();

            if(!count($fundings_ids)) {
                // fallback to remaining amount match only
                $fundings_ids = Funding::search([
                            ['condo_id', '=', $bankStatementLine['condo_id']],
                            ['accounting_account_id', '=', $bankStatementLine['accounting_account_id']],
                            ['status', '<>', 'balanced'],
                            ['is_cancelled', '=', false],
                            ['remaining_amount', '>=', $bankStatementLine['amount']]
                        ], ['sort' => ['issue_date' => 'asc']]
                    )
                    ->read(['id', 'due_date', 'remaining_amount'])
                    ->ids();
            }

            return $fundings_ids;

        }
        elseif($bankStatementLine['is_owner']) {
            return Funding::search([
                        ['condo_id', '=', $bankStatementLine['condo_id']],
                        ['accounting_account_id', '=', $bankStatementLine['accounting_account_id']],
                        ['status', '<>', 'balanced'],
                        ['funding_type', '<>', 'due_balance'],
                        ['is_cancelled', '=', false]
                    ], ['sort' => ['issue_date' => 'asc']]
                )
                ->read(['id', 'due_date', 'remaining_amount'])
                ->ids();
        }
        elseif($bankStatementLine['is_transfer']) {
            return Funding::search([
                        ['condo_id', '=', $bankStatementLine['condo_id']],
                        ['accounting_account_id', '=', $bankStatementLine['accounting_account_id']],
                        ['bank_account_id', '=', $bankStatementLine['bank_statement_id']['bank_account_id']],
                        ['counterpart_bank_account_iban', '=', $bankStatementLine['account_iban']],
                        ['status', '<>', 'balanced'],
                        ['funding_type', '<>', 'due_balance'],
                        ['is_cancelled', '=', false]
                    ], ['sort' => ['issue_date' => 'asc']]
                )
                ->read(['id', 'due_date', 'remaining_amount'])
                ->ids();
        }
        else {
            return Funding::search([
                        ['condo_id', '=', $bankStatementLine['condo_id']],
                        ['accounting_account_id', '=', $bankStatementLine['accounting_account_id']],
                        ['status', '<>', 'balanced'],
                        ['funding_type', '<>', 'due_balance'],
                        ['is_cancelled', '=', false]
                    ],
                    ['sort' => ['issue_date' => 'asc']]
                )
                ->read(['id', 'due_date', 'remaining_amount'])
                ->ids();
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

        return ( abs(abs($sum) - abs($bankStatementLine['amount'])) < 0.01 );
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
            if(!$statementLine['payments_ids'] || $statementLine['payments_ids']->count() <= 0) {
                $result[$id] = false;
                continue;
            }
            $result[$id] = self::computeIsReconciled($id);
        }
        return $result;
    }

    protected static function onbeforePost($self) {
        // #memo - we cannot trust the user : business logic imposes the use of oldest Fundings first
        $self
            // find Fundings if applicable
            ->do('attempt_reconcile')
            ->do('generate_accounting_entry');
    }

    protected static function onafterPost($self) {
        $self->read(['bank_statement_id', 'accounting_entry_id', 'payments_ids']);
        foreach($self as $id => $bankStatementLine) {
            if(!$bankStatementLine['bank_statement_id']) {
                continue;
            }
            AccountingEntry::id($bankStatementLine['accounting_entry_id'])->transition('validate');
            Payment::ids($bankStatementLine['payments_ids'])->transition('post');
            BankStatement::id($bankStatementLine['bank_statement_id'])->do('refresh_status');
        }
    }

    public static function onchange($event, $values, $view) {
        $result = [];


        if(isset($event['communication']) && strlen($event['communication']) > 0) {
            $result['communication'] = trim($event['communication']);
        }

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
        $self->read([
            'condo_id',
            'payments_ids' => ['funding_id'],
            'status',
            'fiscal_year_id',
            'accounting_account_id' => ['is_apportionable', 'is_reconcilable'],
            'is_expense', 'is_income', 'apportionment_id',
            'bank_statement_id' => ['id', 'bank_account_id', 'is_balanced', 'fiscal_year_id']
        ]);
        foreach($self as $id => $bankStatementLine) {
            if($bankStatementLine['status'] !== 'pending') {
                $result[$id] = [
                    'invalid_status' => 'Only non-posted bank statement lines can be posted.'
                ];
                continue;
            }
            if(!$bankStatementLine['accounting_account_id']) {
                $result[$id] = [
                    'missing_accounting_account' => 'Accounting account is mandatory on Bank Statement Line.'
                ];
                continue;
            }

            $bankAccount = CondominiumBankAccount::id($bankStatementLine['bank_statement_id']['bank_account_id'])->read(['condo_id'])->first();

            if(!$bankAccount) {
                $result[$id] = [
                    'missing_bank_account_condominium' => 'No condominium found for Bank Statement IBAN.'
                ];
                continue;
            }

            if($bankAccount['condo_id'] != $bankStatementLine['condo_id']) {
                $result[$id] = [
                    'mismatch_bank_account_with_condominium' => 'Bank Statement IBAN not amongst targeted Condominium bank accounts.'
                ];
                continue;
            }

            foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {
                $funding = Funding::id($payment['funding_id'])->first();
                if(!$funding) {
                    $result[$id] = [
                        'missing_mandatory_funding' => 'Unexpected payment attached to statement line, not linked to any funding.'
                    ];
                    continue 2;
                }
            }

            // parent bank statement must be balanced before being able to post individual lines
            if(!$bankStatementLine['bank_statement_id']['is_balanced']) {
                $result[$id] = [
                    'incomplete_bank_statement' => 'Parent bank statement is not balanced.'
                ];
                continue;
            }
            // Check if: 1) the entry is reconciled 2) there are payments (which fully reconcile the line), or a counterpart accounting account is specified
            if($bankStatementLine['accounting_account_id']['is_reconcilable'] && !self::computeIsReconciled($id)) {
                $result[$id] = [
                    'invalid_reconcile_state' => 'Only reconciled bank statement lines can be posted.'
                ];
                continue;
            }
            // The statement date must be in the same fiscal year (not period)
            if($bankStatementLine['fiscal_year_id'] !== $bankStatementLine['bank_statement_id']['fiscal_year_id'] ) {
                $result[$id] = [
                    'incompatible_fiscal_year' => 'Fiscal year of the line must match parent bank statement fiscal year.'
                ];
                continue;
            }
            if($bankStatementLine['accounting_account_id']['is_apportionable'] && !$bankStatementLine['apportionment_id']) {
                $result[$id] = [
                    'missing_apportionment_id' => "Bank Statement Line ({$id}) not linked to an apportionment key."
                ];
                continue;
            }
        }
        return $result;
    }

    /**
     * Generate and validate accounting entry, based on existing Payments.
     * In the case where the BankStatementLine is considered as the original accounting document, it is responsible for generating the accounting entry,
     * and AccountingEntryLines (records) are linked to it.
     *
     * The entries to be made in the financial journal (BANK) .
     *
     * Attempt auto-match : Retrieve accounting entry lines that could be used for automatic matching.
     *  - start from Payments linked to the statement line
     *  - retrieve related Fundings
     *  - collect accounting entry lines attached to the Funding itself or to its source document
     *
     * If there are payments, accounting entries are generated from them (they always take precedence over the selected account)
     * corollary: when performing a manual match with a funding, a payment must be created (= doReconcileWithFunding)
     * otherwise, entries are created using 'accounting_account_id'
     *
     */
    protected static function doGenerateAccountingEntry($self) {

        $self->read([
                'condo_id',
                'date',
                'amount',
                'communication',
                'matching_id' => ['id', 'accounting_entry_lines_ids' => ['debit', 'credit']],
                'accounting_account_id' => ['is_control_account', 'ownership_id'],
                'bank_statement_id' => ['id', 'bank_account_id'],
                'payments_ids' => ['amount', 'funding_id']
            ]);

        foreach($self as $id => $bankStatementLine) {
            $logs = [];

            $logs[] = "Start accounting entry generation for bank statement line {$id}";

            $bankAccount = CondominiumBankAccount::id($bankStatementLine['bank_statement_id']['bank_account_id'])
                ->read(['accounting_account_id'])
                ->first();
            $logs[] = "Retrieved bank account accounting account {$bankAccount['accounting_account_id']}";

            $journal = Journal::search([
                            ['condo_id', '=', $bankStatementLine['condo_id']],
                            ['journal_type', '=', 'BANK'],
                            ['accounting_account_id', '=', $bankAccount['accounting_account_id']]
                        ])
                        ->first();

            if(!$journal) {
                self::id($id)->update(['logs' => implode("\n", array_merge($logs, ['Missing mandatory BANK journal']))]);
                throw new \Exception('missing_mandatory_journal', EQ_ERROR_INVALID_CONFIG);
            }
            $logs[] = "Retrieved BANK journal id {$journal['id']}";

            // #todo - keep handling manual matching on BankStatementLine ?
            // keep track of the matching that will require a refresh
            $is_fully_matched = false;

            // #memo - we cannot use matching_level here since the accounting entry line from the statement line has not been added yet
            if($bankStatementLine['matching_id']) {
                $logs[] = "Existing matching detected: {$bankStatementLine['matching_id']['id']}";
                if($bankStatementLine['matching_id']['accounting_entry_lines_ids']->count() > 0) {
                    $lines_total = 0.0;
                    foreach($bankStatementLine['matching_id']['accounting_entry_lines_ids'] as $accounting_entry_line_id => $accountingEntryLine) {
                        $line_amount = round($accountingEntryLine['debit'] - $accountingEntryLine['credit'], 2);
                        $lines_total += $line_amount;
                    }
                    $logs[] = "Existing matching total amount: {$lines_total}";
                    if(abs($bankStatementLine['amount'] + $lines_total) < 0.01) {
                        $is_fully_matched = true;
                        $logs[] = "Existing matching is fully compatible";
                    }
                }
            }

            $debit_account_id = $bankAccount['accounting_account_id'];
            // #memo - this account might be a control account
            $credit_account_id = $bankStatementLine['accounting_account_id']['id'];
            $logs[] = "Default debit account {$debit_account_id}";
            $logs[] = "Default credit account {$credit_account_id}";

            $accountingEntry = AccountingEntry::create([
                    'condo_id'               => $bankStatementLine['condo_id'],
                    'entry_date'             => $bankStatementLine['date'],
                    'origin_object_class'    => self::getType(),
                    'origin_object_id'       => $id,
                    'bank_statement_line_id' => $id,
                    'bank_statement_id'      => $bankStatementLine['bank_statement_id']['id'],
                    'journal_id'             => $journal['id'],
                    'description'            => $bankStatementLine['communication']
                ])
                ->first();
            $logs[] = "Created accounting entry {$accountingEntry['id']}";

            // attach accounting entry to the statement line
            self::id($id)->update(['accounting_entry_id' => $accountingEntry['id']]);
            $logs[] = "Attached accounting entry {$accountingEntry['id']} to bank statement line";

            // #todo - if selected account imposes Matching, then BankStatementLine amount MUST be balanced with Payments/Allocations
            if(count($bankStatementLine['payments_ids']) > 0) {
                $logs[] = 'Processing ' . count($bankStatementLine['payments_ids']) . ' payment(s)';
                // create one AccountingEntryLine per Payment
                foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {
                    try {
                        $logs[] = "Processing payment {$payment_id}";

                        if(!$payment['funding_id']) {
                            $logs[] = "WARN - Skipped payment {$payment_id}: missing funding";
                            continue;
                        }

                        $funding = Funding::id($payment['funding_id'])
                            ->read([
                                'name', 'due_amount', 'description', 'funding_type',
                                'accounting_account_id',
                                'accounting_entry_line_id' => ['id', 'account_id'],
                                'misc_operation_id' => ['has_opening_journal']
                            ])
                            ->first();

                        if(!$funding) {
                            $logs[] = "Skipped payment {$payment_id}: funding {$payment['funding_id']} not found";
                            continue;
                        }

                        /*
                        if($funding['funding_type'] === 'misc_operation' && ($funding['misc_operation_id']['has_opening_journal'] ?? false)) {
                            $logs[] = "Skipping funding {$payment['funding_id']} relating to Opening Balance";
                            continue;
                        }
                        */

                        $fundingAccountingEntryLine = $funding['accounting_entry_line_id'] ?? null;
                        $credit_account_id = $funding['accounting_account_id'] ?? null;

                        if($fundingAccountingEntryLine) {
                            $credit_account_id = $fundingAccountingEntryLine['account_id'];
                        }
                        else {
                            // Payment might have been created for a unidentified money movement
                            if($bankStatementLine['accounting_account_id']['is_control_account'] && $bankStatementLine['accounting_account_id']['ownership_id']) {
                                // #memo - always use Ownership control_account for Fundings
                                $ownershipAccount = Account::search([
                                        ['condo_id', '=', $bankStatementLine['condo_id']],
                                        ['ownership_id', '=', $bankStatementLine['accounting_account_id']['ownership_id']],
                                        ['is_control_account', '=', false],
                                        ['operation_assignment', '=', 'co_owners_owner_working_fund']
                                    ])
                                    ->first();
                                if($ownershipAccount) {
                                    $credit_account_id = $ownershipAccount['id'];
                                }
                            }
                        }

                        if(!$credit_account_id) {
                            throw new \Exception('missing_funding_accounting_account', EQ_ERROR_INVALID_PARAM);
                        }

                        $logs[] = "Retrieved funding {$payment['funding_id']} with due amount {$funding['due_amount']}";

                        $description = $bankStatementLine['communication'];

                        if(strlen($description) <= 0) {
                            $description = $funding['description'];
                            if(strlen($description) <= 0) {
                                $description = $funding['name'];
                            }
                        }

                        $payment_amount = round((float) $payment['amount'], 2);

                        // debit line
                        AccountingEntryLine::create([
                                'condo_id'               => $bankStatementLine['condo_id'],
                                'account_id'             => $debit_account_id,
                                'debit'                  => $payment_amount > 0 ? abs($payment_amount) : 0,
                                'credit'                 => $payment_amount < 0 ? abs($payment_amount) : 0,
                                'accounting_entry_id'    => $accountingEntry['id'],
                                'bank_statement_line_id' => $id,
                                'description'            => $description
                            ]);
                        $logs[] = "Created debit line for payment {$payment_id} with amount {$payment_amount}";

                        // credit line
                        $creditAccountingEntryLine = AccountingEntryLine::create([
                                'condo_id'               => $bankStatementLine['condo_id'],
                                'account_id'             => $credit_account_id,
                                'debit'                  => $payment_amount < 0 ? abs($payment_amount) : 0,
                                'credit'                 => $payment_amount > 0 ? abs($payment_amount) : 0,
                                'accounting_entry_id'    => $accountingEntry['id'],
                                'bank_statement_line_id' => $id,
                                'description'            => $description
                            ])
                            ->first();

                        $logs[] = "Created credit line on account {$credit_account_id} for payment {$payment_id}";

                        // Store the created accounting entry ID back to the payment
                        Payment::id($payment_id)->update(['accounting_entry_line_id' => $creditAccountingEntryLine['id']]);
                        $logs[] = "Attached accounting entry line {$creditAccountingEntryLine['id']} to payment {$payment_id}";

                        if($fundingAccountingEntryLine) {
                            AccountingEntryLine::id($creditAccountingEntryLine['id'])
                                ->do('attempt_match_with_line', [
                                    'accounting_entry_line_id' => $fundingAccountingEntryLine['id']
                                ]);
                            $logs[] = "Triggered attempt_match_with_line for payment {$payment_id} against funding entry line {$fundingAccountingEntryLine['id']}";
                        }
                        else {
                            $logs[] = "Skipped attempt_match_with_line for payment {$payment_id}: funding has no source accounting entry line";
                        }
                    }
                    catch(\Exception $e) {
                        $logs[] = "ERROR on payment {$payment_id}: {$e->getMessage()}";
                        trigger_error("APP::doGenerateAccountingEntry: Error while creating accounting entries for Bank Statement Line #{$id} : " . $e->getMessage(), EQ_REPORT_ERROR);
                    }

                }
            }
            else {
                // single AccountingEntryLine
                try {
                    $logs[] = 'No payment found, generating stand-alone accounting entry lines';

                    if($bankStatementLine['accounting_account_id']['is_control_account'] && $bankStatementLine['accounting_account_id']['ownership_id']) {
                        $ownershipWorkingFundAccount = Account::search([
                                ['condo_id', '=', $bankStatementLine['condo_id']],
                                ['parent_account_id', '=', $bankStatementLine['accounting_account_id']['id']],
                                ['is_control_account', '=', false],
                                ['ownership_id', '=', $bankStatementLine['accounting_account_id']['ownership_id']],
                                ['operation_assignment', '=', 'co_owners_owner_working_fund']
                            ])
                            ->first();
                        if(!$ownershipWorkingFundAccount) {
                            throw new \Exception('missing_ownership_working_fund_account', EQ_ERROR_INVALID_CONFIG);
                        }
                        $credit_account_id = $ownershipWorkingFundAccount['id'];
                        $logs[] = "Retrieved credit account from ownership subaccount ({$credit_account_id})";
                    }

                    $amount = round($bankStatementLine['amount'], 2);

                    // debit line
                    AccountingEntryLine::create([
                            'condo_id'               => $bankStatementLine['condo_id'],
                            'account_id'             => $debit_account_id,
                            'debit'                  => $amount > 0 ? abs($amount) : 0,
                            'credit'                 => $amount < 0 ? abs($amount) : 0,
                            'accounting_entry_id'    => $accountingEntry['id'],
                            'bank_statement_line_id' => $id,
                            'description'            => $bankStatementLine['communication']
                        ]);
                    $logs[] = "Created stand-alone debit line with amount {$amount}";

                    // credit line
                    AccountingEntryLine::create([
                            'condo_id'               => $bankStatementLine['condo_id'],
                            'account_id'             => $credit_account_id,
                            'debit'                  => $amount < 0 ? abs($amount) : 0,
                            'credit'                 => $amount > 0 ? abs($amount) : 0,
                            'accounting_entry_id'    => $accountingEntry['id'],
                            'bank_statement_line_id' => $id,
                            'description'            => $bankStatementLine['communication']
                        ]);
                    $logs[] = "Created stand-alone credit line on account {$credit_account_id}";

                    // #todo - keep handling manual matching on BankStatementLine ?
                    if(!$is_fully_matched) {
                        // attempt to match the entry with an existing match (will cascade to accounting entry lines)
                        AccountingEntry::id($accountingEntry['id'])->do('attempt_match');
                        $logs[] = "Triggered attempt_match on accounting entry {$accountingEntry['id']}";
                    }
                }
                catch(\Exception $e) {
                    $logs[] = "ERROR on stand-alone accounting entry generation: {$e->getMessage()}";
                    trigger_error("APP::doGenerateAccountingEntry: Error while creating accounting entries for Bank Statement Line #{$id} : " . $e->getMessage(), EQ_REPORT_ERROR);
                }

            }

            self::id($id)->update(['logs' => implode("\n", $logs)]);
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


    protected static function defaultDate($values) {
        $result = null;
        if(isset($values['bank_statement_id'])) {
            $bankStatement = BankStatement::id($values['bank_statement_id'])->read(['opening_date'])->first();
            if($bankStatement) {
                $result = $bankStatement['opening_date'];
            }
        }
        return $result;
    }

    protected static function calcFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'date']);
        foreach($self as $id => $bankStatementLine) {
            $fiscalYear = FiscalYear::search([ ['condo_id', '=', $bankStatementLine['condo_id']], ['date_from', '<=', $bankStatementLine['date']], ['date_to', '>=', $bankStatementLine['date']] ])->first();
            if($fiscalYear) {
                $result[$id] = $fiscalYear['id'];
            }
        }
        return $result;
    }

    protected static function onupdateAccountingAccountId($self) {
        $self->read(['accounting_account_id']);
        foreach($self as $id => $bankStatementLine) {
            $account = Account::id($bankStatementLine['accounting_account_id'])->read(['apportionment_id', 'tenant_share', 'owner_share', 'vat_rate'])->first();
            if($account) {
                self::id($id)->update([
                    'apportionment_id'  => $account['apportionment_id'],
                    'tenant_share'      => $account['tenant_share'],
                    'owner_share'       => $account['owner_share'],
                    'vat_rate'          => $account['vat_rate'],
                ]);
            }
        }
    }

    /**
     * Attempt to retrieve accounting_account_id based on account_iban.
     */
    protected static function onupdateAccountIban($self) {
        $self->read(['condo_id', 'account_iban']);
        foreach($self as $id => $bankStatementLine) {
            if(!isset($bankStatementLine['condo_id'], $bankStatementLine['account_iban'])) {
                continue;
            }

            // attempt to retrieve accounting account based on IBAN

            // 1) search amongst ownerships
            $ownerBankAccount = OwnershipBankAccount::search([
                    ['condo_id', '=', $bankStatementLine['condo_id']],
                    ['bank_account_iban', '=', $bankStatementLine['account_iban']],
                    ['ownership_id', '<>', null]
                ])
                ->read(['ownership_id'])
                ->first();

            // if found, assign the related accounting_account_id
            if($ownerBankAccount) {
                $account = Account::search([
                        ['ownership_id', '=', $ownerBankAccount['ownership_id']],
                        ['is_control_account', '=', true]
                    ])
                    ->first();

                if($account) {
                    self::id($id)->update(['accounting_account_id' => $account['id']]);
                }

                continue;
            }

            // 2) search amongst suppliers
            $supplierBankAccount = SuppliershipBankAccount::search([
                    ['condo_id', '=', $bankStatementLine['condo_id']],
                    ['bank_account_iban', '=', $bankStatementLine['account_iban']],
                    ['suppliership_id', '<>', null]
                ])
                ->read(['suppliership_id'])
                ->first();

            if($supplierBankAccount) {
                $account = Account::search([
                        ['condo_id', '=', $bankStatementLine['condo_id']],
                        ['suppliership_id', '=', $supplierBankAccount['suppliership_id']]
                    ])
                    ->first();

                if($account) {
                    self::id($id)->update(['accounting_account_id' => $account['id']]);
                }

                continue;
            }

            // 3) search amongst condominiums
            $condominiumBankAccount = CondominiumBankAccount::search([
                    ['condo_id', '=', $bankStatementLine['condo_id']],
                    ['object_class', '=', 'finance\bank\CondominiumBankAccount'],
                    ['bank_account_type', 'in', ['bank_current', 'bank_savings']],
                    ['bank_account_iban', '=', $bankStatementLine['account_iban']]
                ])
                ->first();

            if($condominiumBankAccount) {
                $account = Account::search([
                        ['condo_id', '=', $bankStatementLine['condo_id']],
                        ['operation_assignment', '=', 'bank_transfer']
                    ])
                    ->first();

                if($account) {
                    self::id($id)->update(['accounting_account_id' => $account['id']]);
                }

                continue;
            }
        }
    }
}
