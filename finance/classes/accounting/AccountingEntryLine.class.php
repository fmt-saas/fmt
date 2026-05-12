<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use equal\orm\Model;

class AccountingEntryLine extends Model {

    public static function getName() {
        return "Accounting Record";
    }

    public static function getDescription() {
        return "Accounting records map invoices lines into movements in the accounting journals.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry line refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true,
                'default'           => 'defaultCondoId'
            ],

            'name' => [
                'type'              => 'alias',
                'alias'             => 'id'
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Explanation or internal notes about the operation.'
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Accounting entry the line relates to.",
                'ondelete'          => 'cascade',
                'dependents'        => ['journal_id', 'entry_date', 'entry_number', 'entry_reference','fiscal_year_id', 'fiscal_period_id']
            ],

            'journal_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => "Accounting journal the entry relates to.",
                'relation'          => ['accounting_entry_id' => 'journal_id'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the entry relates to.",
                'relation'          => ['accounting_entry_id' => 'fiscal_year_id'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the entry relates to (from entry_date).",
                'relation'          => ['accounting_entry_id' => 'fiscal_period_id'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'entry_date' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'usage'             => 'date/plain',
                'relation'          => ['accounting_entry_id' => 'entry_date'],
                'description'       => 'The date on which the transaction is recorded in the accounting system and affects the fiscal period.',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'entry_number' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['accounting_entry_id' => 'entry_number'],
                'description'       => 'Number of the parent accounting entry.',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'entry_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['accounting_entry_id' => 'entry_reference'],
                'description'       => 'Info to display as reference for accounting entry.',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            // #deprecated - this field should not be used (direct link is between Funding and AccountingEntryLine)
            'funding_id' => [
                'deprecated'        => true,
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'ondelete'          => null,
                'description'       => 'Funding to which the accounting entry is linked, if any.'
            ],

            'matching_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Matching',
                'ondelete'          => null,
                'description'       => 'Matching (lettering) to which the accounting entry is linked, if any.',
                'onupdate'          => 'onupdateMatchingId',
                'dependents'        => ['matching_level']
            ],

            'matching_level' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                // 'usage'             => 'icon',
                'selection'         => [
                    'none',
                    'part',
                    'full'
                ],
                'function'          => 'calcMatchingLevel',
                'store'             => true,
                'instant'           => true
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false]],
                'dependents'        => ['account_code', 'account_operation_assignment', 'account_class']
            ],

            'account_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code of the related accounting account.",
                'relation'          => ['account_id' => 'code'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'account_operation_assignment' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Operation assignment of related account.",
                'relation'          => ['account_id' => 'operation_assignment'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'account_class' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The accounting class of the account (as a number from 1 to 7).",
                'relation'          => ['account_id' => 'account_class'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'debit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be debited on the account.',
                'default'           => 0.0,
                'dependents'        => ['accounting_entry_id' => 'debit'],
                'onupdate'          => 'onupdateDebit'
            ],

            'credit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be credited on the account.',
                'default'           => 0.0,
                'dependents'        => ['accounting_entry_id' => 'credit'],
                'onupdate'          => 'onupdateCredit'
            ],

            'bank_statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'description'       => 'Bank Statement line the entry line relates to, if any.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'sale_invoice_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\SaleInvoiceLine',
                'description'       => 'Invoice line the entry line relates to, if any.',
                'help'              => 'This is necessary for retrieving the invoice line corresponding to the entry line and, further, the apportionment and ratio to use for expense statement.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'purchase_invoice_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\accounting\invoice\PurchaseInvoiceLine',
                'description'       => 'Invoice line the entry line relates to, if any.',
                'help'              => 'This is necessary for retrieving the invoice line corresponding to the entry line and, further, the apportionment and ratio to use for expense statement.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'misc_operation_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\MiscOperationLine',
                'description'       => 'Misc Operation line the entry line relates to, if any.',
                'help'              => 'This is necessary for retrieving the invoice line corresponding to the entry line and, further, the apportionment and ratio to use for expense statement.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'is_visible' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the entry as visible.',
                'help'              => 'In some situations, an accounting entry should not be shown or presented in some views or documents.
                    This flag helps for this purpose. However, even if not visible, a validated accounting entry always impacts the Balance.',
                'default'           => true
            ],

            'is_cleared' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the record as cleared (reinvoiced or processed).',
                'default'           => false
            ],

            'is_carry_forward' => [
                'type'              => 'boolean',
                'description'       => 'The accounting entry is a report and is handled depending on the status of its fiscal year.',
                'help'              => 'Carry forward entries are balanced between a year and the one that follows (Y & Y+1).',
                'default'           => false
            ],

            'is_closing' => [
                'type'              => 'boolean',
                'description'       => 'The accounting entry is a report and is handled depending on the status of its fiscal year.',
                'help'              => 'Closing entries are balanced between a year and the one that follows (Y & Y+1).',
                'default'           => false
            ],

            'is_posted' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the line has been applied to the cumulative account balance projection.',
                'help'              => 'Technical flag used by the accounting engine to ensure idempotent posting of balance changes.
                    It allows distinguishing between entries that have already impacted AccountBalanceChange and those that have not, independently of the entry status.
                    This distinction is necessary when having symmetrical `reversed` entries on an accounting document.',
                'default'           => false
            ],

            // #memo - this field is only changed by parent Accounting Entry and should remain synced
            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'validated',
                    'reversed'
                ],
                'default'           => 'pending'
            ]

        ];
    }

    public static function getActions() {
        return [
            'attempt_match' => [
                'description'   => 'Attempts to find a suitable Matching and, if so, links the entry line to it.',
                'policies'      => [/* */],
                'function'      => 'doAttemptMatch'
            ],
            'match_with_matching' => [
                'description'   => 'Arbitrary link the entry line to a given Matching.',
                'policies'      => [/* */],
                'function'      => 'doMatchWithMatching'
            ],
            'refresh_matching_level' => [
                'description'   => 'Update status according to currently paid amount.',
                'policies'      => [],
                'function'      => 'doRefreshMatchingLevel'
            ]
        ];
    }

    protected static function calcAccountClass($self) {
        $result = [];
        $self->read(['account_code']);
        foreach($self as $id => $accountingEntryLine) {
            if($accountingEntryLine['account_code']) {
                $result[$id] = intval(substr($accountingEntryLine['account_code'], 0, 1));
            }
        }
        return $result;
    }

    protected static function doMatchWithMatching($self, $values) {
        if(!isset($values['matching_id'])) {
            throw new \Exception('missing_mandatory_matching_id', EQ_ERROR_INVALID_PARAM);
        }

        $matching = Matching::id($values['matching_id'])
            ->first();

        if(!$matching) {
            throw new \Exception('provided_matching_not_found', EQ_ERROR_INVALID_PARAM);
        }

        $self->update(['matching_id' => $matching['id']]);
    }

    protected static function doAttemptMatch($self) {
        $self->read(['condo_id', 'matching_id', 'account_id' => ['account_type'], 'debit', 'credit']);
        foreach($self as $id => $accountingEntryLine) {
            // skip records that are already matched (matching cancellation must be done manually)
            if($accountingEntryLine['matching_id']) {
                continue;
            }
            if($accountingEntryLine['account_id']['account_type'] === 'B') {
                // If a Matching exists on this account and the delta matches the amount of the line, then assign the entry to this Matching
                $amount = round($accountingEntryLine['debit'] - $accountingEntryLine['credit'], 2);
                $matching = Matching::search([
                        ['condo_id', '=', $accountingEntryLine['condo_id']],
                        ['accounting_account_id', '=', $accountingEntryLine['account_id']['id']],
                        ['is_balanced', '=', false],
                        ['balance_amount', '=', $amount]
                    ],
                    [
                        'sort' => ['created' => 'desc']
                    ])
                    ->first();

                if(!$matching) {
                    $counterpartAccountingEntryLine = AccountingEntryLine::search([
                            [
                                ['condo_id', '=',  $accountingEntryLine['condo_id']],
                                ['account_id', '=', $accountingEntryLine['account_id']['id']],
                                ['matching_id', '=', null],
                                ['debit', '=', $accountingEntryLine['credit']],
                                ['debit', '>', 0],
                                ['id', '<>', $id]
                            ],
                            [
                                ['condo_id', '=',  $accountingEntryLine['condo_id']],
                                ['account_id', '=', $accountingEntryLine['account_id']['id']],
                                ['matching_id', '=', null],
                                ['credit', '=', $accountingEntryLine['debit']],
                                ['credit', '>', 0],
                                ['id', '<>', $id]
                            ]
                        ])
                        ->first();

                    if($counterpartAccountingEntryLine) {
                        $matching = Matching::create([
                                'condo_id'              => $accountingEntryLine['condo_id'],
                                'accounting_account_id' => $accountingEntryLine['account_id']['id']
                            ])
                            ->first();

                        self::id($counterpartAccountingEntryLine['id'])->update(['matching_id' => $matching['id']]);
                    }
                }

                if($matching) {
                    self::id($id)->update(['matching_id' => $matching['id']]);
                }
            }
        }
    }

    protected static function doRefreshMatchingLevel($self) {
        $self->update(['matching_level' => null]);
    }

    public static function defaultCondoId($values) {
        $result = null;
        if(isset($values['accounting_entry_id'])) {
            $accountingEntry = AccountingEntry::id($values['accounting_entry_id'])->read(['condo_id'])->first();
            if($accountingEntry) {
                $result = $accountingEntry['condo_id'];
            }
        }
        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['accounting_entry_id' => ['status']]);
        $allowed_fields = ['status', 'description', 'matching_id', 'matching_level', 'is_posted'];
        $updated_fields = array_keys($values);

        if(count(array_diff($updated_fields, $allowed_fields)) > 0) {
            foreach($self as $id => $accountingEntryLine) {
                if(in_array($accountingEntryLine['accounting_entry_id']['status'], ['reversed', 'validated'])) {
                    return ['accounting_entry_id' => ['not_allowed' => 'Accounting entry line cannot be modified once entry is validated.']];
                }
            }
        }
        return parent::canupdate($self);
    }

    protected static function onupdateCredit($self) {
        $self->read(['matching_id']);
        foreach($self as $id => $accountingEntryLine) {
            if($accountingEntryLine['matching_id']) {
                Matching::id($accountingEntryLine['matching_id'])->do('refresh_matching_level');
            }
        }
    }

    protected static function onupdateDebit($self) {
        $self->read(['matching_id']);
        foreach($self as $id => $accountingEntryLine) {
            if($accountingEntryLine['matching_id']) {
                Matching::id($accountingEntryLine['matching_id'])->do('refresh_matching_level');
            }
        }
    }

    protected static function onupdateMatchingId($self) {
        $self->read(['matching_id']);
        foreach($self as $id => $accountingEntryLine) {
            if($accountingEntryLine['matching_id']) {
                Matching::id($accountingEntryLine['matching_id'])->do('refresh_matching_level');
            }
            else {
                // matching_id was reset to null
                self::id($id)->do('refresh_matching_level');
            }
        }
    }

    protected static function onbeforeupdate($self, $values) {
        // matching_id is about to be reset to null
        if(array_key_exists('matching_id', $values) && !$values['matching_id']) {
            $self->read(['matching_id']);
            foreach($self as $id => $accountingEntryLine) {
                // matching will remove itself if empty
                Matching::id($accountingEntryLine['matching_id'])->do('check_emptiness');
            }
        }
    }

    protected static function calcMatchingLevel($self) {
        $result = [];
        $self->read(['accounting_entry_id' => ['status'], 'matching_id' => ['is_balanced']]);
        foreach($self as $id => $accountingEntryLine) {
            if(!$accountingEntryLine['accounting_entry_id'] || $accountingEntryLine['accounting_entry_id']['status'] !== 'validated') {
                continue;
            }
            $result[$id] = 'none';
            if(!$accountingEntryLine['matching_id']) {
                continue;
            }
            $result[$id] = 'part';
            if($accountingEntryLine['matching_id']['is_balanced']) {
                $result[$id] = 'full';
            }
        }
        return $result;
    }

    public function getIndexes(): array {
        return [
            // ledger_index
            ['condo_id','account_id','entry_date','id'],
            // `journal_index`
            ['condo_id','journal_id','entry_date'],
            // `supplier_index`
            ['condo_id', 'suppliership_id', 'entry_date'],
            // `ownership_index`
            ['condo_id', 'ownership_id', 'entry_date'],
            ['condo_id', 'account_id', 'matching_id']
        ];
    }
}