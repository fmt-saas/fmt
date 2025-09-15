<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\sale\pay;

use finance\accounting\Journal;
use finance\bank\CondominiumBankAccount;
use realestate\finance\accounting\AccountingEntry;
use realestate\finance\accounting\AccountingEntryLine;

class Payment extends \sale\pay\Payment {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the payment relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'description'       => 'The funding the payment relates to, if any.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'order'             => 'issue_date',
                'sort'              => 'asc'
            ],

            'receipt_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'The Bank account the payment relates to.',
                'help'              => 'This is the bank account on which movement was actually performed (received or sent), and might differ from the Funding banK-account_id.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\finance\accounting\AccountingEntry',
                'description'       => "Accounting entry of the invoice.",
                'domain'            => [['origin_object_class', '=', 'finance\accounting\MiscOperation'], ['origin_object_id', '=', 'object.id']]
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
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

    protected static function policyCanGenerateAccountingEntry($self) {
        $result = [];
        $self->read([
                'status',
                'bank_statement_line_id' => ['bank_statement_id'],
                'funding_id' => ['counterpart_accounting_account_id']
            ]);

        foreach($self as $id => $payment) {
            if($payment['status'] !== 'pending') {
                $result[$id] = [
                    'invalid_status' => 'Only pending payment can be posted.'
                ];
                continue;
            }
            if( !($payment['bank_statement_line_id']['bank_statement_id'] ?? null) ) {
                $result[$id] = [
                    'missing_mandatory_bank_statement' => 'Payment not linked to any bank statement.'
                ];
                continue;
            }

            if( !($payment['funding_id']['counterpart_accounting_account_id'] ?? null) ) {
                $result[$id] = [
                    'missing_mandatory_counterpart_account' => 'Payment (via Funding) not linked to counterpart accounting account.'
                ];
                continue;
            }

        }
        return $result;
    }

    protected static function onbeforePost($self) {
        $self->do('generate_accounting_entry');
    }

    protected static function doGenerateAccountingEntry($self) {
        $self->read([
                'condo_id',
                'amount',
                'journal_id',
                'receipt_date',
                'fiscal_year_id',
                'fiscal_period_id',
                'receipt_bank_account_id',
                'bank_statement_line_id' => [
                    'id', 'bank_statement_id'
                ],
                'funding_id' => [
                    'counterpart_accounting_account_id'
                ]
            ]);

        foreach($self as $id => $payment) {
            // #memo - the receipt bank account should match the bank statement bank account
            $bankAccount = CondominiumBankAccount::id($payment['receipt_bank_account_id'])->read(['accounting_account_id'])->first();

            if(!$bankAccount) {
                throw new \Exception('missing_bank_account', EQ_ERROR_INVALID_CONFIG);
            }

            $bankJournal = Journal::search([['journal_type', '=', 'BANK'], ['condo_id', '=', $payment['condo_id']]])->first();

            if(!$bankJournal) {
                throw new \Exception('missing_bank_journal', EQ_ERROR_INVALID_CONFIG);
            }

            $debit_account_id = $bankAccount['accounting_account_id'];
            $credit_account_id = $payment['funding_id']['counterpart_accounting_account_id'];

            $amount = round($payment['amount'], 2);

            $accountingEntry = AccountingEntry::create([
                    'condo_id'                  => $payment['condo_id'],
                    'entry_date'                => $payment['receipt_date'],
                    'origin_object_class'       => self::getType(),
                    'origin_object_id'          => $id,
                    'journal_id'                => $bankJournal['id'],
                    'bank_statement_line_id'    => $payment['bank_statement_line_id']['id'],
                    'bank_statement_id'         => $payment['bank_statement_line_id']['bank_statement_id'],
                ])
                ->first();

            // #memo - debit & credit assignment might be inverted if amount is negative

            // debit line
            AccountingEntryLine::create([
                    'condo_id'               => $payment['condo_id'],
                    'account_id'             => $debit_account_id,
                    'debit'                  => $amount > 0 ? abs($amount) : 0,
                    'credit'                 => $amount < 0 ? abs($amount) : 0,
                    'accounting_entry_id'    => $accountingEntry['id'],
                    'bank_statement_line_id' => $id,
                ]);

            // credit line
            AccountingEntryLine::create([
                    'condo_id'               => $payment['condo_id'],
                    'account_id'             => $credit_account_id,
                    'debit'                  => $amount < 0 ? abs($amount) : 0,
                    'credit'                 => $amount > 0 ? abs($amount) : 0,
                    'accounting_entry_id'    => $accountingEntry['id'],
                    'bank_statement_line_id' => $id,
                ]);

            AccountingEntry::id($accountingEntry['id'])->transition('validate');

            // Store the created accounting entry ID back to the payment
            self::id($id)->update(['accounting_entry_id' => $accountingEntry['id']]);
        }
    }



}
