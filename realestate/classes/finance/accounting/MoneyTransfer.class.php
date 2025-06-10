<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\finance\accounting;

use finance\accounting\Account;
use finance\accounting\CurrentBalanceLine;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use realestate\sale\pay\Funding;

class MoneyTransfer extends \finance\accounting\MiscOperation {

    public static function getName() {
        return 'Money Transfer';
    }

    public static function getDescription() {
        return 'A MoneyTransfer is a specific type of Miscellaneous Operation designed to generate entries and funding for tracking a transfer between internal bank accounts.';
    }

    public static function getColumns() {

        return [

            'description' => [
                'type'              => 'string',
                'description'       => 'Explanation or internal notes about the operation.',
                'default'           => 'Money Transfer'
            ],

            'operation_type' => [
                'type'              => 'string',
                'selection'         => [
                    'misc',
                    'transfer'
                ],
                'default'           => 'transfer',
                'description'       => "Type of operation, necessary for entities inheriting from MiscOperation."
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'The Bank account the funding relates to.',
                'help'              => 'This is the bank account to which payments are expected to be received or from which payment is expected to be made.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'counterpart_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'Counterpart bank account, when applying.',
                'help'              => 'The bank account used as the counterpart in a transfer. Required when the funding represents an internal transfer between two bank accounts.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'description'       => 'The funding related to the misc operation, if any.'
            ],

            'is_complete' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the money transfer as complete.',
                'help'              => 'A Money Transfer is considered complete when the amount has been credited to the destination account following the processing of a bank statement.',
                'default'           => false
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount to be transferred.',
            ],

            'journal_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => 'Accounting journal used for this miscellaneous operation.',
                'store'             => true,
                'function'          => 'calcJournalId',
                'readonly'          => true
            ],

