<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;

use equal\orm\Model;
use fmt\setting\Setting;

class AccountingEntry extends Model {

    public static function getName() {
        return "Journal accounting entry";
    }

    public static function getDescription() {
        return "Accounting entries convert invoice lines into records of financial transactions in the accounting books.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                //'readonly'          => true
            ],

            'journal_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => "Accounting journal the entry relates to.",
                'required'          => true,
                'domain'            => ['code', '<>', 'LEDG']
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the entry relates to.",
                'required'          => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the entry relates to (from entry_date).",
                'function'          => 'calcFiscalPeriodId'
            ],

            'entry_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'The date on which the transaction is recorded in the accounting system and affects the fiscal period.',
                'help'              => 'This should always match the selection period, and is necessary in cas of period re-assignment.',
                'required'          => true
            ],

            'is_temp' => [
                'type'              => 'boolean',
                'description'       => 'The accounting entry is a temporary report and cannot be modified nor receive an entry number.',
                'default'           => false
            ],

            'entry_number' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcEntryNumber',
                'store'             => true
            ],

            'origin_object_class' => [
                'type'              => 'string',
                'description'       => 'Entity class that the entry originates from.',
            ],

            'origin_object_id' => [
                'type'              => 'integer',
                'description'       => 'Object identifier, of `origin_object_class`, the entry originates from.'
            ],

            'debit' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total debited amount from all lines.',
                'function'          => 'calcDebit',
                'store'             => true,
                'readonly'          => true
            ],

            'credit' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
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
                'dependents'        => ['debit', 'credit']
            ],

            'reverse_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Reverse accounting entry voiding the current one.",
                'visible'           => ['visible', '=', 'cancelled']
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'validated',
                    'cancelled'
                ],
                'default'           => 'pending',
                'description'       => 'Status of the accounting entry.',
                'help'              => 'Once an accounting entry has been validated, it cannot be removed. It can however, be cancelled through a reverse entry.'
            ]

        ];
    }

    public static function getActions() {
        return [
            /*
            'generate_periods' => [
                'description'   => 'Generate the periods according to the fiscal year definition (only for draft fiscal year).',
                'policies'      => [],
                'function'      => 'doGeneratePeriods'
            ]
            */
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
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon'        => 'drive_file_rename_outline',
                'transitions' => [
                    'cancel' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'help'        => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'policies'    => [
                            'can_be_cancelled',
                        ],
                        'onafter'   => 'onafterCancel',
                        'status'    => 'cancelled'
                    ]
                ]
            ]
        ];
    }

    /**
     * Update the Current Balance the entry relates to.
     *
     */
    public static function doUpdateBalance($self) {
        $self->read(['fiscal_year_id' => ['current_balance_id'], 'entry_lines_ids' => ['account_id', 'debit', 'credit']]);
        foreach($self as $id => $accountingEntry) {
            if( !($accountingEntry['fiscal_year_id']['current_balance_id'] ?? false) ) {
                continue;
            }
            foreach($accountingEntry['entry_lines_ids'] ?? [] as $entry_line_id => $entryLine) {
                CurrentBalance::id($accountingEntry['fiscal_year_id']['current_balance_id'])
                    ->do('update_account', [
                        'account_id' => $entryLine['account_id'],
                        'debit'      => $entryLine['debit'],
                        'credit'     => $entryLine['credit']
                    ]);
            }
        }
    }

    /**
     * The entry has been validated (irreversible transition).
     * This method triggers the update of the related current balance and the fiscal period (based on the entry date).
     */
    public static function onafterValidate($self) {
        // append accounting entry to current balance
        self::doUpdateBalance($self);
        // force computing required fields
        $self->read(['entry_number', 'fiscal_period_id']);
    }

    public static function onafterCancel($self) {
        // créer et valider l'écriture inversée

    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    public static function policyIsValid($self): array {
        $result = [];
        $self->read(['is_balanced']);
        foreach($self as $id => $accountingEntry) {
            if(!$accountingEntry['is_balanced']) {
                $result[$id] = false;
                continue;
            }
        }
        return $result;
    }

    public static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['status', 'entry_date', 'fiscal_year_id' => ['fiscal_periods_ids' => ['date_from', 'date_to']]]);
        foreach($self as $id => $entry) {
            if($entry['status'] == 'pending') {
                continue;
            }
            foreach($entry['fiscal_year_id']['fiscal_periods_ids'] ?? [] as $period_id => $period) {
                if($entry['entry_date'] >= $period['date_from'] && $entry['entry_date'] <= $period['date_to']) {
                    $result[$id] = $period_id;
                    break;
                }
            }
        }
        return $result;
    }

    public static function calcIsBalanced($self) {
        $result = [];
        $self->read(['entry_lines_ids' => ['credit', 'debit']]);
        foreach($self as $id => $entry) {
            $credit = 0;
            $debit = 0;
            foreach($entry['entry_lines_ids'] as $line_id => $line) {
                $credit += $line['credit'];
                $debit += $line['debit'];
            }
            $result[$id] = ($credit === $debit);
        }
        return $result;
    }

    public static function calcDebit($self) {
        $result = [];
        $self->read(['entry_lines_ids' => ['debit']]);
        foreach($self as $id => $entry) {
            $result[$id] = 0.0;
            foreach($entry['entry_lines_ids'] as $line) {
                $result[$id] += $line['debit'];
            }
        }
        return $result;
    }

    public static function calcCredit($self) {
        $result = [];
        $self->read(['entry_lines_ids' => ['credit']]);
        foreach($self as $id => $entry) {
            $result[$id] = 0.0;
            foreach($entry['entry_lines_ids'] as $line) {
                $result[$id] += $line['credit'];
            }
        }
        return $result;
    }

    public static function calcEntryNumber($self) {
        $result = [];
        $self->read(['status', 'is_temp', 'condo_id', 'journal_id' => ['code'], 'fiscal_year_id' => ['code', 'organisation_id']]);

        foreach($self as $id => $entry) {
            if($entry['status'] == 'pending' || $entry['is_temp']) {
                continue;
            }

            if(!isset($entry['journal_id'], $entry['journal_id']['code'], $entry['fiscal_year_id']['organisation_id'])) {
                continue;
            }

            $format = Setting::get_value(
                    'finance',
                    'accounting',
                    'accounting_entry.number_format',
                    '%s{journal}/%02d{year}/%05d{sequence}',
                    [
                        'condo_id' => $entry['condo_id']
                    ]
                );

            $fiscal_year_code = $entry['fiscal_year_id']['code'];
            $journal_code = $entry['journal_id']['code'];

            $sequence = Setting::fetch_and_add(
                    'finance',
                    'accounting',
                    "accounting_entry.sequence.{$fiscal_year_code}.{$journal_code}",
                    1,
                    [
                        'condo_id' => $entry['condo_id']
                    ]
                );

            if($sequence) {
                $result[$id] = Setting::parse_format($format, [
                        'year'      => $fiscal_year_code,
                        'journal'   => $journal_code,
                        'sequence'  => $sequence
                    ]);
            }

        }
        return $result;
    }
}
