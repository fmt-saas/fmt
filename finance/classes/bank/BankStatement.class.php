<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace finance\bank;

use documents\Document;
use documents\DocumentType;
use documents\processing\DocumentProcess;
use equal\orm\Model;
use finance\accounting\FiscalYear;
use identity\User;
use realestate\property\Condominium;

class BankStatement extends Model {

    public static function getName() {
        return 'Bank statement';
    }

    public static function getDescription() {
        return 'A bank statement summarizes a bank account financial transactions over a period.'
            .' It includes details of deposits, withdrawals, fees, and the account balance.';
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the bank statement refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'dependents'        => ['name']
                //'readonly'          => true
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'description'       => 'The bank account the statement refers to.',
                'help'              => 'This field is set automatically upon update of the `bank_account_iban` field.',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null],
                    ['object_class', '=', 'finance\bank\CondominiumBankAccount']
                ],
                'onupdate'          => 'onupdateBankAccountId',
                'dependents'        => ['name']
            ],

            'bank_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\bank\Bank',
                'description'       => 'The Bank the account is part of.',
                'help'              => "This is equivalent to supplier_id since Bank inherit from Supplier.",
                'store'             => false,
                'relation'          => ['bank_account_id' => 'bank_id']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of bank statement.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date at which the statement was received.',
                'help'              => "This is for information only and might not be accurate with the actual date/time at which the statement was generated.
                    By convention all banks release at maximum 1 statement per day, so this date is always at midnight (00:00:00) of the given day.",
                'readonly'          => true,
                'dependents'        => ['name']
            ],

            'statement_number' => [
                'type'              => 'string',
                'usage'             => 'text/plain:3',
                'description'       => 'Arbitrary number of the statement, provided by the bank.',
                'help'              => 'This field can be left unknown for manually encoded statements. By convention, in Belgium only 3 digits are used (due to CODA structure).'
            ],

            'statement_currency' => [
                'type'              => 'string',
                'description'       => 'Currency of the statement.',
                'default'           => 'EUR'
            ],

            'opening_date' => [
                'type'              => 'date',
                'description'       => 'First date the statement refers to.',
                'help'              => 'This date is used to associate the statement with specific fiscal year and fiscal period.',
                'required'          => true,
                'dependents'        => ['fiscal_year_id', 'fiscal_period_id']
            ],

            'closing_date' => [
                'type'              => 'date',
                'description'       => 'Last date the statement refers to.',
                'required'          => true,
                'dependents'        => ['fiscal_year_id', 'fiscal_period_id']
            ],

            'opening_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Account balance before the transactions.',
                'required'          => true,
                'default'           => 0.0,
                'dependents'        => ['name']
            ],

            'closing_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Account balance after the transactions.',
                'required'          => true,
                'default'           => 0.0,
                'dependents'        => ['name']
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban:34',
                'description'       => 'IBAN representation of the account number.',
                'onupdate'          => 'onupdateBankAccountIban'
            ],

            'bank_account_bic' => [
                'type'              => 'string',
                'description'       => 'Bank Identification Code of the account.'
            ],

            'bank_account_suffix' => [
                'type'              => 'string',
                'description'       => 'Proprietary or extended account identifier (e.g. ING sub-account, not SEPA-valid).',
                // #memo - so far this only applies to ING bank
                'domain'            => ['bank_account_bic', '=', 'BBRUBEBB']
            ],

            'statement_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'foreign_field'     => 'bank_statement_id',
                'description'       => 'The lines that are assigned to the statement.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'document_process_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\processing\DocumentProcess',
                'description'       => 'Document Process the statement originates from, if any.',
                'help'              => 'This field is optional and is used for the document digestor processing.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Document the statement originates from.',
                'help'              => 'This document is always present. For imported statements (is_source=true), it is the uploaded document (possibly split for holding a single statement). For manual encoding, it is automatically generated based on the Bank Statement schema.'
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the statement relates to.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'help'              => "Fiscal Year is automatically assigned based on opening_date.",
                'function'          => 'calcFiscalYearId',
                'store'             => true,
                'instant'           => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the statement relates to.",
                'help'              => "Period is automatically assigned based on opening_date.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['fiscal_year_id', '=', 'object.fiscal_year_id']],
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true
            ],

            'is_reconciled' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'A statement is reconciled if all its lines are reconciled.',
                'function'          => 'calcIsReconciled',
                'store'             => true,
                'instant'           => true
            ],

            'is_balanced' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'A statement is reconciled if all its lines are reconciled.',
                'function'          => 'calcIsBalanced',
                'store'             => false
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'bank_statement_line_id',
                'description'       => 'Accounting entries linked to the bank statement line.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'posted',
                    'cancelled'
                ],
                'default'           => 'pending',
                'description'       => 'Status of the statement (depending on lines).'
            ],

            // #memo - some actions of this entity rely on status from DocumentProcessing
            'document_process_status' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Current status of the Document Processing.',
                'help'              => "This value is used in addition to the status, in order to check allowed actions.",
                'selection'         => [
                    'created',
                    'assigned',
                    'completed',
                    'validated',
                    'integrated',
                    'cancelled'
                ],
                'relation'          => ['document_process_id' => 'status'],
                'store'             => true,
                'readonly'          => true
            ],

            'assigned_employee_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'hr\employee\Employee',
                'description'       => 'Employee currently in charge of the processing.',
                'help'              => 'Assigned employee can evolve over time, and might depend on Role.',
                'relation'          => ['document_process_id' => 'assigned_employee_id'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'alert' => [
                'type'              => 'computed',
                'usage'             => 'icon',
                'result_type'       => 'string',
                'description'       => 'Alert flag for the invoice.',
                'help'              => "Indicates if there is an issue with the invoice that needs attention, by providing an icon: info, warn, major, error.",
                'function'          => 'calcAlert',
                'onrevert'          => 'onrevertAlert',
                'store'             => true
            ]

        ];
    }

    public static function getActions() {
        return [
            'attempt_reconcile' => [
                'description'   => 'Attempt to reconcile the statement and its lines, and invoke the creation of subsequent accounting entries.',
                'help'          => 'This action triggers a reconcile attempt on all statement lines. It is immutable and can be called multiple times.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doAttemptReconcile'
            ],
            'update_document_json' => [
                'description'   => 'Update the document data JSON with the newly provided data.',
                'help'          => 'This action is called when a manual change have been made that implies a sync with the descriptor of the linked document.',
                'policies'      => [],
                'function'      => 'doUpdateDocumentJson'
            ],
            'refresh_status' => [
                'description'   => 'Update status according to current status of the statement lines.',
                'policies'      => [],
                'function'      => 'doRefreshStatus'
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Bank Statement being created.',
                'icon' => 'edit',
                'transitions' => [
                    'post' => [
                        'description' => 'Post bank statement statement to the accounting system.',
                        'help'        => 'In order to be posted, Bank Statement must be balanced but not necessarily reconciled.',
                        'policies'    => [
                            'is_balanced', 'can_post'
                        ],
                        'onafter'     => 'onafterPost',
                        'status'      => 'posted'
                    ]
                ],
            ],
            'posted' => [
                'description' => 'The Bank Statement is reconciled and all its line have been posted to the accounting system.',
                'icon' => 'done',
                'transitions' => [
                    'cancel' => [
                        // #todo
                        'description' => '',
                        'status' => 'cancelled',
                    ]
                ],
            ],
        ];
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'is_balanced' => [
                'description' => 'Checks that delta between opening and closing amounts matches the sum of the lines amounts.',
                'function'    => 'policyIsBalanced'
            ],
            'is_reconciled' => [
                'description' => 'Verifies that all statement lines have been processed.',
                'function'    => 'policyIsReconciled'
            ],
            'can_post' => [
                'function'    => 'policyCanPost'
            ]
        ]);
    }

    protected static function policyCanPost($self) {
        $result = [];
        $self->read([
                'condo_id', 'bank_account_id', 'statement_number', 'opening_date', 'opening_balance', 'closing_balance',
                'statement_lines_ids' => ['id']
            ]);
        // #todo - check iban and bic consistency (should have been done before)
        foreach($self as $id => $bankStatement) {


            if(!$bankStatement['condo_id']) {
                $result[$id] = [
                    'missing_condo_id' => "Bank Statement ({$id}) is not linked to a condominium."
                ];
                continue;
            }

            if(!$bankStatement['bank_account_id']) {
                $result[$id] = [
                    'missing_bank_account_id' => "Bank Statement ({$id}) is not linked to a Bank Account."
                ];
                continue;
            }

            try {
                $bankStatement['statement_lines_ids']->assert('can_post');
            }
            catch(\Exception $e) {
                $result[$id] = [
                    'non_postable_bank_statement_line' => "At least one line for Bank Statement ({$id}) cannot be posted: " . $e->getMessage()
                ];
                continue;
            }

            $statement_year = date('Y', $bankStatement['opening_date']);
            $year_start = strtotime("{$statement_year}-01-01");

            $previousBankStatement = BankStatement::search([
                    ['bank_account_id', '=', $bankStatement['bank_account_id']],
                    ['id', '<>', $id],
                    ['closing_date', '<', $bankStatement['opening_date']],
                    ['closing_date', '>=', $year_start],
                    ['statement_number', '=', sprintf("%03d", intval($bankStatement['statement_number']) - 1)]
                ], ['sort' => ['date' => 'desc']])
                ->read(['statement_number', 'opening_balance', 'closing_balance'])
                ->first();

            if($previousBankStatement) {
                $previous_number = intval($previousBankStatement['statement_number']);
                $statement_number = intval($bankStatement['statement_number']);
                if($statement_number != ($previous_number + 1)) {
                    $result[$id] = [
                        'statement_number_mismatch' => "Statement number ($statement_number) does not follow the previous statement of the account (previous = $previous_number)."
                    ];
                    continue;
                }

                if( $previousBankStatement['closing_balance'] != $bankStatement['opening_balance']) {
                    $result[$id] = [
                        'balance_mismatch' => "Opening balance does not match closing balance of previous statement."
                    ];
                    continue;
                }
            }

        }
        return $result;
    }

    protected static function calcAlert($self, $orm) {
        $result = [];
        foreach($self as $id => $bankStatement) {
            $messages_ids = $orm->search('core\alert\Message',[ ['object_class', '=', 'finance\bank\BankStatement'], ['object_id', '=', $id]]);
            if($messages_ids > 0 && count($messages_ids)) {
                $max_alert = 0;
                $map_alert = array_flip([
                    'notice',           // weight = 1, might lead to a warning
                    'warning',          // weight = 2, might be important, might require an action
                    'important',        // weight = 3, requires an action
                    'error'             // weight = 4, requires immediate action
                ]);
                $messages = $orm->read(\core\alert\Message::getType(), $messages_ids, ['severity']);
                foreach($messages as $mid => $message){
                    $weight = $map_alert[$message['severity']];
                    if($weight > $max_alert) {
                        $max_alert = $weight;
                    }
                }
                switch($max_alert) {
                    case 0:
                        $result[$id] = 'info';
                        break;
                    case 1:
                        $result[$id] = 'warn';
                        break;
                    case 2:
                        $result[$id] = 'major';
                        break;
                    case 3:
                    default:
                        $result[$id] = 'error';
                        break;
                }
            }
            else {
                $result[$id] = 'success';
            }
        }
        return $result;
    }

    protected static function calcFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'opening_date']);
        foreach($self as $id => $bankStatement) {
            $fiscalYear = FiscalYear::search([ ['condo_id', '=', $bankStatement['condo_id']], ['date_from', '<=', $bankStatement['opening_date']], ['date_to', '>=', $bankStatement['opening_date']] ])->first();
            if($fiscalYear) {
                $result[$id] = $fiscalYear['id'];
            }
        }
        return $result;
    }

    protected static function doRefreshStatus($self) {
        $self->read(['statement_lines_ids' => ['status']]);
        foreach($self as $id => $bankStatement) {
            foreach($bankStatement['statement_lines_ids'] as $bank_statement_line_id => $bankStatementLine) {
                if($bankStatementLine['status'] === 'pending') {
                    continue 2;
                }
            }
            self::id($id)->update(['status' => 'posted']);
        }
    }

    protected static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['opening_date', 'fiscal_year_id' => ['fiscal_periods_ids' => ['date_from', 'date_to']]]);
        foreach($self as $id => $bankStatement) {
            foreach($bankStatement['fiscal_year_id']['fiscal_periods_ids'] ?? [] as $period_id => $period) {
                if($bankStatement['opening_date'] >= $period['date_from'] && $bankStatement['opening_date'] <= $period['date_to']) {
                    $result[$id] = $period_id;
                    break;
                }
            }
        }
        return $result;
    }

    protected static function onrevertAlert($self) {
        $self->read(['document_process_id']);
        foreach($self as $id => $bankStatement) {
            if(!$bankStatement['document_process_id']) {
                continue;
            }
            DocumentProcess::id($bankStatement['document_process_id'])
                ->update(['alert' => null]);
        }
    }

    protected static function onupdateBankAccountIban($self) {
        $self->read(['bank_account_iban', 'bank_account_suffix', 'condo_id']);
        foreach($self as $id => $bankStatement) {
            $domain = [
                ['bank_account_iban', '=', $bankStatement['bank_account_iban']],
                ['validated', '=', true]
            ];
            if($bankStatement['bank_account_suffix']) {
                $domain[] = ['bank_account_suffix', '=', $bankStatement['bank_account_suffix']];
            }
            $bankAccount = CondominiumBankAccount::search($domain)
                ->read(['condo_id'])
                ->first();
            if($bankAccount) {
                self::id($id)->update(['bank_account_id' => $bankAccount['id']]);
            }
        }
    }

    protected static function onupdateBankAccountId($self) {
        $self->read(['bank_account_id' => ['bank_account_iban', 'bank_account_suffix']]);
        foreach($self as $id => $bankStatement) {
            if($bankStatement['bank_account_id']) {
                $values = ['bank_account_iban' => $bankStatement['bank_account_id']['bank_account_iban']];
                if($bankStatement['bank_account_id']['bank_account_suffix']) {
                    $values['bank_account_suffix'] = $bankStatement['bank_account_id']['bank_account_suffix'];
                }
                self::id($id)->update($values);
            }
        }
    }


    protected static function onafterPost($self) {
        $self->read(['document_process_id', 'statement_lines_ids' => ['status']]);
        foreach($self as $id => $bankStatement) {

            try {
                foreach($bankStatement['statement_lines_ids'] as $bank_statement_line_id => $bankStatementLine) {
                    // relay post to BankStatementLines
                    // #memo - this triggers a cascade event `post` on Payments, and `refresh_status` on Fundings
                    if($bankStatementLine['status'] === 'pending') {
                        BankStatementLine::id($bank_statement_line_id)->transition('post');
                    }
                }

            }
            catch(\Exception $e) {
                // ignore already posted lines
                trigger_error("APP::BankStatement::onafterPost - Failed to post BankStatementLine: {$e->getMessage()}", EQ_REPORT_WARNING);
            }

            if($bankStatement['document_process_id']) {
                DocumentProcess::id($bankStatement['document_process_id'])
                    // bypass all stages
                    ->update(['status' => 'validated'])
                    // mark DocumentProcess as integrated
                    ->transition('integrate');
            }
        }
    }

    protected static function doAttemptReconcile($self) {
        $self->read(['statement_lines_ids' => ['status']]);
        foreach($self as $id => $bankStatement) {
            try {
                // attempt to reconcile lines
                $bankStatement['statement_lines_ids']->do('attempt_reconcile');
                self::id($id)->update(['is_reconciled' => null]);
            }
            catch(\Exception $e) {
                // safely ignore errors
            }
        }
    }

    /**
     * Sync back manually encoded values to the JSON structure of the linked document, if any (`urn:fmt:json-schema:finance:bank-statement`).
     *
     */
    protected static function doUpdateDocumentJson($self) {
        /*
        * [
        *   {
        *     "account_iban": "BE71 0961 2345 6789",                // IBAN of the account
        *     "statement_number": "0000123456",                     // Unique statement identifier
        *     "opening_balance": 1000.00,                           // Balance at the beginning of the statement period
        *     "opening_date": "2024-05-01",                         // Date when the statement period starts
        *     "closing_balance": 1200.00,                           // Balance at the end of the statement period
        *     "closing_date": "2024-05-10",                         // Date when the statement period ends
        *     "statement_currency": "EUR",                          // Currency used for all amounts
        *     "bank_bic": "CREGBEBB",                               // BIC (Bank Identifier Code) of the issuing bank
        *     "account_holder": "FMT solutions",                    // Name of the account holder
        *     "account_type": "current",                            // Type of account (e.g. current, savings)
        *     "transactions": [                                     // List of transactions included in the statement
        *       {
        *         "entry_date": "2024-05-05",                       // Date the transaction was recorded
        *         "value_date": "2024-05-05",                       // Date the transaction becomes effective
        *         "amount": -150.00,                                // Transaction amount (negative = debit, positive = credit)
        *         "currency": "EUR",                                // Currency of the transaction
        *         "transaction_type": "sepa_direct_debit",          // Transaction type (e.g. SEPA direct debit, transfer)
        *         "sequence_number": 123,                           // Internal transaction sequence number
        *         "received_at": "2024-05-05T10:45:00Z",            // Timestamp when the transaction was received (UTC)
        *         "mandate_id": "MANDATE-2023-XYZ",                 // Mandate identifier for SEPA direct debit
        *         "client_reference": "Facture 2024-87",            // Reference provided by the client
        *         "structured_reference": "+++123/4567/89012+++",   // Structured communication reference
        *         "bank_reference": "987654321",                    // Reference provided by the bank
        *         "unstructured_reference": "Paiement facture mai", // Free-text communication
        *         "counterparty_name": "EDF Luminus",               // Name of the transaction counterparty
        *         "counterparty_iban": "BE23 0910 1111 2222",       // IBAN of the transaction counterparty
        *         "counterparty_bic": "GEBA BE BB",                 // BIC of the transaction counterparty
        *         "counterparty_details": "Rue de l'Énergie, Liège",// Additional counterparty details (e.g. address)
        *         "transaction_message": "Paiement automatique"     // Message or label associated with the transaction
        *       }
        *     ]
        *   }
        * ]
        *
        */
        $self->read([
                'document_process_id',
                'statement_lines_ids',
                'bank_account_id',
                'opening_date',
                'closing_date',
                'opening_balance',
                'closing_balance',
                'statement_number',
                'statement_currency'
            ]);

        foreach($self as $id => $bankStatement) {
            if(!$bankStatement['document_process_id']) {
                continue;
            }

            $fields = [];

            if(isset($bankStatement['bank_account_id'])) {
                $bankAccount = BankAccount::id($bankStatement['bank_account_id'])->read([
                        'bank_account_iban',
                        'bank_account_bic',
                        'bank_account_type',
                        'owner_identity_id' => ['legal_name']
                    ])
                    ->first();

                $fields['account_iban'] = $bankAccount['bank_account_iban'];
                $fields['bank_bic'] = $bankAccount['bank_account_bic'];
                $fields['account_type'] = preg_replace('/^bank_/', '', $bankAccount['bank_account_type']);
                $fields['account_holder'] = $bankAccount['owner_identity_id']['legal_name'];
            }

            if(isset($bankStatement['statement_number'])) {
                $fields['statement_number'] = $bankStatement['statement_number'];
            }

            if(isset($bankStatement['opening_date'])) {
                $fields['opening_date'] = date('c', $bankStatement['opening_date']);
            }

            if(isset($bankStatement['closing_date'])) {
                $fields['closing_date'] = date('c', $bankStatement['closing_date']);
            }

            if(isset($bankStatement['opening_balance'])) {
                $fields['opening_balance'] = $bankStatement['opening_balance'];
            }

            if(isset($bankStatement['closing_balance'])) {
                $fields['closing_balance'] = $bankStatement['closing_balance'];
            }

            if(isset($bankStatement['statement_currency'])) {
                $fields['statement_currency'] = $bankStatement['statement_currency'];
            }

            if(count($bankStatement['statement_lines_ids'])) {
                // re-sync all lines
                $bankStatementLines = BankStatementLine::ids($bankStatement['statement_lines_ids'])
                    ->read([
                        'sequence_number',
                        'created',
                        'date',
                        'communication',
                        'communication_type',
                        'amount',
                        'amount_currency',
                        'account_iban',
                        'account_holder',
                        'account_bic',
                        'transaction_type',
                        'mandate_identifier'
                    ]);

                $fields['transactions'] = [];
                foreach($bankStatementLines as $bankStatementLine) {
                    $line = [
                        'sequence_number'        => $bankStatementLine['sequence_number'],
                        'entry_date'             => date('c', $bankStatementLine['created']),
                        'value_date'             => date('c', $bankStatementLine['date']),
                        'amount'                 => $bankStatementLine['amount'],
                        'currency'               => $bankStatementLine['amount_currency'],
                        'transaction_type'       => $bankStatementLine['transaction_type'],
                        'counterparty_name'      => $bankStatementLine['account_holder'],
                        'counterparty_iban'      => $bankStatementLine['account_iban'],
                        'counterparty_bic'       => $bankStatementLine['account_bic'],
                        'structured_reference'   => ($bankStatementLine['communication_type'] !== 'free') ? $bankStatementLine['communication'] : '',
                        'unstructured_reference' => ($bankStatementLine['communication_type'] === 'free') ? $bankStatementLine['communication'] : '',
                        'client_reference'       => '',
                        'bank_reference'         => '',
                        'received_at'            => null,               // Timestamp when the transaction was received (UTC)
                        'counterparty_details'   => '',                 // Additional counterparty details (e.g. address)
                        'transaction_message'    => '',                  // Message or label associated with the transaction
                        // #todo
                        'mandate_id'             => '',                 // Mandate identifier for SEPA direct debit
                    ];

                    $fields['transactions'][] = $line;
                }
            }

            DocumentProcess::id($bankStatement['document_process_id'])->do('update_document_json', $fields);
        }
    }

    /**
     * If no document is attached to the bank statement (handled as an accounting document), a Document and a DocumentProcess are created
     */
    protected static function onafterupdate($self, $auth) {
        $self->read(['state', 'document_id', 'condo_id']);
        $user = User::id($auth->userId())->read(['employee_id'])->first();

        foreach($self as $id => $bankStatement) {
            if($bankStatement['state'] === 'instance' && !$bankStatement['document_id']) {
                $documentType = DocumentType::search(['code', '=', 'bank_statement'])->first();
                $data = \eQual::run('get', 'documents_processing_BankStatement_empty');

                $document = Document::create([
                        'condo_id'          => $bankStatement['condo_id'],
                        'name'              => sprintf("%s %06d", 'extrait bancaire', $id),
                        'bank_statement_id' => $id,
                        'document_type_id'  => $documentType['id'],
                        'document_json'     => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                        'is_origin'         => true,
                        'is_source'         => false
                    ])
                    ->first();

                $documentProcess = DocumentProcess::create([
                        'condo_id'                      => $bankStatement['condo_id'],
                        'name'                          => sprintf("%s %06d", 'extrait bancaire', $id),
                        'description'                   => 'extrait bancaire - encodage manuel',
                        'document_id'                   => $document['id'],
                        'document_bank_statement_id'    => $id,
                        'document_type_id'              => $documentType['id'],
                        'document_origin'               => 'manual',
                        'has_target_object'             => true,
                        'assigned_employee_id'          => $user['employee_id']
                    ])
                    ->first();

                self::id($id)->update([
                        'document_id'           => $document['id'],
                        'document_process_id'   => $documentProcess['id']
                    ]);
            }
        }
        $self->do('update_document_json');
    }

    protected static function calcIsReconciled($self) {
        $result = [];
        $self->read(['opening_balance', 'closing_balance', 'statement_lines_ids' => ['status', 'is_reconciled']]);
        foreach($self as $id => $bankStatement) {
            $result[$id] = ($bankStatement['statement_lines_ids']->count() > 0);
            foreach($bankStatement['statement_lines_ids'] as $bankStatementLine) {
                if(!$bankStatementLine['is_reconciled'] && $bankStatementLine['status'] !== 'posted') {
                    $result[$id] = false;
                    break;
                }
            }
        }
        return $result;
    }

    protected static function calcIsBalanced($self) {
        $result = [];
        $self->read(['statement_lines_ids' => ['amount'], 'opening_balance', 'closing_balance']);
        foreach($self as $id => $statement) {
            $delta = round($statement['closing_balance'], 2) - round($statement['opening_balance'], 2);
            $sum = 0.0;
            foreach($statement['statement_lines_ids'] as $statementLine) {
                $sum += round($statementLine['amount'], 2);
            }
            $result[$id] = (abs($sum - $delta) < 0.01);
        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['condo_id' => 'code', 'bank_account_iban', 'date', 'opening_balance', 'closing_balance']);
        foreach($self as $id => $statement) {
            if(!isset($statement['condo_id'], $statement['bank_account_iban'], $statement['date'], $statement['opening_balance'], $statement['closing_balance'])) {
                continue;
            }
            $result[$id] = sprintf("%s - %s - %s  (%s - %s)", $statement['condo_id']['code'], $statement['bank_account_iban'], date('Y-m-d', $statement['date']), $statement['opening_balance'], $statement['closing_balance']);
        }
        return $result;
    }

    protected static function calcBankAccountIban($om, $ids, $lang) {
        $result = [];
        $statements = $om->read(self::getType(), $ids, ['bank_account_iban', 'bank_account_bic']);

        foreach($statements as $id => $statement) {
            $result[$id] = self::convertBbanToIban($statement['bank_account_iban']);
        }
        return $result;
    }

    protected static function policyIsReconciled($self): array {
        $result = [];
        $self->read(['is_reconciled']);
        foreach($self as $id => $statement) {
            if(!$statement['is_reconciled']) {
                $result[$id] = [
                    'not_reconciled' => 'The statement is not reconciled.'
                ];
            }
        }
        return $result;
    }

    protected static function policyIsBalanced($self): array {
        $result = [];
        $self->read(['is_balanced']);
        foreach($self as $id => $statement) {
            if(!$statement['is_balanced']) {
                $result[$id] = [
                    'is_balanced' => "Bank Statement [{$id}] is not balanced."
                ];
                continue;
            }
        }
        return $result;
    }

    protected static function onbeforedelete($self) {
        $self->read(['document_process_id']);
        foreach($self as $id => $bankStatement) {
            if(!$bankStatement['document_process_id']) {
                continue;
            }
            DocumentProcess::id($bankStatement['document_process_id'])->do('remove');
        }
    }

    private static function convertBbanToIban($account_number) {

        /*
            account number already has IBAN format
        */

        if( !is_numeric(substr($account_number, 0, 2)) ) {
            return $account_number;
        }

        /*
            if code is not a country code, then convert BBAN to IBAN
        */

        // create numeric code of the target country
        $country_code = 'BE';

        $code_alpha = $country_code;
        $code_num = '';

        for($i = 0; $i < strlen($code_alpha); ++$i) {
            $letter = substr($code_alpha, $i, 1);
            $order = ord($letter) - ord('A');
            $code_num .= '1'.$order;
        }

        $check_digits = substr($account_number, -2);
        $dummy = intval($check_digits . $check_digits . $code_num . '00');
        $control = 98 - ($dummy % 97);
        return trim(sprintf("BE%02d%s", $control, $account_number));
    }

    public static function onchange($self, $event, $values, $view, $lang) {
        $result = [];

        switch($view) {
            case 'form.manual':
            case 'form.default':
                if(isset($event['bank_account_id'])) {
                    $bankAccount = CondominiumBankAccount::id($event['bank_account_id'])->read(['current_balance'])->first();
                    if($bankAccount) {
                        $result['opening_balance'] = $bankAccount['current_balance'];
                    }

                }
                // #memo - bank_account_id controls bank_account_iban
                /*
                if(isset($event['bank_account_iban'])) {
                    if(!isset($values['condo_id'])) {
                        $event['bank_account_iban'] = trim(str_replace(' ', '', $event['bank_account_iban']));
                        $result['bank_account_iban'] = $event['bank_account_iban'];
                        $bankAccount = CondominiumBankAccount::search(['bank_account_iban', '=', $event['bank_account_iban']])->read(['condo_id' => ['id', 'name']])->first();
                        if($bankAccount) {
                            $result['condo_id'] = ['id' => $bankAccount['condo_id']['id'], 'name' => $bankAccount['condo_id']['name']];
                            $previousBankStatement = BankStatement::search([['bank_account_id', '=', $bankAccount['id']]], ['sort' => ['date' => 'desc']])
                                ->read(['statement_number', 'opening_balance', 'closing_balance'])
                                ->first();
                            if($previousBankStatement) {
                                $result['statement_number'] = self::computeNewStatementNumber($previousBankStatement['statement_number']);
                                $result['opening_balance'] = $previousBankStatement['closing_balance'];
                            }
                        }
                    }
                }
                elseif(empty($values['bank_account_iban']) && (isset($event['condo_id']) || isset($values['condo_id']))) {
                    $condo_id = $event['condo_id'] ?? $values['condo_id'];
                    $condominium = Condominium::id($condo_id)->read(['bank_accounts_ids' => ['bank_account_iban']])->first(true);
                    if($condominium) {
                        $list = array_map(function($a) {return $a['bank_account_iban'];}, $condominium['bank_accounts_ids']);
                        $result['bank_account_iban'] = [
                            'value' => '',
                            'selection' => $list
                        ];
                    }
                }
                */
                if(isset($event['date']) || isset($values['date'])) {
                    $date = $event['date'] ?? $values['date'];
                    if(!isset($values['opening_date'])) {
                        $result['opening_date'] = $date;
                    }
                    if(!isset($values['closing_date'])) {
                        $result['closing_date'] = $date;
                    }
                }
                break;
        }
        return $result;
    }

    private static function computeNewStatementNumber($previous_statement_number)  {
        return sprintf("%03d", intval($previous_statement_number) + 1);
    }

    public function getUnique() {
        return [
            ['bank_account_iban', 'opening_date', 'opening_balance', 'closing_date', 'closing_balance']
        ];
    }
}