            'posting_date' => [
                'type'              => 'date',
                'description'       => 'Date the operation is posted in the accounting system.',
                'default'           => function () { return time(); },
                'dependents'        => ['fiscal_year_id', 'fiscal_period_id']
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => 'Fiscal year in which the operation is recorded.',
                'function'          => 'calcFiscalYearId',
                'store'             => true,
                'instant'           => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => 'Accounting period derived from the posting date.',
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'proforma',
                    'posted'
                ],
                'default'           => 'draft',
                'description'       => 'Current status of the operation.',
            ],

        ];
    }

    public static function getWorkflow() {
        return array_merge(parent::getWorkflow(), [
            'proforma' => [
                'description' => 'Just imported document, waiting to be completed (manually or auto-analysis).',
                'icon'        => 'draw',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the document to `completed`.',
                        'policies'    => ['is_valid', 'can_transfer'],
                        'onafter'     => 'onafterPost',
                        'status'      => 'posted'
                    ]
                ]
            ]
        ]);
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'generate_accounting_entry' => [
                'description'   => 'Creates accounting entries according to operation lines.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doGenerateAccountingEntry'
            ],
            'create_funding' => [
                'description'   => 'Creates a funding for the transfer followup.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doCreateFunding'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'is_valid' => [
                'description' => 'Verifies that the state of the Money Transfer allows validation.',
                'function'    => 'policyIsValid'
            ],
            'can_transfer' => [
                'description' => 'Verifies that the origin bank account has enough funds to transfer the amount.',
                'function'    => 'policyCanTransfer'
            ]
        ]);
    }

    protected static function policyIsValid($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'posting_date', 'amount', 'bank_account_id' => ['accounting_account_id'], 'counterpart_bank_account_id']);
        foreach($self as $id => $moneyTransfer) {
            if(!isset($moneyTransfer['bank_account_id'], $moneyTransfer['counterpart_bank_account_id'])) {
                $result[$id] = [
                    'missing_bank_account' => 'At least one bank account is missing.'
                ];
            }
            if(!isset($moneyTransfer['condo_id'])) {
                $result[$id] = [
                    'missing_condominium' => 'The target condominium must be specified.'
                ];
            }
            if($moneyTransfer['amount'] <= 0) {
                $result[$id] = [
                    'invalid_amount' => 'Amount must be greater than zero.'
                ];
            }
            if(date('Ymd', $moneyTransfer['posting_date']) !== date('Ymd', time())) {
                $result[$id] = [
                    'invalid_posting_date' => 'Posting date must be today.'
                ];
            }
        }
        return $result;
    }

    protected static function policyCanTransfer($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'amount', 'bank_account_id' => ['available_balance'], 'counterpart_bank_account_id']);
        foreach($self as $id => $moneyTransfer) {

            if($moneyTransfer['bank_account_id']['available_balance'] < $moneyTransfer['amount']) {
                $result[$id] = [
                    'insufficient_funds' => 'The origin account has not enough funds.'
                ];
            }

            $bankTransferAccount = Account::search([ ['condo_id', '=', $moneyTransfer['condo_id']], ['operation_assignment', '=', 'bank_transfer'] ])->first();
            if(!$bankTransferAccount) {
                $result[$id] = [
                    'missing_bank_transfer_account' => 'The transfer account is missing from the chart of accounts.'
                ];
            }

        }
        return $result;
    }

    protected static function calcJournalId($self) {
        $result = [];
        $self->read(['condo_id']);
        foreach($self as $id => $moneyTransfer) {
            if(!$moneyTransfer['condo_id']) {
                continue;
            }
            $journal = Journal::search([['condo_id', '=', $moneyTransfer['condo_id']], ['journal_type', '=', 'MISC']])->first();

            if($journal) {
                $result[$id] = $journal['id'];
            }
        }
        return $result;
    }

    protected static function calcFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'posting_date']);
        foreach($self as $id => $moneyTransfer) {
            $result[$id] = self::computeFiscalYearId($moneyTransfer['condo_id'], $moneyTransfer['posting_date']);
        }
        return $result;
    }

    protected static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['condo_id', 'posting_date']);
        foreach($self as $id => $moneyTransfer) {
            $result[$id] = self::computeFiscalPeriodId($moneyTransfer['condo_id'], $moneyTransfer['posting_date']);
        }
        return $result;
    }

    protected static function doCreateFunding($self) {
        $self->read(['condo_id', 'amount', 'bank_account_id', 'counterpart_bank_account_id']);

        foreach($self as $id => $moneyTransfer) {
            Funding::create([
                    'condo_id'                      => $moneyTransfer['condo_id'],
                    'misc_operation_id'             => $id,
                    'funding_type'                  => 'transfer',
                    'due_amount'                    => -$moneyTransfer['amount'],
                    'bank_account_id'               => $moneyTransfer['bank_account_id'],
                    'counterpart_bank_account_id'   => $moneyTransfer['counterpart_bank_account_id'],
                    // #todo - allow custom with setting
                    'due_date'                      => time() + 10 * 86400
                ]);
        }
    }

    protected static function doGenerateAccountingEntry($self) {
        $self->read([
                'condo_id', 'amount', 'posting_date', 'journal_id', 'fiscal_year_id', 'fiscal_period_id',
                'bank_account_id' => ['accounting_account_id'],
                'counterpart_bank_account_id' => ['accounting_account_id']
            ]);
        foreach($self as $id => $moneyTransfer) {

            try {
                $accountingEntry = AccountingEntry::create([
                        'condo_id'              => $moneyTransfer['condo_id'],
                        'entry_date'            => $moneyTransfer['posting_date'],
                        'origin_object_class'   => self::getType(),
                        'origin_object_id'      => $id,
                        'journal_id'            => $moneyTransfer['journal_id'],
                        'fiscal_year_id'        => $moneyTransfer['fiscal_year_id'],
                        'fiscal_period_id'      => $moneyTransfer['fiscal_period_id']
                    ])
                    ->first();

                $bankTransferAccount = Account::search([ ['condo_id', '=', $moneyTransfer['condo_id']], ['operation_assignment', '=', 'bank_transfer'] ])->first();

                AccountingEntryLine::create([
                        'account_id'            => $moneyTransfer['bank_account_id']['accounting_account_id'],
                        'debit'                 => 0.0,
                        'credit'                => $moneyTransfer['amount'],
                        'accounting_entry_id'   => $accountingEntry['id']
                    ]);

                AccountingEntryLine::create([
                        'account_id'            => $bankTransferAccount['id'],
                        'debit'                 => $moneyTransfer['amount'],
                        'credit'                => 0.0,
                        'accounting_entry_id'   => $accountingEntry['id']
                    ]);

                // Store the created accounting entry ID back to the misc operation
                self::id($id)->update(['accounting_entry_id' => $accountingEntry['id']]);
            }
            catch(\Exception $e) {
                // rollback
                if($accountingEntry) {
                    AccountingEntry::id($accountingEntry['id'])->delete(true);
                }
                trigger_error("APP:Unexpected error while creating accounting entry: ".$e->getMessage(), EQ_REPORT_WARNING);
                throw new \Exception('unexpected_error', EQ_ERROR_UNKNOWN);
            }
        }
    }

    private static function computeFiscalYearId($condo_id, $posting_date) {
        $result = null;

        $fiscalYear = FiscalYear::search([['condo_id', '=', $condo_id], ['date_from', '<=', $posting_date], ['date_to', '>=', $posting_date]])
            ->read(['fiscal_periods_ids' => ['date_from', 'date_to']])
            ->first();

        if($fiscalYear) {
            $result = $fiscalYear['id'];
        }

        return $result;
    }

    private static function computeFiscalPeriodId($condo_id, $posting_date) {
        $result = null;

        $fiscalYear = FiscalYear::search([['condo_id', '=', $condo_id], ['date_from', '<=', $posting_date], ['date_to', '>=', $posting_date]])
            ->read(['fiscal_periods_ids' => ['date_from', 'date_to']])
            ->first();

        if(!$fiscalYear) {
            return $result;
        }

        foreach($fiscalYear['fiscal_periods_ids'] ?? [] as $period_id => $period) {
            if($posting_date >= $period['date_from'] && $posting_date <= $period['date_to']) {
                $result = $period_id;
                break;
            }
        }

        return $result;
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['posting_date'], $values['condo_id'])) {
            $fiscal_year_id = self::computeFiscalYearId($values['condo_id'], $event['posting_date']);
            if($fiscal_year_id) {
                $fiscalYear = FiscalYear::id($fiscal_year_id)->read(['id', 'name'])->first();
                $result['fiscal_year_id'] = [
                    'id'    => $fiscalYear['id'],
                    'name'  => $fiscalYear['name']
                ];
            }

            $fiscal_period_id = self::computeFiscalPeriodId($values['condo_id'], $event['posting_date']);
            if($fiscal_period_id) {
                $fiscalPeriod = FiscalPeriod::id($fiscal_period_id)->read(['id', 'name'])->first();
                $result['fiscal_period_id'] = [
                    'id'    => $fiscalPeriod['id'],
                    'name'  => $fiscalPeriod['name']
                ];
            }
        }

        return $result;
    }

    protected static function onafterPost($self) {
        $self
            ->do('generate_accounting_entry')
            ->do('validate_accounting_entry')
            ->do('create_funding');
    }

}
