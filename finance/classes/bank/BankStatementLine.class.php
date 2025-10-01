<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use equal\orm\Model;
use finance\accounting\Account;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\accounting\MiscOperation;
use finance\accounting\MiscOperationLine;
use realestate\sale\pay\Funding;
use realestate\sale\pay\Payment;
use finance\bank\BankStatement;
use finance\bank\CondominiumBankAccount;
use realestate\finance\accounting\MoneyTransfer;
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
                'domain'            => [['condo_id', '=', 'object.condo_id'],['condo_id', '<>', null]]
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date of the transaction as provided by the bank.',
                'required'          => true,
                'default'           => 'defaultDate',
                'dependents'        => ['fiscal_year_id']
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
                'dependents'        => ['accounting_account_code', 'is_transfer', 'is_expense', 'is_income', 'is_supplier', 'is_owner', 'ownership_id', 'suppliership_id'],
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

            'matching_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Matching',
                'description'       => 'Matching (lettering) to which the accounting entry is linked, if any.',
                'help'              => "This value can only be set manually when doing reconciliation with a non balanced matching",
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
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doAttemptReconcile'
            ],
            // arbitrary reconcile with a given funding (auto or manual)
            'reconcile_with_funding' => [
                'description'   => 'Creates Funding and related Payment that use the Bank Statement line itself as accounting document.',
                'policies'      => [],
                'function'      => 'doReconcileWithFunding'
            ],
            'generate_accounting_entry' => [
                'description'   => 'Creates accounting entries according to operation lines.',
                'help'          => 'This is run only when posting (in `onbeforePost`).',
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
     * Reconcile the line by creating a Payment and linking the line to related funding.
     *
     */
    protected static function doReconcileWithFunding($self, $values) {
        if(!isset($values['funding_id'])) {
            throw new \Exception('missing_mandatory_funding_id', EQ_ERROR_INVALID_PARAM);
        }

        $funding = Funding::id($values['funding_id'])
            ->read(['accounting_account_id'])
            ->first();

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

            self::id($id)->update(['accounting_account_id' => $funding['accounting_account_id']]);
        }
    }

    /**
     * This method either links the line with a Funding through a Payment,
     * or generates an orphan operation referencing current line as accounting document.
     * If a funding is fund, action `reconcile_with_funding` is called with it.
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
            // user must manually remove them in order to be able to retry reconciliation
            if($bankStatementLine['payments_ids']->count() > 0) {
                continue;
            }

            // 1) attempt to reconcile with a matching Funding
            $matching_funding_id = self::computeMatchingFunding($bankStatementLine['amount'], $bankStatementLine['communication'], $bankStatementLine['bank_statement_id']['bank_account_iban'], $bankStatementLine['account_iban']);
            if($matching_funding_id) {
                self::id($id)
                    ->do('reconcile_with_funding', ['funding_id' => $matching_funding_id])
                    ->update(['is_reconciled' => null]);
            }
            // 2) attempt to reconcile with a matching Funding
            /*
            // #todo - nothing to try here : only at posting, if a Matching can be retrieved
            elseif($bankStatementLine['accounting_account_id']) {
                self::id($id)->do('generate_orphan_operation');
            }
            */
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
        $self->do('generate_accounting_entry');
    }

    protected static function onafterPost($self) {
        $self->read(['bank_statement_id', 'payments_ids']);
        foreach($self as $id => $bankStatementLine) {
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
        $self->read(['status', 'fiscal_year_id', 'accounting_account_id', 'bank_statement_id' => ['is_balanced', 'fiscal_year']]);
        foreach($self as $id => $bankStatementLine) {
            if($bankStatementLine['status'] !== 'pending') {
                $result[$id] = [
                    'invalid_status' => 'Only non-posted bank statement lines can be posted.'
                ];
                continue;
            }
            // parent bank statement must be balanced before being able to post individual lines
            if(!$bankStatementLine['bank_statement_id']['is_balanced']) {
                $result[$id] = [
                    'incomplete_bank_statement' => 'Parent bank statement is not balanced.'
                ];
                continue;
            }
            // Check if: 1) the entry is reconciled 2) there are payments (which fully reconcile the line), or a counterpart accounting account is specified
            if(!self::computeIsReconciled($id) && !$bankStatementLine['accounting_account_id']) {
                $result[$id] = [
                    'invalid_reconcile_state' => 'Only reconciled bank statement lines can be posted.'
                ];
                continue;
            }
            // The statement date must be in the same fiscal year (not period)
            if($bankStatementLine['fiscal_year_id'] = $bankStatementLine['bank_statement_id']['fiscal_year_id'] ) {
                $result[$id] = [
                    'incompatible_fiscal_year' => 'Fiscal year of the line must match parent bank statement fiscal year.'
                ];
                continue;
            }
        }
        return $result;
    }

    /**
     *
     */
    protected static function policyCanGenerateAccountingEntry($self) {
        $result = [];
        $self->read([
                'condo_id',
                'status',
                'accounting_account_id',
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

            if($bankStatementLine['payments_ids']->count() <= 0 && !$bankStatementLine['accounting_account_id']) {
                $result[$id] = [
                    'invalid_status' => 'Bank statement without payment nor accounting account cannot be posted.'
                ];
                continue;
            }

            foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {
                $funding = Funding::id($payment['funding_id'])->first();
                if(!$funding) {
                    $result[$id] = [
                        'missing_mandatory_funding' => 'Unexpected statement line not linked to any funding.'
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

            if($bankAccount['condo_id'] != $bankStatementLine['condo_id']) {
                $result[$id] = [
                    'mismatch_bank_account_with_condominium' => 'Bank Statement IBAN not amongst targeted Condominium bank accounts.'
                ];
                continue;
            }

        }
        return $result;
    }

    // on ne doit pas générer une opération mais essayer de rattacher ce qui peut l'être 
    protected static function policyCanGenerateOrphanOperation($self) {
        $result = [];


        // si les écritures ne sont pas lettrées : pas de payment pour la ligne
        // on prend toutes les écritures de la ligne, et on cherche un matching 
        // retrouver un matching dont le solde correspond au montant de la ligne

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
     * Generate and validate accounting entry, based on existing Payments.
     * In the case where the BankStatementLine is considered as the original accounting document, it is responsible for generating the accounting entry,
     * and AccountingEntryLines (records) are linked to it.
     *
     * The entries to be made in the financial journal (BANK) .
     *
     *
     * s'il y a des paiements, on génère les accounting entries à partir d'eux (ils prévalent toujours sur le compte sélectionné)
     * corollaire : quand on fait un match manuel avec un funding, il faut créer un paiement (= doReconcileWithFunding)
     * sinon, on créée des écritures en utilisant  'accounting_account_id'
     *
     */
    protected static function doGenerateAccountingEntry($self) {

        $self->read([
                'condo_id',
                'date',
                'amount',
                'communication',
                'matching_id',
                'accounting_account_id',
                'bank_statement_id' => ['bank_account_id'],
                'payments_ids' => [
                    'amount',
                    'receipt_date',
                    'fiscal_year_id',
                    'fiscal_period_id',
                    'funding_id' => [
                        'name',
                        'description',
                        'due_amount',
                        'bank_account_id',
                        'ownership_id',
                        'suppliership_id',
                        'accounting_account_id'
                    ]
                ]
            ]);

        foreach($self as $id => $bankStatementLine) {
            $bankAccount = CondominiumBankAccount::id($bankStatementLine['bank_statement_id']['bank_account_id'])->read(['accounting_account_id'])->first();

            $journal = Journal::search([
                            ['condo_id', '=', $bankStatementLine['condo_id']],
                            ['journal_type', '=', 'BANK'],
                            ['accounting_account_id', '=', $bankAccount['accounting_account_id']]
                        ])
                        ->first();

            if(!$journal) {
                throw new \Exception('missing_mandatory_journal', EQ_ERROR_INVALID_CONFIG);
            }

            if(count($bankStatementLine['payments_ids']) > 0) {
                foreach($bankStatementLine['payments_ids'] as $payment_id => $payment) {

                    try {

                        $funding = $payment['funding_id'];

                        $debit_account_id = $bankAccount['accounting_account_id'];
                        $credit_account_id = $funding['accounting_account_id'];

                        $amount = round($payment['amount'], 2);

                        $description = $bankStatementLine['communication'];

                        if(strlen($description) <= 0) {
                            $description = $funding['description'];
                            if(strlen($description) <= 0) {
                                $description = $funding['name'];
                            }
                        }

                        $accountingEntry = AccountingEntry::create([
                                'condo_id'              => $bankStatementLine['condo_id'],
                                'entry_date'            => $payment['receipt_date'],
                                'origin_object_class'   => self::getType(),
                                'origin_object_id'      => $id,
                                'journal_id'            => $journal['id'],
                                'description'           => $bankStatementLine['communication']
                            ])
                            ->first();

                        // debit line
                        AccountingEntryLine::create([
                                'condo_id'               => $bankStatementLine['condo_id'],
                                'account_id'             => $debit_account_id,
                                'debit'                  => $amount > 0 ? abs($amount) : 0,
                                'credit'                 => $amount < 0 ? abs($amount) : 0,
                                'funding_id'             => $funding['id'],
                                'accounting_entry_id'    => $accountingEntry['id'],
                                'bank_statement_line_id' => $id,
                                'description'            => $description
                            ]);

                        // credit line
                        AccountingEntryLine::create([
                                'condo_id'               => $bankStatementLine['condo_id'],
                                'account_id'             => $credit_account_id,
                                'debit'                  => $amount < 0 ? abs($amount) : 0,
                                'credit'                 => $amount > 0 ? abs($amount) : 0,
                                'funding_id'             => $funding['id'],
                                'accounting_entry_id'    => $accountingEntry['id'],
                                'bank_statement_line_id' => $id,
                                'description'            => $description
                            ]);

                        // instant validation of the created accounting entry
                        AccountingEntry::id($accountingEntry['id'])->transition('validate');

                        // Store the created accounting entry ID back to the payment
                        Payment::id($payment_id)->update(['accounting_entry_id' => $accountingEntry['id']]);

                    }
                    catch(\Exception $e) {
                        trigger_error("APP::doGenerateAccountingEntry: Error while creating accounting entries for Bank Statement Line #{$id} : " . $e->getMessage(), EQ_REPORT_ERROR);
                    }

                }
            }
            else {

                try {

                    $debit_account_id = $bankAccount['accounting_account_id'];
                    $credit_account_id = $bankStatementLine['accounting_account_id'];

                    $amount = round($bankStatementLine['amount'], 2);

                    $accountingEntry = AccountingEntry::create([
                            'condo_id'              => $bankStatementLine['condo_id'],
                            'entry_date'            => $bankStatementLine['date'],
                            'origin_object_class'   => self::getType(),
                            'origin_object_id'      => $id,
                            'journal_id'            => $journal['id'],
                            'description'           => $bankStatementLine['communication']
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
                            'description'            => $bankStatementLine['communication']
                        ]);

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


                    AccountingEntry::id($accountingEntry['id'])
                        // instant validation of the created accounting entry
                        ->transition('validate');


                    if($bankStatementLine['matching_id']) {
                        // arbitrary match entry with given (existing) match
                        AccountingEntry::id($accountingEntry['id'])
                            ->do('match_with_matching', ['matching_id' => $bankStatementLine['matching_id']]);
                    }
                    else {
                        // attempt to match the entry with an existing match (will cascade to accounting entry lines)
                        AccountingEntry::id($accountingEntry['id'])
                            ->do('attempt_match');
                    }
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


}