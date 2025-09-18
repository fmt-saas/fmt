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
                'default'           => 'transfer',
                'readonly'          => true,
                'description'       => "Type of operation, necessary for entities inheriting from MiscOperation."
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'The Bank account the funding relates to.',
                'help'              => 'This is the bank account to which payments are expected to be received or from which payment is expected to be made.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'counterpart_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'Counterpart bank account, when applying.',
                'help'              => 'The bank account used as the counterpart in a transfer. Required when the funding represents an internal transfer between two bank accounts.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'account_available_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'relation'          => ['bank_account_id' => 'available_balance'],
                'description'       => 'The origin Bank account available balance.'
            ],

            'counterpart_available_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'relation'          => ['counterpart_bank_account_id' => 'available_balance'],
                'description'       => 'The target Bank account available balance.'
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'origin_object_id',
                'description'       => "Accounting entry of the invoice.",
                'domain'            => [['origin_object_class', '=', 'realestate\finance\accounting\MoneyTransfer']]
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'money_transfer_id',
                'description'       => 'The fundings related to the money transfer.'
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
                'description'       => 'Accounting journal used for the Money Transfer.',
                'help'              => 'Money transfer between internal accounts is a bank operation and is always put in the BNK/FIN journal.',
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
                    'pending',
                    'proforma',
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
                        'policies'    => ['is_valid', 'can_transfer'],
                        'onafter'     => 'onafterPublish',
                        'status'      => 'proforma'
                    ]
                ]
            ],
            'proforma' => [
                'description' => 'Planned transfer waiting to be sent.',
                'icon'        => 'send',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the document to `completed`.',
                        'help'        => 'This transition is used to post the money transfer, after a bank statement has been integrated. It creates the accounting entries and fundings necessary to track the transfer.',
                        'policies'    => ['can_post'],
                        'onbefore'     => 'onbeforePost',
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
                'policies'      => [],
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
                'description' => 'Verifies that the state of the Money Transfer allows validation.',
                'function'    => 'policyIsValid'
            ],
            'is_paid' => [
                'description' => 'Verifies that the state of the Money Transfer allows validation.',
                'function'    => 'policyIsPaid'
            ],
            'is_posted' => [
                'description' => 'Verifies that the state of the Money Transfer allows validation.',
                'function'    => 'policyIsPosted'
            ],
            'can_transfer' => [
                'description' => 'Verifies that the origin bank account has enough funds to transfer the amount.',
                'help'        => 'This policy verifies the balance of the origin bank by considering pending fundings, if any.',
                'function'    => 'policyCanTransfer'
            ],
            'can_post' => [
                'description' => 'Verifies that the origin bank account has enough funds to post the transfer.',
                'help'        => 'This policy verifies strictly the balance of the origin bank (based on current accounting entries).',
                'function'    => 'policyCanPost'
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
            if(date('Ymd', $moneyTransfer['posting_date']) < date('Ymd', time())) {
                $result[$id] = [
                    'invalid_posting_date' => 'Posting date cannot be in the past.'
                ];
            }
        }
        return $result;
    }

    protected static function policyIsPaid($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'amount', 'fundings_ids' => ['is_paid']]);
        foreach($self as $id => $moneyTransfer) {
            if($moneyTransfer['fundings_ids']->count() <> 2) {
                $result[$id] = [
                    'missing_funding' => 'There should be exactly 2 fundings.'
                ];
            }
            foreach($moneyTransfer['fundings_ids'] as $funding_id => $funding) {
                if(!$funding['is_paid']) {
                    $result[$id] = [
                        'unpaid_funding' => 'At least one funding is not paid.'
                    ];
                    break;
                }
            }
        }
        return $result;
    }

    protected static function policyIsPosted($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $moneyTransfer) {
            if($moneyTransfer['status'] !== 'posted') {
                $result[$id] = [
                    'invalid_status' => 'Status must be `posted`.'
                ];
            }
        }
        return $result;
    }

    protected static function policyCanTransfer($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'amount', 'bank_account_id' => ['available_balance']]);
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

    // #todo - this should not occur - raise an alert if it does
    protected static function policyCanPost($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'amount', 'bank_account_id' => ['current_balance']]);
        foreach($self as $id => $moneyTransfer) {
            if($moneyTransfer['bank_account_id']['current_balance'] < $moneyTransfer['amount']) {
                /*
                $result[$id] = [
                    'insufficient_funds' => 'The origin account has not enough funds.'
                ];
                */
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
            $journal = Journal::search([['condo_id', '=', $moneyTransfer['condo_id']], ['journal_type', '=', 'BANK']])->first();

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

    protected static function doCreateFundings($self) {
        $self->read([
                'condo_id',
                'amount',
                'bank_account_id' => ['accounting_account_id'],
                'counterpart_bank_account_id' => ['accounting_account_id']
            ]);
        // #memo - this operation will result in 2 bank statements lines on 2 distinct bank statements (one for each involved bank account)
        foreach($self as $id => $moneyTransfer) {
            Funding::create([
                    'condo_id'                          => $moneyTransfer['condo_id'],
                    'money_transfer_id'                 => $id,
                    'funding_type'                      => 'transfer',
                    'due_amount'                        => -$moneyTransfer['amount'],
                    'bank_account_id'                   => $moneyTransfer['bank_account_id']['id'],
                    'counterpart_bank_account_id'       => $moneyTransfer['counterpart_bank_account_id']['id'],
                    'counterpart_accounting_account_id' => $moneyTransfer['counterpart_bank_account_id']['accounting_account_id'],
                    // #todo - allow custom with setting
                    'due_date'                          => time() + 10 * 86400,
                    // #memo - payment_reference is a computed field
                ]);

            Funding::create([
                    'condo_id'                          => $moneyTransfer['condo_id'],
                    'money_transfer_id'                 => $id,
                    'funding_type'                      => 'transfer',
                    'due_amount'                        => $moneyTransfer['amount'],
                    'bank_account_id'                   => $moneyTransfer['counterpart_bank_account_id']['id'],
                    'counterpart_bank_account_id'       => $moneyTransfer['bank_account_id']['id'],
                    'counterpart_accounting_account_id' => $moneyTransfer['bank_account_id']['accounting_account_id'],
                    // #todo - allow custom with setting
                    'due_date'                          => time() + 10 * 86400,
                    // #memo - payment_reference is a computed field
                ]);
        }
    }


    /**
     * Fund transfers are a specific case for which an automatic entry must be made after the reception of bank statements, which may be deferred.
     * This is to maintain the consistency of accounting operations (without "gaps" in the tracking of fund movements).
     */
    protected static function doGenerateAccountingEntry($self) {
        $self->read([
                'condo_id', 'amount', 'posting_date', 'journal_id', 'fiscal_year_id', 'fiscal_period_id',
                'bank_account_id' => ['accounting_account_id'],
                'counterpart_bank_account_id' => ['accounting_account_id']
            ]);

        foreach($self as $id => $moneyTransfer) {

            $funding = Funding::search([
                    ['condo_id', '=', $moneyTransfer['condo_id']],
                    ['money_transfer_id', '=', $id],
                    ['bank_account_id', '=', $moneyTransfer['bank_account_id']['id']]
                ])
                ->first();

            try {
                $accountingEntry = AccountingEntry::create([
                        'condo_id'              => $moneyTransfer['condo_id'],
                        'entry_date'            => $moneyTransfer['posting_date'],
                        'origin_object_class'   => self::getType(),
                        'origin_object_id'      => $id,
                        'journal_id'            => $moneyTransfer['journal_id'],
                        'matching_id'           => $funding['id'],
                        'fiscal_year_id'        => $moneyTransfer['fiscal_year_id'],
                        'fiscal_period_id'      => $moneyTransfer['fiscal_period_id']
                    ])
                    ->first();

                AccountingEntryLine::create([
                        'account_id'            => $moneyTransfer['bank_account_id']['accounting_account_id'],
                        'debit'                 => 0.0,
                        'credit'                => $moneyTransfer['amount'],
                        'accounting_entry_id'   => $accountingEntry['id']
                    ]);

                AccountingEntryLine::create([
                        'account_id'            => $moneyTransfer['counterpart_bank_account_id']['accounting_account_id'],
                        'debit'                 => $moneyTransfer['amount'],
                        'credit'                => 0.0,
                        'accounting_entry_id'   => $accountingEntry['id']
                    ]);

                AccountingEntry::id($accountingEntry['id'])->transition('validate');

                // Store the created accounting entry ID back to the misc operation
                self::id($id)->update(['accounting_entry_id' => $accountingEntry['id']]);
            }
            catch(\Exception $e) {
                trigger_error("APP::Unexpected error while creating accounting entry: " . $e->getMessage(), EQ_REPORT_WARNING);
                // rollback
                if(isset($accountingEntry)) {
                    AccountingEntry::id($accountingEntry['id'])->delete(true);
                }
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

        if(isset($event['bank_account_id'])) {
            $bankAccount = CondominiumBankAccount::id($event['bank_account_id'])->read(['available_balance'])->first();
            if($bankAccount) {
                $result['account_available_balance'] = $bankAccount['available_balance'];
            }
        }
        elseif(array_key_exists('bank_account_id', $event)) {
            $result['account_available_balance'] = 0.0;
        }

        if(isset($event['counterpart_bank_account_id'])) {
            $bankAccount = CondominiumBankAccount::id($event['counterpart_bank_account_id'])->read(['available_balance'])->first();
            if($bankAccount) {
                $result['counterpart_available_balance'] = $bankAccount['available_balance'];
            }
        }
        elseif(array_key_exists('counterpart_bank_account_id', $event)) {
            $result['counterpart_available_balance'] = 0.0;
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

    public static function canupdate($self, $values) {
        $self->read(['status', 'accounting_entry_id']);

        foreach($self as $id => $moneyTransfer) {
            if($moneyTransfer['status'] === 'posted') {
                if(!$moneyTransfer['accounting_entry_id'] && isset($values['accounting_entry_id']) && count($values) == 1) {
                    // allow first setting of accounting entry
                    continue;
                }
                return [
                    'status' => [
                        'not_allowed' => "Money transfer cannot be updated once posted."
                    ]
                ];
            }

        }
        return parent::canupdate($self, $values);
    }

    protected static function onafterPublish($self) {
        $self->do('create_fundings');
    }

    protected static function onbeforePost($self) {
        $self->do('generate_accounting_entry');
    }

}
