<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace finance\bank;

use documents\processing\DocumentProcess;
use equal\orm\Model;
use finance\accounting\Account;
use finance\accounting\AccountingEntry;
use finance\accounting\AccountingEntryLine;
use finance\accounting\FiscalYear;
use realestate\property\Condominium;
use sale\pay\Payment;

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
                //'readonly'          => true
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'description'       => 'The bank account the statement refers to.',
                'help'              => 'This field is set automatically upon update of the `bank_account_iban` field',
                'foreign_object'    => 'finance\bank\BankAccount',
                'domain'            => ['condo_id', '=', 'object.condo_id']
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
                'help'              => 'This is for information only and might not be accurate with the actual date/time at which the statement was generated.',
                'readonly'          => true
            ],

            'statement_number' => [
                'type'              => 'string',
                'description'       => 'Arbitrary number of the statement, provided by the bank.',
                'help'              => 'This field can be left unknown for manually encoded statements.'
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
                'required'          => true
            ],

            'opening_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Account balance before the transactions.',
                'required'          => true,
                'default'           => 0.0
            ],

            'closing_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Account balance after the transactions.',
                'required'          => true,
                'default'           => 0.0
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'IBAN representation of the account number.',
                'onupdate'          => 'onupdateBankAccountIban'
            ],

            'bank_account_bic' => [
                'type'              => 'string',
                'description'       => 'Bank Identification Code of the account.'
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
                'description'       => 'Received Document that the statement is issued from, if any.'
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

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'proforma',
                    'posted'
                ],
                'default'           => 'proforma',
                'description'       => 'Status of the statement (depending on lines).'
            ]

        ];
    }

    public static function getActions() {
        return [
            'attempt_reconcile' => [
                'description'   => 'Attempt to reconcile the statement and its lines and invoke the creation of subsequent accounting entries.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doAttemptReconcile'
            ],
        ];
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Bank Statement being created.',
                'icon' => 'edit',
                'transitions' => [
                    'post' => [
                        'description' => 'Post bank statement statement to the accounting system.',
                        'policies'    => [
                            'is_balanced', 'is_reconciled'
                        ],
                        'onafter'     => 'onafterPost',
                        'status'      => 'posted'
                    ]
                ],
            ],
            'posted' => [
                'description' => 'The Bank Statement is reconciled.',
                'icon' => 'done',
                'transitions' => [
                    'cancel' => [
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
            'can_generate_accounting_entry' => [
                'function'    => 'policyCanGenerateAccountingEntry'
            ]
        ]);
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

    protected static function onupdateBankAccountIban($self) {
        $self->read(['bank_account_iban', 'condo_id']);
        foreach($self as $id => $bankStatement) {
            $bankAccount = CondominiumBankAccount::search(['bank_account_iban', '=', $bankStatement['bank_account_iban']])->read(['condo_id'])->first();
            if($bankAccount) {
                self::id($id)->update(['bank_account_id' => $bankAccount['id']]);
            }
        }
    }

    protected static function onafterPost($self) {
        $self->read(['document_process_id', 'statement_lines_ids' => ['payments_ids']]);
        foreach($self as $id => $bankStatement) {
            try {
                // mark involved payment as posted
                // #memo - this triggers a cascade event `attempt_posting` on Funding and related documents
                foreach($bankStatement['statement_lines_ids'] as $lid => $statementLine) {
                    Payment::ids($statementLine['payments_ids'])->transition('post');
                }
            }
            catch(\Exception $e) {
                // ignore already published payments
                trigger_error("APP::BankStatement::onafterPost - Failed to post payment: {$e->getMessage()}", EQ_REPORT_ERROR);
            }
            if($bankStatement['document_process_id']) {
                DocumentProcess::id($bankStatement['document_process_id'])
                    // bypass all stages
                    ->update(['status' => 'confirmed'])
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
                $fields['account_type'] = $bankAccount['bank_account_type'];
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
                $fields['statement_currency'] = $bankAccount['statement_currency'];
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

    public static function onafterupdate($self) {
        $self->do('update_document_json');
    }

    protected static function calcIsReconciled($self) {
        $result = [];
        $self->read(['statement_lines_ids' => ['is_reconciled']]);
        foreach($self as $id => $bankStatement) {
            $result[$id] = true;
            foreach($bankStatement['statement_lines_ids'] as $bankStatementLine) {
                if(!$bankStatementLine['is_reconciled']) {
                    $result[$id] = false;
                    break;
                }
            }
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

    public static function calcBankAccountIban($om, $ids, $lang) {
        $result = [];
        $statements = $om->read(self::getType(), $ids, ['bank_account_iban', 'bank_account_bic']);

        foreach($statements as $id => $statement) {
            $result[$id] = self::convertBbanToIban($statement['bank_account_iban']);
        }
        return $result;
    }

    public static function policyIsReconciled($self): array {
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

    public static function policyIsBalanced($self): array {
        $result = [];
        $self->read(['statement_lines_ids' => ['amount'], 'opening_balance', 'closing_balance']);
        foreach($self as $id => $statement) {
            $delta = round($statement['closing_balance'], 2) - round($statement['opening_balance'], 2);
            $sum = 0;
            foreach($statement['statement_lines_ids'] as $statementLine) {
                $sum += $statementLine['amount'];
            }
            if( (round($sum, 2) - $delta) !== 0.0) {
                $result[$id] = [
                    'invalid_amount' => 'The sum of the lines does not match the delta.'
                ];
            }
        }
        return $result;
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
            case 'form.create':
                if(isset($event['bank_account_iban'])) {
                    if(!isset($values['condo_id'])) {
                        $event['bank_account_iban'] = trim(str_replace(' ', '', $event['bank_account_iban']));
                        $result['bank_account_iban'] = $event['bank_account_iban'];
                        $bankAccount = CondominiumBankAccount::search(['bank_account_iban', '=', $event['bank_account_iban']])->read(['condo_id' => ['id', 'name']])->first();
                        if($bankAccount) {
                            $result['condo_id'] = ['id' => $bankAccount['condo_id']['id'], 'name' => $bankAccount['condo_id']['name']];
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

    public function getUnique() {
        return [
            ['bank_account_iban', 'opening_date', 'opening_balance', 'closing_balance']
        ];
    }
}
