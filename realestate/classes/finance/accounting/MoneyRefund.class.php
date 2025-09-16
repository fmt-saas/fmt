<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\finance\accounting;

use finance\accounting\Account;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\bank\CondominiumBankAccount;
use realestate\finance\accounting\AccountingEntry;
use realestate\finance\accounting\AccountingEntryLine;
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

            'refund_type' => [
                'type'              => 'string',
                'selection'         => [
                    'owner_refund',
                    'supplier_refund'
                ],
                'default'           => 'owner_refund',
                'description'       => "Type of refund (owner or supplier), necessary for Funding creation."
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'The Bank account the refund originates from.',
                'help'              => 'This is the bank account from which the refund is made.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['bank_account_type', '=', 'bank_current']]
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
                'domain'            => [['condo_id', '=', 'object.condo_id']],
                'visible'           => ['refund_type', '=', 'owner_refund']
            ],

            'ownership_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\OwnershipBankAccount',
                'description'       => 'The Bank account of the Ownership to reimburse.',
                'help'              => 'This is the bank account of the ownership to which the refund has to be made.',
                'domain'            => [
                        ['condo_id', '=', 'object.condo_id'],
                        ['condo_id', '<>', null],
                        ['ownership_id', '=', 'object.ownership_id']
                    ],
                'visible'           => [['ownership_id', '<>', null], ['refund_type', '=', 'owner_refund']]
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'description'       => 'The supplier the funding relates to.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'suppliership_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\SuppliershipBankAccount',
                'description'       => "The bank accounts of the supplier te reimburse.",
                'domain'            => [
                        ['condo_id', '=', 'object.condo_id'],
                        ['condo_id', '<>', null],
                        ['suppliership_id', '=', 'object.suppliership_id']
                    ],
                'visible'           => [['ownership_id', '<>', null], ['refund_type', '=', 'owner_refund']]
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

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'origin_object_id',
                'description'       => "Accounting entry of the invoice.",
                'domain'            => [['origin_object_class', '=', 'realestate\finance\accounting\MoneyRefund']]
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'money_refund_id',
                'description'       => 'The fundings related to the money transfer.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'proforma',
                    'sent',
                    'posted'
                ],
                'default'           => 'pending',
                'description'       => 'Current status of the operation.',
            ],
        ];
    }

    public static function getWorkflow() {
        return array_merge(parent::getWorkflow(), [
            'pending' => [
                'description' => 'Just imported document, waiting to be completed (manually or auto-analysis).',
                'icon'        => 'draw',
                'transitions' => [
                    'publish' => [
                        'description' => 'Update the document to `completed`.',
                        'policies'    => ['is_valid'],
                        'status'      => 'proforma'
                    ]
                ]
            ],
            'proforma' => [
                'description' => 'Just imported document, waiting to be completed (manually or auto-analysis).',
                'icon'        => 'draw',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the document to `completed`.',
                        'help'        => 'This transition is used to post the money transfer, after a bank statement has been integrated. It creates the accounting entries and fundings necessary to track the transfer.',
                        'policies'    => [/*'can_post'*/],
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
                'description'   => 'Creates accounting entries for the refund.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doGenerateAccountingEntry'
            ],
            'create_fundings' => [
                'description'   => 'Creates a funding for the transfer followup.',
                'policies'      => [/* 'can_generate_fundings' */],
                'function'      => 'doCreateFundings'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'is_valid' => [
                'description' => 'Verifies that the state of the Money Refund allows validation.',
                'function'    => 'policyIsValid'
            ],
            'is_payable' => [
                'description' => 'Verifies that the balance of the bank account is sufficient.',
                'function'    => 'policyIsPayable'
            ],
            'is_paid' => [
                'description' => 'Verifies that the refund has been paid.',
                'function'    => 'policyIsPaid'
            ]
        ]);
    }

    protected static function policyIsPayable($self): array {
        $result = [];
        $self->read(['amount', 'account_available_balance']);
        foreach($self as $id => $moneyRefund) {
            if($moneyRefund['amount'] > $moneyRefund['account_available_balance']) {
                $result[$id] = [
                    'insufficient_fund' => 'Available fund must be higher than the refund amount.'
                ];
            }
        }
        return $result;
    }

    protected static function policyIsValid($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'posting_date', 'amount', 'bank_account_id', 'ownership_id', 'ownership_bank_account_id']);
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
            /*
            // #todo - not sure for this limitation
            if(date('Ymd', $moneyRefund['posting_date']) !== date('Ymd', time())) {
                $result[$id] = [
                    'invalid_posting_date' => 'Posting date must be today.'
                ];
            }
            */
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

    protected static function doCreateFundings($self) {
        $self->read([
                'condo_id',
                'amount',
                'refund_type',
                'bank_account_id',
                'ownership_id',
                'suppliership_id',
                'counterpart_bank_account_id'
            ]);

        // request an amount from the owner, to be paid on the current account
        foreach($self as $id => $moneyRefund) {

            switch($moneyRefund['refund_type']) {
                case 'owner_refund':
                    $counterpartAccount = Account::search([
                            ['condo_id', '=', $moneyRefund['condo_id']],
                            ['ownership_id', '=', $moneyRefund['ownership_id']],
                            ['operation_assignment', '=', 'co_owners_working_fund']
                        ])
                        ->first();

                    if(!$counterpartAccount) {
                        throw new \Exception('missing_ownership_accounting_account', EQ_ERROR_INVALID_PARAM);
                    }
                    break;
                case 'supplier_refund':
                    $counterpartAccount = Account::search([
                            ['condo_id', '=', $moneyRefund['condo_id']],
                            ['ownership_id', '=', $moneyRefund['suppliership_id']],
                            ['operation_assignment', '=', 'suppliers']
                        ])
                        ->first();

                    if(!$counterpartAccount) {
                        throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                    }
                    break;
            }

            Funding::create([
                    'condo_id'                          => $moneyRefund['condo_id'],
                    'money_refund_id'                   => $id,
                    'funding_type'                      => 'refund',
                    'due_amount'                        => -$moneyRefund['amount'],
                    'bank_account_id'                   => $moneyRefund['bank_account_id'],
                    'counterpart_bank_account_id'       => $moneyRefund['counterpart_bank_account_id'],
                    'counterpart_accounting_account_id' => $counterpartAccount['id'],
                    // #todo - allow custom with setting
                    'due_date'                          => time() + 10 * 86400
                ]);
        }
    }

    /**
     * Refunds are a specific case for which an automatic entry must be made after the reception of bank statements, which may be deferred.
     * This is to maintain the consistency of accounting operations (without "gaps" in the tracking of fund movements).
     */
    protected static function doGenerateAccountingEntry($self) {
        $self->read([
                'condo_id', 'amount', 'posting_date', 'journal_id', 'fiscal_year_id', 'fiscal_period_id',
                'ownership_id',
                'bank_account_id' => ['accounting_account_id']
            ]);

        foreach($self as $id => $moneyRefund) {
            AccountingEntry::search(['origin_object_class', '=', self::getType()], ['origin_object_id', '=', $id])->delete(true);

            try {
                $ownershipAccount = Account::search([
                        ['condo_id', '=', $moneyRefund['condo_id']],
                        ['ownership_id', '=', $moneyRefund['ownership_id']],
                        ['operation_assignment', '=', 'co_owners_working_fund']
                    ])
                    ->first();

                if(!$ownershipAccount) {
                    throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                }

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

                AccountingEntryLine::create([
                        'account_id'            => $ownershipAccount['id'],
                        'debit'                 => $moneyRefund['amount'],
                        'credit'                => 0.0,
                        'accounting_entry_id'   => $accountingEntry['id']
                    ]);

                AccountingEntry::id($accountingEntry['id'])->transition('validate');

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

    protected static function onafterSend($self) {
        $self->do('create_fundings');
    }

    protected static function onafterPost($self) {
        $self->do('generate_accounting_entry');
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