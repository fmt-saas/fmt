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
use realestate\purchase\accounting\AccountingEntry;
use realestate\purchase\accounting\AccountingEntryLine;
use realestate\sale\pay\Funding;

class MoneyRefund extends \finance\accounting\MiscOperation {

    public static function getName() {
        return 'Money Refund';
    }

    public static function getDescription() {
        return 'A MoneyRefund is a specific type of Miscellaneous Operation designed to generate entries and funding for tracking a transfer to an external ownership account.';
    }

    public static function getColumns() {
        return [
            'description' => [
                'type'              => 'string',
                'description'       => 'Explanation or internal notes about the operation.',
                'default'           => 'Money Refund'
            ],

            'operation_type' => [
                'type'              => 'string',
                'readonly'          => true,
                'default'           => 'refund',
                'description'       => "Type of operation, necessary for entities inheriting from MiscOperation."
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'The Bank account the refund originates from.',
                'help'              => 'This is the bank account from which the refund is made.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['bank_account_type', '=', 'bank_current']]
            ],

            'account_available_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'relation'          => ['bank_account_id' => 'available_balance'],
                'description'       => 'The origin Bank account available balance.'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'description'       => "Ownership to which the refund is due.",
                'required'          => true,
                'domain'            => [['condo_id', '=', 'object.condo_id']]
            ],

            'ownership_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\OwnershipBankAccount',
                'description'       => 'The Bank account of the Ownership.',
                'required'          => true,
                'help'              => 'This is the bank account of the ownership to which the refund has to be made.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['ownership_id', '=', 'object.ownership_id']]
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount to be refunded.',
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
                    'posted',
                    'completed'
                ],
                'default'           => 'draft',
                'description'       => 'Current status of the operation.',
            ],
        ];
    }

    public static function getWorkflow() {
        return array_merge(parent::getWorkflow(), [
            'draft' => [
                'description' => 'Just imported document, waiting to be completed (manually or auto-analysis).',
                'icon'        => 'draw',
                'transitions' => [
                    'publish' => [
                        'description' => 'Update the document to `completed`.',
                        'policies'    => ['is_valid'],
                        'onafter'     => 'onafterPost',
                        'status'      => 'posted'
                    ]
                ]
            ],
            'proforma' => [
                'description' => 'Just imported document, waiting to be completed (manually or auto-analysis).',
                'icon'        => 'draw',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the document to `completed`.',
                        'policies'    => [],
                        'onafter'     => 'onafterPost',
                        'status'      => 'posted'
                    ]
                ]
            ],
            'posted' => [
                'description' => 'Just imported document, waiting to be completed (manually or auto-analysis).',
                'icon'        => 'draw',
                'transitions' => [
                    'complete' => [
                        'description' => 'Update the document to `completed`.',
                        'policies'    => ['is_paid'],
                        'onafter'     => 'onafterComplete',
                        'status'      => 'completed'
                    ]
                ]
            ]
        ]);
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'generate_accounting_entry_outgoing' => [
                'description'   => 'Creates accounting entries for the refund.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doGenerateAccountingEntryOutgoing'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'is_valid' => [
                'description' => 'Verifies that the state of the Money Refund allows validation.',
                'function'    => 'policyIsValid'
            ],
            'is_paid' => [
                'description' => 'Verifies that the refund has been paid.',
                'function'    => 'policyIsPaid'
            ]
        ]);
    }

    protected static function policyIsValid($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'posting_date', 'amount', 'bank_account_id', 'account_available_balance', 'ownership_id', 'ownership_bank_account_id']);
        foreach($self as $id => $moneyRefund) {
            if(!isset($moneyRefund['bank_account_id'])) {
                $result[$id] = [
                    'missing_bank_account' => 'The bank account is missing.'
                ];
            }
            if(!isset($moneyRefund['ownership_id'])) {
                $result[$id] = [
                    'missing_owner' => 'The owner is missing.'
                ];
            }
            if(!isset($moneyRefund['ownership_bank_account_id'])) {
                $result[$id] = [
                    'missing_owner_account' => 'The account of the owner is missing.'
                ];
            }
            if(!isset($moneyRefund['condo_id'])) {
                $result[$id] = [
                    'missing_condominium' => 'The target condominium must be specified.'
                ];
            }
            if($moneyRefund['amount'] <= 0) {
                $result[$id] = [
                    'invalid_amount' => 'Amount must be greater than zero.'
                ];
            }
            if($moneyRefund['amount'] > $moneyRefund['account_available_balance']) {
                $result[$id] = [
                    'insufficient_fund' => 'Available fund must be higher than the refund amount.'
                ];
            }
            if(date('Ymd', $moneyRefund['posting_date']) !== date('Ymd', time())) {
                $result[$id] = [
                    'invalid_posting_date' => 'Posting date must be today.'
                ];
            }
        }
        return $result;
    }

    protected static function policyIsPaid($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'amount']);
        foreach($self as $id => $moneyRefund) {
            // #todo
            // External ownership accounts cannot be directly validated for payment.
            // Add custom logic here if needed.
        }
        return $result;
    }

    protected static function calcJournalId($self) {
        $result = [];
        $self->read(['condo_id']);
        foreach($self as $id => $moneyRefund) {
            if(!$moneyRefund['condo_id']) {
                continue;
            }
            $journal = Journal::search([['condo_id', '=', $moneyRefund['condo_id']], ['journal_type', '=', 'MISC']])->first();

            if($journal) {
                $result[$id] = $journal['id'];
            }
        }
        return $result;
    }

    protected static function calcFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'posting_date']);
        foreach($self as $id => $moneyRefund) {
            $result[$id] = self::computeFiscalYearId($moneyRefund['condo_id'], $moneyRefund['posting_date']);
        }
        return $result;
    }

    protected static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['condo_id', 'posting_date']);
        foreach($self as $id => $moneyRefund) {
            $result[$id] = self::computeFiscalPeriodId($moneyRefund['condo_id'], $moneyRefund['posting_date']);
        }
        return $result;
    }

    protected static function doGenerateAccountingEntryOutgoing($self) {
        $self->read([
                'condo_id', 'amount', 'posting_date', 'journal_id', 'fiscal_year_id', 'fiscal_period_id',
                'bank_account_id' => ['accounting_account_id']
            ]);

        foreach($self as $id => $moneyRefund) {

            try {
                $accountingEntry = AccountingEntry::create([
                        'condo_id'              => $moneyRefund['condo_id'],
                        'entry_date'            => $moneyRefund['posting_date'],
                        'origin_object_class'   => self::getType(),
                        'origin_object_id'      => $id,
                        'journal_id'            => $moneyRefund['journal_id'],
                        'fiscal_year_id'        => $moneyRefund['fiscal_year_id'],
                        'fiscal_period_id'      => $moneyRefund['fiscal_period_id']
                    ])
                    ->first();

                AccountingEntryLine::create([
                        'account_id'            => $moneyRefund['bank_account_id']['accounting_account_id'],
                        'debit'                 => 0.0,
                        'credit'                => $moneyRefund['amount'],
                        'accounting_entry_id'   => $accountingEntry['id']
                    ]);

                // External ownership accounts are not directly accessible, so no counterpart entry is created.

                // Store the created accounting entry ID back to the misc operation
                self::id($id)->update(['accounting_entry_id' => $accountingEntry['id']]);

                AccountingEntry::id($accountingEntry['id'])->transition('validate');
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

    protected static function onafterPost($self) {
        $self
            ->do('generate_accounting_entry_outgoing');
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['bank_account_id'])) {
            $bankAccount = CondominiumBankAccount::id($event['bank_account_id'])->read(['available_balance'])->first();
            if($bankAccount) {
                $result['account_available_balance'] = $bankAccount['available_balance'];
            }
        }

        if(array_key_exists('ownership_id', $event) && !isset($event['ownership_id'])) {
            $result['ownership_bank_account_id'] = null;
        }

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
}