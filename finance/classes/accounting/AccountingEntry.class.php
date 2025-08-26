<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;

use equal\orm\Model;
use fmt\setting\Setting;

class AccountingEntry extends Model {

    public static function getName() {
        return "Journal accounting entry";
    }

    public static function getDescription() {
        return "Accounting entries correspond to invoice lines mapped as records of financial transactions in the accounting books.";
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
                'domain'            => [['code', '<>', 'LEDG'], ['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the entry relates to.",
                'required'          => true,
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'dependents'        => ['fiscal_period_id'],
                'default'           => 'defaultFiscalYearId'
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the entry relates to (from entry_date).",
                'help'              => "Period is automatically assigned based on entry date.",
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true
            ],

            'entry_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'The date on which the transaction is recorded in the accounting system and affects the fiscal period.',
                'help'              => 'This should always match the selected period, and is necessary in case of period re-assignment.',
                'required'          => true,
                'dependents'        => ['fiscal_period_id']
            ],

            'is_temp' => [
                'type'              => 'boolean',
                'description'       => 'The accounting entry is a temporary report and cannot be modified nor receive an entry number.',
                'default'           => false,
                'dependents'        => ['name']
            ],

            'entry_number' => [
                'type'              => 'string',
                'description'       => 'Unique code for entry identification.',
                'dependents'        => ['name', 'debit', 'credit'],
                'description'       => 'Entry number is automatically assigned after validation, and cannot be changed afterwards.'
            ],

            'origin_object_class' => [
                'type'              => 'string',
                'description'       => 'Entity class that the entry originates from.',
                'help'              => 'An accounting entry can originate from an Invoice, a Fund Request, ... But can also be a Misc entry (not related to a document).',
            ],

            'origin_object_id' => [
                'type'              => 'integer',
                'description'       => 'Object identifier, as a complement to `origin_object_class`, the entry originates from.'
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
                'help'              => 'When cancelled an entry remains valid and continues impacting the balance. However it should not have an impact since its debit and credits are voided by the reverse entry.',
                'default'           => false
            ],

            'reverse_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Reverse accounting entry voiding the current one.",
                'visible'           => ['is_cancelled', '=', true]
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\invoice\Invoice',
                'description'       => 'Invoice the accounting entry is related to.',
                'help'              => 'This field is expected to be overloaded in purchase and sale invoice classes.',
                'ondelete'          => 'null'
            ],

            // #todo - not sure if this is necessary
            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Reference document, if any.'
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
                'help'              => 'Pending entries are not actual accounting entries but `drafts` that can be created and modified without impacting Balance. Once an accounting entry has been validated, it cannot be removed. It can however, be cancelled through a reverse entry. Planned entries are system entries and can never be removed manually (only through source document cancellation).',
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

    public static function defaultFiscalYearId($values) {
        $result = null;
        if(isset($values['journal_id'])) {
            $journal = Journal::id($values['journal_id'])->read(['condo_id'])->first();
            if(isset($journal['condo_id'])) {
                $fiscalYear = FiscalYear::search([['condo_id', '=', $journal['condo_id']], ['status', '=', 'open']], ['sort' => ['date_from' => 'desc'], 'limit' => 1])->first();
                if($fiscalYear) {
                    $result = $fiscalYear['id'];
                }
            }
        }
        return $result;
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
                'fiscal_period_id' => ['id', 'date_from'],
                'fiscal_year_id'   => ['current_balance_id'],
                'entry_lines_ids'  => ['account_id', 'debit', 'credit']
            ]);

        foreach($self as $id => $accountingEntry) {
            if( !($accountingEntry['fiscal_year_id']['current_balance_id'] ?? false) ) {
                throw new \Exception('missing_balance', EQ_ERROR_INVALID_PARAM);
            }
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

            // make sure entries are chronological
            // search for all entries in the period for the concerned journal and, if more recent, take the date of the most recent one
            $mostRecentEntry = self::search([['id', '<>', $id], ['journal_id', '=', $accountingEntry['journal_id']], ['fiscal_period_id', '=', $accountingEntry['fiscal_period_id']['id']]], ['sort' => ['entry_date' => 'desc'], 'limit' => 1])->read(['entry_date'])->first();
            $entry_date = $accountingEntry['entry_date'];
            if($mostRecentEntry && $mostRecentEntry['entry_date'] > $accountingEntry['entry_date']) {
                $entry_date = $mostRecentEntry['entry_date'];
            }
            self::id($id)->update(['entry_number' => self::computeEntryNumber($id), 'entry_date' => $entry_date]);
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
            ->read(['status', 'is_temp', 'condo_id',
                'journal_id'        => ['code'],
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
                    'condo_id' => $entry['condo_id']
                ]
            );

        $fiscal_year_code = $entry['fiscal_year_id']['code'];
        $fiscal_period_code = $entry['fiscal_period_id']['code'];
        $journal_code = $entry['journal_id']['code'];

        $sequence = Setting::fetch_and_add(
                'finance',
                'accounting',
                "accounting_entry.sequence.{$fiscal_year_code}.{$fiscal_period_code}.{$journal_code}",
                1,
                [
                    'condo_id' => $entry['condo_id']
                ]
            );

        if(!$sequence) {
            trigger_error("APP::missing mandatory finance.accounting.accounting_entry.sequence.{$fiscal_year_code}.{$fiscal_period_code}.{$journal_code} for condominium {$entry['condo_id']}.", EQ_REPORT_ERROR);
            throw new \Exception('missing_mandatory_sequence', EQ_ERROR_INVALID_CONFIG);
        }

        $result = Setting::parse_format($format, [
                'year'      => $fiscal_year_code,
                'period'    => $fiscal_period_code,
                'journal'   => $journal_code,
                'sequence'  => $sequence
            ]);

        return $result;
    }

    public static function candelete($self) {
        $self->read(['status', 'is_temp', 'invoice_id']);
        foreach($self as $entry) {
            // unless temporary, a validated entry cannot be removed
            if($entry['status'] === 'validated' && !$entry['is_temp']) {
                return ['status' => ['non_removable' => 'Non-draft accounting entries cannot be deleted.']];
            }
            // while still attached to an invoice, an planned entry cannot be removed
            if($entry['invoice_id'] && $entry['status'] === 'planned') {
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
