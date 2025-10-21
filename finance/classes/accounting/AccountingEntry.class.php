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

            'entry_number' => [
                'type'              => 'string',
                'description'       => 'Unique code for entry identification.',
                'dependents'        => ['name', 'debit', 'credit'],
                'description'       => 'Entry number is automatically assigned after validation, and cannot be changed afterwards.',
                'onupdate'          => 'onupdateEntryNumber'
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
                'function'          => 'calcIsBalanced'
            ],

            'entry_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntryLine',
                'foreign_field'     => 'accounting_entry_id',
                'description'       => "Lines of the accounting entry.",
                'dependents'        => ['debit', 'credit'],
                'ondetach'          => 'delete'
            ],

            'is_cancelled' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the entry as cancelled (reversed).',
                'help'              => 'When cancelled an entry remains valid and continues impacting the balance.
                    It should be linked with an accounting document that voids it.
                    And should not have an impact on the result since its debit and credits are voided by the reverse entry (`reverse_entry_id`).',
                'default'           => false
            ],

            'reverse_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Reverse accounting entry voiding the current one, if any.",
                'visible'           => ['is_cancelled', '=', true]
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
                'selection'         => [
                    'pending',
                    'planned',
                    'validated'
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


    public static function getActions() {
        return [
            'cancel' => [
                'description'   => 'Delete the proforma and set receivables statuses back to pending.',
                'help'          => 'A fiscal year can be opened before the previous one is definitely closed.',
                'policies'      => ['can_be_cancelled'],
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
                            'is_valid'
                        ],
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

    // #memo - matching is performed at AccountingEntryLne (records) level
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
            if( !($accountingEntry['fiscal_year_id']['current_balance_id'] ?? false) ) {
                throw new \Exception('missing_balance', EQ_ERROR_INVALID_PARAM);
            }

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
            foreach($accountingEntry['entry_lines_ids'] ?? [] as $entry_line_id => $entryLine) {
                CurrentBalance::id($accountingEntry['fiscal_year_id']['current_balance_id'])
                    ->do('update_account', [
                        'account_id' => $entryLine['account_id'],
                        'debit'      => $entryLine['debit'],
                        'credit'     => $entryLine['credit']
                    ]);
            }

            $entry_date = $accountingEntry['entry_date'];

            // #memo - the encoding of the purchase invoices is non chronological
            // - we must maintain the sequence, but cannot force dates sequence without losing information

            self::id($id)
                ->update([
                    'entry_number'  => self::computeEntryNumber($id),
                    'entry_date'    => $entry_date
                ]);

        }
    }

    protected static function doCancel($self) {
        // create and validate reverse entry
        $self->read(['condo_id', 'fiscal_year_id', 'journal_id', 'entry_date', 'entry_lines_ids' => ['account_id', 'debit', 'credit']]);
        foreach($self as $id => $accountingEntry) {
            // #memo - we cannot update the Balance directly to avoid concurrent changes: always use BalanceUpdateRequest
            $reverseAccountingEntry = self::create([
                    'condo_id'              => $accountingEntry['condo_id'],
                    'journal_id'            => $accountingEntry['journal_id'],
                    'fiscal_year_id'        => $accountingEntry['fiscal_year_id'],
                    'entry_date'            => time(),
                    'reverse_entry_id'      => $id
                ])
                ->first();

            foreach($accountingEntry['entry_lines_ids'] ?? [] as $entry_line_id => $entryLine) {
                AccountingEntryLine::create([
                    'condo_id'              => $accountingEntry['condo_id'],
                    'accounting_entry_id'   => $reverseAccountingEntry['id'],
                    'account_id'            => $entryLine['account_id'],
                    'debit'                 => $entryLine['credit'],
                    'credit'                => $entryLine['debit']
                ]);
            }
            self::id($id)->update(['reverse_entry_id' => $reverseAccountingEntry['id']]);
            self::id($reverseAccountingEntry['id'])->transition('validate');
        }
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that an accounting entry is balanced.',
                'function'    => 'policyIsValid'
            ],
            'can_be_cancelled' => [
                'description' => 'Verifies that an accounting entry can be cancelled (validated and not already cancelled).',
                'function'    => 'policyCanBeCancelled'
            ]
        ];
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

    public static function policyCanBeCancelled($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $accountingEntry) {
            if($accountingEntry['status'] != 'validated') {
                $result[$id] = [
                        'invalid_status' => 'Accounting entry must be validated.'
                    ];
                continue;
            }
        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['status', 'is_temp', 'entry_number']);
        foreach($self as $id => $accountingEntry) {
            if($accountingEntry['status'] == 'pending') {
                $result[$id] = '(draft)';
            }
            elseif($accountingEntry['is_temp']) {
                $result[$id] = '(temp)';
            }
            else {
                $result[$id] = $accountingEntry['entry_number'];
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
        $result = '';
        $entry = self::id($id)
            ->read(['status', 'is_temp',
                'condo_id'          => ['id', 'code'],
                'journal_id'        => ['code'],
                'sub_journal_id'    => ['code'],
                'fiscal_year_id'    => ['code'],
                'fiscal_period_id'  => ['code']
            ])
            ->first();

        if($entry['status'] == 'pending' || $entry['is_temp']) {
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

    protected static function onupdateDescription($self) {
        $self->read(['description', 'entry_lines_ids']);
        foreach($self as $id => $accountingEntry) {
            if($accountingEntry['description'] && strlen($accountingEntry['description']) > 0) {
                AccountingEntryLine::ids($accountingEntry['entry_lines_ids'])
                    ->update(['name' => $accountingEntry['description']]);
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
     * on ne check pas sur le status mais sur le entry_number (il ne peut être assigné qu'une seule fois)
     * pour éviter un blocage au moment de la transition 'validate'
     */
    public static function canupdate($self) {
        $self->read(['entry_number']);
        foreach($self as $id => $accountingEntry) {
            if($accountingEntry['entry_number']) {
                return ['status' => ['not_allowed' => 'Accounting entry cannot be modified once validated.']];
            }
        }
        return parent::canupdate($self);
    }
}
