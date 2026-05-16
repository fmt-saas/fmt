<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;

use equal\orm\Model;
use fmt\setting\Setting;

class AccountingEntry extends Model {

    public static function getName() {
        return "Accounting entry";
    }

    public static function getDescription() {
        return "Accounting entries are linked to accounting documents and hold a series of lines for recording the subsequent movements in the accounting books.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                //'readonly'          => true
                'default'           => 'defaultCondoId'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short optional description of the entry.',
                'multilang'         => true,
                'onupdate'          => 'onupdateDescription'
            ],

            'journal_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => "Accounting journal the entry relates to.",
                'required'          => true,
                'domain'            => [['journal_type', '<>', 'LEDG'], ['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'sub_journal_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => "Accounting journal the entry relates to.",
                'help'              => "We need this to be able to view either all entries from a parent journal, or only entries from a specific sub journal.",
                'visible'           => ['journal_id', '<>', null],
                'domain'            => [
                    ['journal_type', '=', 'BANK'],
                    ['parent_journal_id', '=', 'object.journal_id'],
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null]
                ]
            ],

            'entry_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'The date on which the transaction is recorded in the accounting system and affects the fiscal period.',
                'help'              => 'This should always match the selected period, and is necessary in case of period re-assignment.',
                'required'          => true,
                'dependents'        => ['fiscal_year_id', 'fiscal_period_id']
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the entry relates to.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'function'          => 'calcFiscalYear',
                'store'             => true,
                'instant'           => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the entry relates to (from entry_date).",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'help'              => "Period is automatically assigned based on entry date.",
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true
            ],

            'is_temp' => [
                'type'              => 'boolean',
                'description'       => 'The accounting entry is a temporary report and cannot be modified nor receive an entry number.',
                'help'              => 'Temporary reports are made when a fiscal year is preclosed. While temporary report entries are present, a fiscal year cannot be closed.',
                'default'           => false,
                'dependents'        => ['name']
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

            'entry_number' => [
                'type'              => 'string',
                'description'       => 'Unique code for entry identification.',
                'dependents'        => ['name', 'debit', 'credit', 'entry_reference'],
                'help'              => 'Entry number is automatically assigned after validation, and cannot be changed afterwards.',
                'onupdate'          => 'onupdateEntryNumber'
            ],

            'entry_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'function'          => 'calcEntryReference',
                'description'       => 'Info to display as reference (links to accoutning doc).'
            ],

            'debit' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total debited amount from all lines.',
                'function'          => 'calcDebit',
                'store'             => true,
                'readonly'          => true
            ],

            'credit' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total credited amount from all lines.',
                'function'          => 'calcCredit',
                'store'             => true,
                'readonly'          => true
            ],

            'is_balanced' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'An entry is balanced if the total debited amount equals the total credited amount.',
                'function'          => 'calcIsBalanced',
                'store'             => false
            ],

            'has_cleared_lines' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag telling if at least one of the entry lines has been cleared.',
                'function'          => 'calcHasClearedLines',
                'store'             => false
            ],

            'entry_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntryLine',
                'foreign_field'     => 'accounting_entry_id',
                'description'       => "Lines of the accounting entry.",
                'dependents'        => ['debit', 'credit'],
                'ondetach'          => 'delete'
            ],


            // #memo - do not use this field
            'is_visible' => [
                'deprecated'        => true,
                'type'              => 'boolean',
                'description'       => 'Flag marking the entry as visible.',
                'help'              => 'In some situations, an accounting entry should not be shown or presented in some views or documents.
                    This flag helps for this purpose. However, even if not visible, a validated accounting entry always impacts the Balance.',
                'default'           => true
            ],

            'reversed_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Reverse accounting entry voiding the current one, if any.",
                'visible'           => ['status', '=', 'reversed']
            ],

            'origin_object_class' => [
                'type'              => 'string',
                'description'       => 'Entity class that the entry originates from.'
            ],

            'origin_object_id' => [
                'type'              => 'integer',
                'description'       => 'Object identifier, as a complement to `origin_object_class`.',
                'help'              => 'Together origin_object_class and origin_object_id reference the accounting document the entry is linked to.'
            ],

            /*
                Since we cannot use origin_object_class & origin_object_id in views
                following fields points directly to the targeted Object
            */

            'purchase_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Invoice the accounting entry is related to.',
                'ondelete'          => 'null'
            ],

            'sale_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\SaleInvoice',
                'description'       => 'Invoice the accounting entry is related to.',
                'ondelete'          => 'null'
            ],

            'misc_operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\MiscOperation',
                'description'       => 'Miscellaneous Operation the accounting entry is related to.',
                'ondelete'          => 'null'
            ],

            'bank_statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'description'       => 'Bank Statement line the entry relates to, if any.',
                'ondelete'          => 'null',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'bank_statement_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatement',
                'description'       => 'Bank statement the entry relates to, if any.',
                'ondelete'          => 'null',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'store'             => true,
                'instant'           => true,
                'relation'          => ['bank_statement_line_id' => ['bank_statement_id']],
                'visible'           => ['bank_statement_line_id', '<>', null]
            ],

            'status' => [
                'type'              => 'string',
                'usage'             => 'text/plain:20',
                'selection'         => [
                    'pending',
                    'planned',
                    'validated',
                    'reversed'
                ],
                'default'           => 'pending',
                'description'       => 'Status of the accounting entry.',
                'help'              => 'Pending entries are not actual accounting entries but `drafts` that can be created and modified without impacting Balance.
                    Once an accounting entry has been validated, it cannot be removed. It can however, be cancelled through a reverse entry.
                    Planned entries are system entries and can never be removed manually (only through source document cancellation).',
                'dependents'        => ['name']
            ]

        ];
    }

    public function getIndexes(): array {
        return [
            ['condo_id', 'purchase_invoice_id', 'status'],
            ['condo_id', 'bank_statement_line_id', 'status'],
            ['condo_id', 'misc_operation_id', 'status'],
        ];
    }

    public static function getActions() {
        return [
            'cancel' => [
                'description'   => 'Delete the proforma and set receivables statuses back to pending.',
                'help'          => 'A fiscal year can be opened before the previous one is definitely closed.',
                'policies'      => ['can_cancel'],
                'function'      => 'doCancel'
            ],
            'attempt_match' => [
                'description'   => 'Attempts to find a suitable Matching and, if so, links the entry to it.',
                'policies'      => [/* */],
                'function'      => 'doAttemptMatch'
            ],
            'match_with_matching' => [
                'description'   => 'Arbitrary link entry lines to a given Matching.',
                'policies'      => [/* */],
                'function'      => 'doMatchWithMatching'
            ],
            'update_balance_change' => [
                'description'   => 'Update the AccountBalanceChange objects according to the entry.',
                'policies'      => [/* */],
                'function'      => 'doUpdateBalanceChange'
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Draft entry, still waiting to be completed for validation.',
                'icon'        => 'draw',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the accounting entry status to `validated`.',
                        'policies'    => [
                            'is_valid',
                            'can_validate'
                        ],
                        'onbefore'  => 'onbeforeValidate',
                        'onafter'   => 'onafterValidate',
                        'status'    => 'validated'
                    ],
                    'plan' => [
                        'description' => 'Update the accounting entry status to `planned`.',
                        'policies'    => [
                            'is_valid'
                        ],
                        'status'    => 'planned'
                    ]
                ]
            ],
            'planned' => [
                'description' => 'Scheduled system entry, waiting for entry date to be automatically validated.',
                'icon'        => 'hourglass_top',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the accounting entry status to `validated`.',
                        'policies'    => [
                            'is_valid'
                        ],
                        'onafter'   => 'onafterValidate',
                        'status'    => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon'        => 'drive_file_rename_outline',
                'transitions' => [
                        // #memo - cancellation is done using action 'cancel'
                ]
            ]
        ];
    }


    public static function defaultCondoId($values) {
        $result = null;
        if(isset($values['journal_id'])) {
            $journal = Journal::id($values['journal_id'])->read(['condo_id'])->first();
            if(isset($journal['condo_id'])) {
                $result = $journal['condo_id'];
            }
        }
        return $result;
    }

    /**
     * Make sure final entry number is replicated in accounting entry lines.
     */
    protected static function onupdateEntryNumber($self) {
        $self->read(['entry_lines_ids']);
        foreach($self as $id => $accountingEntry) {
            AccountingEntryLine::ids($accountingEntry['entry_lines_ids'])->read(['entry_number']);
        }
    }

    // #memo - matching is performed at AccountingEntryLne (records) level
    protected static function doAttemptMatch($self) {
        $self->read(['entry_lines_ids']);
        foreach($self as $id => $accountingEntry) {
            AccountingEntryLine::ids($accountingEntry['entry_lines_ids'])->do('attempt_match');
        }
    }

    // #memo - matching is performed at AccountingEntryLine (records) level
    protected static function doMatchWithMatching($self, $values) {
        if(!isset($values['matching_id'])) {
            throw new \Exception('missing_mandatory_matching_id', EQ_ERROR_INVALID_PARAM);
        }

        $matching = Matching::id($values['matching_id'])
            ->read(['accounting_account_id'])
            ->first();

        if(!$matching) {
            throw new \Exception('provided_matching_not_found', EQ_ERROR_INVALID_PARAM);
        }

        $self->read(['entry_lines_ids' => ['account_id']]);
        foreach($self as $id => $accountingEntry) {
            foreach($accountingEntry['entry_lines_ids'] as $accounting_entry_line_id => $accountingEntryLine) {
                if($accountingEntryLine['account_id'] == $matching['accounting_account_id']) {
                    AccountingEntryLine::id($accounting_entry_line_id)->do('match_with_matching', ['matching_id' => $matching['id']]);
                }
            }
        }
    }

    protected static function onbeforeValidate($self) {
        $self->read([
            'condo_id',
            'entry_date',
            'entry_lines_ids' => [
                'account_id',
                'description',
                'debit',
                'credit',
                'funding_id',
                'bank_statement_line_id'    => ['id', 'description'],
                'sale_invoice_line_id'      => ['id', 'description'],
                'purchase_invoice_line_id'  => ['id', 'description'],
                'misc_operation_line_id'    => ['id', 'description']
            ]
        ]);

        foreach($self as $id => $accountingEntry) {

/*
// #memo - we cannot do that since Matching is made on AccountingEntryLine (according to debit & credit fields) and matching_id might already be set
 // also, AccountingEntryLine could be referenced in Payment.accounting_entry_line_id and Funding.accounting_entry_line_id
            // consolidate entry lines by account (to avoid several lines targeting same account_id)
            $grouped_lines = [];

            foreach($accountingEntry['entry_lines_ids'] as $line_id => $line) {
                $account_id = $line['account_id'] ?? null;

                if(!$account_id) {
                    continue;
                }

                $bank_statement_line_id = $line['bank_statement_line_id']['id'] ?? 0;
                $sale_invoice_line_id = $line['sale_invoice_line_id']['id'] ?? 0;
                $purchase_invoice_line_id = $line['purchase_invoice_line_id']['id'] ?? 0;
                $misc_operation_line_id = $line['misc_operation_line_id']['id'] ?? 0;

                $link_key = implode(':', [
                    $bank_statement_line_id,
                    $sale_invoice_line_id,
                    $purchase_invoice_line_id,
                    $misc_operation_line_id
                ]);

                $group_key = $account_id . '-' . $link_key;

                if(!isset($grouped_lines[$group_key])) {
                    $description = $line['description'] ?? null;

                    if(isset($line['purchase_invoice_line_id']['description']) && strlen((string) $line['purchase_invoice_line_id']['description']) > 0) {
                        $description = $line['purchase_invoice_line_id']['description'];
                    }
                    elseif(isset($line['sale_invoice_line_id']['description']) && strlen((string) $line['sale_invoice_line_id']['description']) > 0) {
                        $description = $line['sale_invoice_line_id']['description'];
                    }
                    elseif(isset($line['misc_operation_line_id']['description']) && strlen((string) $line['misc_operation_line_id']['description']) > 0) {
                        $description = $line['misc_operation_line_id']['description'];
                    }
                    elseif(isset($line['bank_statement_line_id']['communication']) && strlen((string) $line['bank_statement_line_id']['description']) > 0) {
                        $description = $line['bank_statement_line_id']['description'];
                    }

                    $grouped_lines[$group_key] = [
                        'line_ids'    => [],
                        'debit'       => 0.0,
                        'credit'      => 0.0,
                        'fields' => [
                            'accounting_entry_id'        => $id,
                            'account_id'                 => $account_id,
                            'description'                => $description,
                            'funding_id'                 => $line['funding_id'] ?? null,
                            'bank_statement_line_id'     => $bank_statement_line_id ?: null,
                            'sale_invoice_line_id'       => $sale_invoice_line_id ?: null,
                            'purchase_invoice_line_id'   => $purchase_invoice_line_id ?: null,
                            'misc_operation_line_id'     => $misc_operation_line_id ?: null
                        ]
                    ];
                }

                $grouped_lines[$group_key]['line_ids'][] = $line_id;
                $grouped_lines[$group_key]['debit'] = round($grouped_lines[$group_key]['debit'] + (float) $line['debit'], 4);
                $grouped_lines[$group_key]['credit'] = round($grouped_lines[$group_key]['credit'] + (float) $line['credit'], 4);
            }

            $lines_to_delete = [];
            $lines_to_create = [];

            foreach($grouped_lines as $group) {
                $line_ids = $group['line_ids'];
                $debit = round($group['debit'], 2);
                $credit = round($group['credit'], 2);
                $net_amount = round($debit - $credit, 2);

                if(abs($net_amount) < 0.01) {
                    $lines_to_delete = array_merge($lines_to_delete, $line_ids);
                    continue;
                }

                if(count($line_ids) <= 1) {
                    continue;
                }

                $lines_to_create[] = array_merge(
                    $group['fields'],
                    [
                        'debit'  => ($net_amount > 0)? $net_amount : 0.0,
                        'credit' => ($net_amount < 0)? abs($net_amount) : 0.0
                    ]
                );

                $lines_to_delete = array_merge($lines_to_delete, $line_ids);
            }

            // #memo - there is a slight risk of inconsistency here in case the create/delete operations are interrupted
            foreach($lines_to_create as $values) {
                AccountingEntryLine::create($values);
            }

            if(!empty($lines_to_delete)) {
                AccountingEntryLine::ids(array_values(array_unique($lines_to_delete)))->delete(true);
            }
*/
            // #memo - the encoding of the purchase invoices is non chronological. We must maintain the sequence, but cannot force dates sequence without losing information.

            self::id($id)
                ->update([
                    'entry_number'  => self::computeEntryNumber($id)
                ]);
        }
    }


    /**
     * The entry has been validated (irreversible transition).
     * This method triggers the update of the related current balance and the fiscal period (based on the entry date).
     */
    public static function onafterValidate($self) {
        // append accounting entry to current balance
        $self->read([
                'condo_id',
                'entry_date',
                'journal_id',
                'sub_journal_id',
                'fiscal_period_id' => ['id', 'date_from'],
                'fiscal_year_id'   => ['current_balance_id'],
                'entry_lines_ids'  => ['account_id', 'debit', 'credit']
            ]);

        foreach($self as $id => $accountingEntry) {

            $accountingEntry['entry_lines_ids']->update(['status' => 'validated']);

            // #memo - we cannot update the Balance directly to avoid concurrent changes: always use BalanceUpdateRequest
            /*
            BalanceUpdateRequest::create([
                    'condo_id'              => $accountingEntry['condo_id'],
                    'balance_id'            => $accountingEntry['fiscal_year_id']['current_balance_id'],
                    'accounting_entry_id'   => $id
                ]);
            */

            // #todo - temporary for testing - to remove once cron handling balanceupdate request will be running
            /*
            // #memo - this has been replaced with AccountBalanceChange mechanism
            foreach($accountingEntry['entry_lines_ids'] ?? [] as $entry_line_id => $entryLine) {
                CurrentBalance::id($accountingEntry['fiscal_year_id']['current_balance_id'])
                    ->do('update_account', [
                        'account_id' => $entryLine['account_id'],
                        'debit'      => $entryLine['debit'],
                        'credit'     => $entryLine['credit']
                    ]);
            }
            */
        }

        $self
            // force refresh Entry name
            ->update(['name' => null])
            ->do('update_balance_change');
    }

    /**
     * Policy ensures: status == 'validated' AND reversed_entry_id is null AND fiscal year is not closed, etc.
     */
    protected static function doCancel($self) {

        $self->read([
            'condo_id',
            'fiscal_year_id',
            'journal_id',
            'entry_date',
            'purchase_invoice_id',
            'sale_invoice_id',
            'misc_operation_id',
            'bank_statement_line_id',
            'bank_statement_id',
            'entry_lines_ids' => [
                'account_id', 'debit', 'credit',
                'description',
                'sale_invoice_line_id',
                'purchase_invoice_line_id',
                'misc_operation_line_id'
            ],
        ]);

        foreach ($self as $id => $entry) {

            // 1) Create reversal entry (B)
            $reversal = self::create([
                    'condo_id'                  => $entry['condo_id'],
                    'journal_id'                => $entry['journal_id'],
                    'fiscal_year_id'            => $entry['fiscal_year_id'],
                    // #memo #important - same date for strict cancellation
                    'entry_date'                => $entry['entry_date'],
                    'purchase_invoice_id'       => $entry['purchase_invoice_id'],
                    'sale_invoice_id'           => $entry['sale_invoice_id'],
                    'misc_operation_id'         => $entry['misc_operation_id'],
                    'bank_statement_line_id'    => $entry['bank_statement_line_id'],
                    'bank_statement_id'         => $entry['bank_statement_id']
                ])
                ->first();

            // 2) Create reversal lines (swap debit/credit)
            foreach ($entry['entry_lines_ids'] ?? [] as $line) {
                AccountingEntryLine::create([
                    'condo_id'                  => $entry['condo_id'],
                    'accounting_entry_id'       => $reversal['id'],
                    'account_id'                => $line['account_id'],
                    'debit'                     => $line['credit'],
                    'credit'                    => $line['debit'],
                    'description'               => $line['description'],
                    'sale_invoice_line_id'      => $line['sale_invoice_line_id'],
                    'purchase_invoice_line_id'  => $line['purchase_invoice_line_id'],
                    'misc_operation_line_id'    => $line['misc_operation_line_id']
                ]);
            }

            // 3) Validate reversal (will post lines once => update AccountBalanceChange)
            self::id($reversal['id'])
                ->transition('validate');

            // 4) Link original to reversal
            self::id($id)
                ->update([
                    'reversed_entry_id'  => $reversal['id'],
                    'status'            => 'reversed'
                ]);

            // 5) Link reversal to original
            self::id($reversal['id'])
                ->update([
                    'reversed_entry_id'  => $id,
                    'status'            => 'reversed'
                ]);

            // 6) Mark all lines as reversed
            AccountingEntryLine::search(['accounting_entry_id', 'in', [$id, $reversal['id']]])
                ->update(['status' => 'reversed']);

        }
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that an accounting entry is balanced.',
                'function'    => 'policyIsValid'
            ],
            'can_validate' => [
                'description' => 'Verifies that an accounting entry can be validated.',
                'function'    => 'policyCanValidate'
            ],
            'can_cancel' => [
                'description' => 'Verifies that an accounting entry can be cancelled (validated and not already cancelled).',
                'function'    => 'policyCanCancel'
            ]
        ];
    }

    public static function policyCanValidate($self): array {
        $result = [];
        $self->read(['status', 'entry_lines_ids' => ['status']]);
        foreach($self as $id => $accountingEntry) {
            if(!in_array($accountingEntry['status'], ['pending', 'planned'])) {
                $result[$id] = [
                        'invalid_entry_status' => 'Accounting entry cannot be validated.'
                    ];
                continue;
            }
            foreach($accountingEntry['entry_lines_ids'] as $entry_line_id => $accountingEntryLine) {
                if($accountingEntryLine['status'] !== 'pending') {
                    $result[$id] = [
                            'invalid_entry_lines' => 'At least one accounting entry line already posted.'
                        ];
                    continue 2;
                }
            }
        }
        return $result;
    }

    public static function policyIsValid($self): array {
        $result = [];
        $self->read(['is_balanced', 'entry_lines_ids']);
        foreach($self as $id => $accountingEntry) {
            if(!$accountingEntry['is_balanced']) {
                $result[$id] = [
                        'invalid_entry' => 'Accounting entry must be balanced.'
                    ];
                continue;
            }
            if(empty($accountingEntry['entry_lines_ids'])) {
                $result[$id] = [
                        'invalid_entry' => 'Accounting entry cannot be empty.'
                    ];
                continue;
            }
        }
        return $result;
    }

    protected static function policyCanCancel($self): array {
        $result = [];

        $self->read([
            'status',
            'reversed_entry_id',
            'fiscal_year_id' => ['status'],
            'fiscal_period_id' => ['status'],
            'entry_lines_ids'
        ]);

        foreach ($self as $id => $entry) {

            $errors = [];

            // 1) Must be validated
            if(($entry['status'] ?? null) !== 'validated') {
                $errors['invalid_status'] = 'Accounting entry must be validated.';
            }

            // 2) Must not already be cancelled / linked to a reversal
            if(!empty($entry['reversed_entry_id'])) {
                $errors['already_cancelled'] = 'Accounting entry has already been cancelled (reversal entry exists).';
            }

            // 3) Fiscal year must allow changes
            // #memo - we do not test preclosed status here
            if(in_array($entry['fiscal_year_id']['status'], ['closed'], true)) {
                $errors['closed_fiscal_year'] = 'Fiscal year is closed; cancellation is not allowed.';
            }

            // 4) Fiscal period must allow changes
            // #memo - we do not test preclosed status here
            if(in_array($entry['fiscal_period_id']['status'], ['closed'], true)) {
                $errors['fiscal_period_id'] = 'Fiscal period is closed; cancellation is not allowed.';
            }

            // 5) Optional: entry must contain at least one line
            if(empty($entry['entry_lines_ids']) || count($entry['entry_lines_ids']) === 0) {
                $errors['no_lines'] = 'Accounting entry has no lines.';
            }

            if(!empty($errors)) {
                $result[$id] = $errors;
            }
        }

        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['status', 'is_temp', 'entry_number']);
        foreach($self as $id => $accountingEntry) {
            if($accountingEntry['status'] === 'pending') {
                $result[$id] = '(draft)';
            }
            // #deprecated - is_temp should no longer be used
            elseif($accountingEntry['is_temp']) {
                $result[$id] = '(temp)';
            }
            else {
                $result[$id] = $accountingEntry['entry_number'];
            }
        }
        return $result;
    }

    protected static function calcEntryReference($self) {
        $result = null;

        $self->read([
                'status',
                'entry_number',
                'misc_operation_id',
                'purchase_invoice_id'   => ['invoice_number'],
                'sale_invoice_id'       => ['invoice_number'],
                'bank_statement_id'     => ['statement_number']
            ]);

        foreach($self as $id => $accountingEntry) {
            if($accountingEntry['status'] !== 'validated') {
                continue;
            }
            if(isset($accountingEntry['purchase_invoice_id'])) {
                $result[$id] = $accountingEntry['purchase_invoice_id']['invoice_number'];
            }
            elseif(isset($accountingEntry['sale_invoice_id'])) {
                $result[$id] = $accountingEntry['sale_invoice_id']['invoice_number'];
            }
            elseif(isset($accountingEntry['misc_operation_id'])) {
                // #todo - use operation_number once it will be available
                $result[$id] = preg_replace('/^[^\/]+\//', '', $accountingEntry['entry_number']);
            }
            elseif(isset($accountingEntry['bank_statement_id'])) {
                $result[$id] = $accountingEntry['bank_statement_id']['statement_number'];
            }
        }

        return $result;
    }

    /**
     * #memo - we need this value even if it can still change (i.e. accounting entry is not yet validated)
     */
    protected static function calcFiscalYear($self) {
        $result = [];
        $self->read(['status', 'condo_id', 'entry_date']);
        foreach($self as $id => $accountingEntry) {
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $accountingEntry['condo_id']],
                    ['date_from', '<=', $accountingEntry['entry_date']],
                    ['date_to', '>=', $accountingEntry['entry_date']]
                ])
                ->first();

            if($fiscalYear) {
                $result[$id] = $fiscalYear['id'];
            }
        }
        return $result;
    }

    /**
     * #memo - we need this value even if it can still change (i.e. accounting entry is not yet validated)
     */
    protected static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['status', 'entry_date', 'fiscal_year_id' => ['fiscal_periods_ids' => ['date_from', 'date_to']]]);
        foreach($self as $id => $entry) {
            foreach($entry['fiscal_year_id']['fiscal_periods_ids'] ?? [] as $period_id => $period) {
                if($entry['entry_date'] >= $period['date_from'] && $entry['entry_date'] <= $period['date_to']) {
                    $result[$id] = $period_id;
                    break;
                }
            }
        }
        return $result;
    }

    private static function computeIsBalanced($entry_lines_ids) {
        $entry_lines = AccountingEntryLine::ids($entry_lines_ids)->read(['credit', 'debit']);
        $credit = 0;
        $debit = 0;
        foreach($entry_lines as $line_id => $line) {
            $credit += $line['credit'];
            $debit += $line['debit'];
        }
        return (abs($credit - $debit) < 0.01 && round($credit, 2) != 0.00);
    }

    protected static function calcIsBalanced($self) {
        $result = [];
        $self->read(['entry_lines_ids']);
        foreach($self as $id => $entry) {
            $result[$id] = self::computeIsBalanced($entry['entry_lines_ids']);
        }
        return $result;
    }

    protected static function calcHasClearedLines($self) {
        $result = [];
        $self->read(['entry_lines_ids' => ['is_cleared']]);
        foreach($self as $id => $entry) {
            $result[$id] = false;
            foreach($entry['entry_lines_ids'] as $line) {
                if($line['is_cleared']) {
                    $result[$id] = true;
                    break;
                }
            }
        }
        return $result;
    }

    protected static function calcDebit($self) {
        $result = [];
        $self->read(['status', 'entry_lines_ids' => ['debit']]);
        foreach($self as $id => $entry) {
            $result[$id] = 0.0;
            foreach($entry['entry_lines_ids'] as $line) {
                $result[$id] += $line['debit'];
            }
        }
        return $result;
    }

    protected static function calcCredit($self) {
        $result = [];
        $self->read(['status', 'entry_lines_ids' => ['credit']]);
        foreach($self as $id => $entry) {
            $result[$id] = 0.0;
            foreach($entry['entry_lines_ids'] as $line) {
                $result[$id] += $line['credit'];
            }
        }
        return $result;
    }

    private static function computeEntryNumber($id) {
        $result = null;
        $entry = self::id($id)
            ->read(['status', 'is_temp', 'entry_number',
                'condo_id'          => ['id', 'code'],
                'journal_id'        => ['code'],
                'sub_journal_id'    => ['code'],
                'fiscal_year_id'    => ['code'],
                'fiscal_period_id'  => ['code']
            ])
            ->first();

        if($entry['entry_number'] && strlen($entry['entry_number']) > 0) {
            return $entry['entry_number'];
        }

        if($entry['is_temp']) {
            return $result;
        }

        if(!isset($entry['fiscal_year_id'], $entry['fiscal_period_id'], $entry['journal_id'])) {
            return $result;
        }

        $format = Setting::get_value(
                'finance',
                'accounting',
                'accounting_entry.number_format',
                '%s{journal}/%02d{year}/%02d{period}/%05d{sequence}',
                [
                    'condo_id' => $entry['condo_id']['id']
                ]
            );

        $fiscal_year_code = $entry['fiscal_year_id']['code'];
        $fiscal_period_code = $entry['fiscal_period_id']['code'];
        $journal_code = $entry['journal_id']['code'];
        $condo_code = $entry['condo_id']['code'];

        if($entry['sub_journal_id']) {
            $journal_code = $entry['sub_journal_id']['code'];
        }

        $sequence = Setting::fetch_and_add(
                'finance',
                'accounting',
                "accounting_entry.sequence.{$fiscal_year_code}.{$fiscal_period_code}.{$journal_code}",
                1,
                [
                    'condo_id' => $entry['condo_id']['id']
                ]
            );

        if(!$sequence) {
            trigger_error("APP::missing mandatory finance.accounting.accounting_entry.sequence.{$fiscal_year_code}.{$fiscal_period_code}.{$journal_code} for condominium {$entry['condo_id']['id']}.", EQ_REPORT_ERROR);
            throw new \Exception('missing_mandatory_sequence', EQ_ERROR_INVALID_CONFIG);
        }

        $result = Setting::parse_format($format, [
                'year'      => $fiscal_year_code,
                'period'    => $fiscal_period_code,
                'journal'   => $journal_code,
                'condo'     => $condo_code,
                'sequence'  => $sequence
            ]);

        return $result;
    }

    protected static function doUpdateBalanceChange($self) {
        $self->read([
            'condo_id',
            'entry_date',
            'status',
            'entry_lines_ids' => ['id', 'account_id', 'debit', 'credit', 'is_posted']
        ]);

        foreach($self as $entry) {

            // #memo - when an entry goes from validated to reversed, this method has no effect since all lines have already been posted
            if(!in_array($entry['status'], ['validated', 'reversed'])) {
                continue;
            }

            $condo_id = $entry['condo_id'];
            $date     = $entry['entry_date'];

            /*
            * Aggregate deltas per account
            */
            $account_deltas = [];
            $posted_lines_ids  = [];

            foreach($entry['entry_lines_ids'] as $line) {

                if($line['is_posted']) {
                    continue;
                }

                $account_id = $line['account_id'];

                if(!isset($account_deltas[$account_id])) {
                    $account_deltas[$account_id] = [
                        'debit'  => 0.0,
                        'credit' => 0.0
                    ];
                }

                $account_deltas[$account_id]['debit']  += (float) $line['debit'];
                $account_deltas[$account_id]['credit'] += (float) $line['credit'];

                $posted_lines_ids[] = $line['id'];
            }

            /*
            * Apply one delta per account
            */
            foreach($account_deltas as $account_id => $delta) {

                $delta_debit  = $delta['debit'];
                $delta_credit = $delta['credit'];

                /*
                * Get previous balance (< date)
                */
                $previous_debit  = 0.0;
                $previous_credit = 0.0;

                $previous = AccountBalanceChange::search([
                            ['account_id', '=', $account_id],
                            ['condo_id', '=', $condo_id],
                            ['date', '<', $date]
                        ],
                        ['sort' => ['date' => 'desc'], 'limit' => 1]
                    )
                    ->read(['debit_balance', 'credit_balance'])
                    ->first();

                if($previous) {
                    $previous_debit  = $previous['debit_balance'];
                    $previous_credit = $previous['credit_balance'];
                }
                else {
                    $openingBalance = OpeningBalance::search([
                            ['condo_id', '=', $condo_id],
                            ['status', '=', 'validated']
                            // #memo - filtering on date is not necessary since it is not possible to create an entry on a non-open (or similar) fiscal year
                        ],
                        [
                            'sort'  => ['created' => 'desc'],
                            'limit' => 1
                        ]
                    )
                    ->first();

                    if($openingBalance) {
                        $openingLine = OpeningBalanceLine::search([
                                ['condo_id','=', $condo_id],
                                ['account_id', '=', $account_id],
                                ['balance_id','=', $openingBalance['id']]
                            ])
                            ->read(['account_id', 'debit', 'credit'])
                            ->first();

                        $previous_debit  = $openingLine ? $openingLine['debit']  : 0.0;
                        $previous_credit = $openingLine ? $openingLine['credit'] : 0.0;
                    }
                }

                /*
                * Get current balance (= date)
                */
                $current = AccountBalanceChange::search([
                            ['condo_id', '=', $condo_id],
                            ['account_id', '=', $account_id],
                            ['date', '=', $date]
                        ],
                        ['limit' => 1]
                    )
                    ->read(['id', 'debit_balance', 'credit_balance'])
                    ->first();

                if($current) {

                    $new_debit  = (float) $current['debit_balance']  + $delta_debit;
                    $new_credit = (float) $current['credit_balance'] + $delta_credit;

                    AccountBalanceChange::id($current['id'])->update([
                        'debit_balance'  => round($new_debit, 2),
                        'credit_balance' => round($new_credit, 2)
                    ]);

                }
                else {

                    $new_debit  = $previous_debit  + $delta_debit;
                    $new_credit = $previous_credit + $delta_credit;

                    AccountBalanceChange::create([
                        'condo_id'       => $condo_id,
                        'account_id'     => $account_id,
                        'date'           => $date,
                        'debit_balance'  => round($new_debit, 2),
                        'credit_balance' => round($new_credit, 2)
                    ]);
                }

                /*
                * Adjust following rows (backdating case)
                */
                $nextChanges = AccountBalanceChange::search([
                            ['account_id', '=', $account_id],
                            ['condo_id', '=', $condo_id],
                            ['date', '>', $date]
                        ],
                        ['sort' => ['date' => 'asc']]
                    )
                    ->read(['id', 'debit_balance', 'credit_balance']);

                foreach($nextChanges as $change_id => $change) {
                    AccountBalanceChange::id($change_id)
                        ->update([
                            'debit_balance'  => round((float) $change['debit_balance']  + $delta_debit, 2),
                            'credit_balance' => round((float) $change['credit_balance'] + $delta_credit, 2)
                        ]);
                }
            }

            /*
            * Mark lines as posted (idempotence)
            */
            if(!empty($posted_lines_ids)) {
                AccountingEntryLine::ids($posted_lines_ids)
                    ->update([
                        'is_posted' => true
                    ]);
            }
        }
    }

    public static function candelete($self) {
        $self->read(['status', 'is_temp', 'sale_invoice_id', 'purchase_invoice_id']);
        foreach($self as $entry) {
            // unless temporary, a validated entry cannot be removed
            if($entry['status'] === 'validated' && !$entry['is_temp']) {
                return ['status' => ['non_removable' => 'Non-draft accounting entries cannot be deleted.']];
            }
            // while still attached to an invoice, an planned entry cannot be removed
            if($entry['status'] === 'planned') {
                return ['status' => ['non_removable' => 'Planned accounting entries cannot be deleted (system entries).']];
            }
        }
        return parent::candelete($self);
    }

    protected static function onupdateDescription($self, $lang) {
        $self->read(['description', 'entry_lines_ids' => ['description']]);
        foreach($self as $id => $accountingEntry) {
            if(!$accountingEntry['description'] || strlen($accountingEntry['description']) <= 0) {
                continue;
            }
            foreach($accountingEntry['entry_lines_ids'] as $entry_line_id => $entryLine) {
                if(!$entryLine['description'] || strlen($entryLine['description']) <= 0) {
                    AccountingEntryLine::id($entry_line_id)->update(['description' => $accountingEntry['description']], $lang);
                }
            }
        }
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['entry_lines_ids'])) {
            $entry = self::id($values['id'])->read(['entry_lines_ids'])->first();
            $map_entry_lines_ids = array_flip($entry['entry_lines_ids']);
            foreach ($event['entry_lines_ids'] as $line_id) {
                $action = $line_id[0];
                $line_id = ltrim($line_id, '+-');
                if($action === '-') {
                    if(isset($map_entry_lines_ids[$line_id])) {
                        unset($map_entry_lines_ids[$line_id]);
                    }
                }
                else {
                    $map_entry_lines_ids[$line_id] = true;
                }
            }
            $result['is_balanced'] = self::computeIsBalanced(array_keys($map_entry_lines_ids));
        }
        return $result;
    }

    /**
     * Once validated (or reversed) an accounting entry can no longer be modified.
     */
    public static function canupdate($self, $values) {
        $self->read(['status', 'entry_number']);
        $allowed_fields = ['status', 'name', 'description', 'reversed_entry_id'];
        foreach($self as $id => $accountingEntry) {
            if(in_array($accountingEntry['status'], ['reversed', 'validated'])) {
                if(count(array_diff(array_keys($values), $allowed_fields)) > 0) {
                    return ['status' => ['not_allowed' => 'Accounting entry cannot be modified once validated.']];
                }
            }
        }
        return parent::canupdate($self);
    }
}
