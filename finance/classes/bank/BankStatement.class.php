<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace finance\bank;

use documents\processing\DocumentProcess;
use equal\orm\Model;
use realestate\sale\pay\Payment;

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
                'description'       => "The bank account the statement refers to.",
                'foreign_object'    => 'finance\bank\BankAccount'
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
                'readonly'          => true,
                'default'           => function () {return time();}
            ],

            'statement_currency' => [
                'type'              => 'string',
                'description'       => 'Currency of the statement.',
                'default'           => 'EUR'
            ],

            'opening_date' => [
                'type'              => 'date',
                'description'       => 'First date the statement refers to.',
                'required'          => true
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
                'required'          => true
            ],

            'closing_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Account balance after the transactions.',
                'required'          => true
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'IBAN representation of the account number.',
                'required'          => true
            ],

            'bank_account_bic' => [
                'type'              => 'string',
                'description'       => 'Bank Identification Code of the account.'
            ],

            'statement_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'foreign_field'     => 'bank_statement_id',
                'description'       => 'The lines that are assigned to the statement.'
            ],

            'document_process_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\processing\DocumentProcess',
                'description'       => 'Document Process the statement originates from, if any.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Received Document that the statement is issued from, if any.'
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
                'icon' => 'receipt_long',
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
            ]
        ]);
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
                $bankStatement['statement_lines_ids']->do('reconcile');
            }
            catch(\Exception $e) {
                // safely ignore errors
            }
        }
    }

    protected static function calcIsReconciled($self) {
        $result = [];
        $self->read(['statement_lines_ids' => ['status']]);
        foreach($self as $id => $bankStatement) {
            $result[$id] = true;
            foreach($bankStatement['statement_lines_ids'] as $bankStatementLine) {
                if($bankStatementLine['status'] === 'pending') {
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
            $result[$id] = sprintf("%s - %s - %s - %s - %s", $statement['condo_id']['code'], $statement['bank_account_iban'], date('Ymd', $statement['date']), $statement['opening_balance'], $statement['closing_balance']);
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

    public static function calcStatus($self) {
        $result = [];
        $self->read(['statement_lines_ids' => 'status']);
        foreach($self as $id => $statement) {
            $is_reconciled = false;
            if(count($statement['statement_lines_ids'])) {
                $is_reconciled = true;
                foreach($statement['statement_lines_ids'] as $line_id => $line) {
                    if( !in_array($line['status'], ['reconciled', 'ignored']) ) {
                        $is_reconciled = false;
                        break;
                    }
                }
            }
            $result[$id] = ($is_reconciled) ? 'reconciled' : 'pending';
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
                $sum += round($statementLine['amount'], 2);
            }
            if(round($sum - $delta) !== 0.0) {
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

    public static function onchange($self, $event, $values, $lang) {
        $result = [];

        if(isset($event['bank_account_iban'])) {
            $event['bank_account_iban'] = trim(str_replace(' ', '', $event['bank_account_iban']));
            $result['bank_account_iban'] = $event['bank_account_iban'];
            $bankAccount = BankAccount::search(['bank_account_iban', '=', $event['bank_account_iban']])->read(['condo_id' => ['id', 'name']])->first();
            if($bankAccount) {
                $result['condo_id'] = ['id' => $bankAccount['condo_id']['id'], 'name' => $bankAccount['condo_id']['name']];
            }
        }

        return $result;
    }

    public function getUnique() {
        return [
            ['bank_account_iban', 'opening_date', 'opening_balance', 'closing_balance']
        ];
    }
}