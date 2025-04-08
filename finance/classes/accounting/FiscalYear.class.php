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
use realestate\property\Condominium;

class FiscalYear extends Model {

    public static function getName() {
        return "Fiscal Year";
    }

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the fiscal year refers to.",
                'help'              => "When a fiscal year is not linked to a condominium, it relates to the organisation itself.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => "The organisation the chart belongs to.",
                'default'           => 1
            ],

            'fiscal_period_frequency' => [
                'type'              => 'string',
                'selection'         => [
                    'A' => 'Annually',
                    'S' => 'Semi-Annually',
                    'T' => 'Tertially' ,
                    'Q' => 'Quarterly'
                ],
                'description'       => 'List of employees assigned to the management of the condominium.',
                'help'              => 'This value is provided at creation and can originate either from condominium settings or entered manually.',
                'default'           => 'Q'
                /*
                1 Annually (12 months)	    A (A1)
                2 Semi-Annually (6 months)  S (S1, S2)
                3 Tertially (4 months)	    T (T1, T2, T3)
                4 Quarterly (3 months)	    Q (Q1, Q2, Q3, Q4)
                ['A' => 1, 'S' => 2, 'T' => 3, 'Q' => 4]
                */
            ],

            'fiscal_periods_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'foreign_field'     => 'fiscal_year_id',
                'description'       => "The fiscal periods related to the fiscal year.",
                'order'             => 'order',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'ondetach'          => 'delete'
            ],

            'current_balance_id' => [
                'type'              => 'many2one',
                'description'       => "The balance of the fiscal year.",
                'foreign_object'    => 'finance\accounting\CurrentBalance',
            ],

            'closing_balance_id' => [
                'type'              => 'many2one',
                'description'       => "The closing (final) balance of the whole fiscal year.",
                'help'              => "The closing balance is generated once, when the fiscal year reaches the 'closed' status. Distinct closing balances can be generated for the periods of the fiscal year.",
                'foreign_object'    => 'finance\accounting\ClosingBalance',
            ],

            'closing_balances_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\ClosingBalance',
                'foreign_field'     => 'fiscal_year_id',
                'description'       => "The closing balance that refer to the fiscal year."
            ],

            'current_balance_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\CurrentBalanceLine',
                'foreign_field'     => 'fiscal_year_id',
                'description'       => "The balance lines that refer to the fiscal year."
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => 'Label for identifying the fiscal year.'
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcCode',
                'store'             => true,
                'description'       => 'Code for identifying the fiscal year, based on start and nd dates.'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => 'First day of the fiscal year (included).',
                'dependents'        => ['name', 'code']
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'Last day of the fiscal year (included).',
                'dependents'        => ['name', 'code']
            ],

            'previous_fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "The directly previous fiscal year, if any.",
                'help'              => "This field set automatically and is dedicated to checks prior to performing some operations."
            ],

            'fund_requests_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'foreign_field'     => 'fiscal_year_id',
                'description'       => "Fund requests relating to the fiscal year."
            ],

            'fund_request_executions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'foreign_field'     => 'fiscal_year_id',
                'description'       => "Fund requests relating to the fiscal year."
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'preopen',
                    'open',
                    'closed',
                    'archived'
                ],
                'default'           => 'draft',
                'description'       => 'Status of the fiscal year.'
            ]

        ];
    }

    /**
     * This method is just for display and can be adapted if necessary
     */
    public static function calcName($self) {
        $result = [];
        $self->read(['condo_id' => ['name'], 'date_from', 'date_to']);
        foreach($self as $id => $year) {
            if(!$year['date_from'] || !$year['date_to']) {
                continue;
            }

            $year_from = date('Y', $year['date_from']);
            $year_to = date('Y', $year['date_to']);

            $name = $year_from;

            if(strcmp($year_from, $year_to) !== 0) {
                $name .= '-'.$year_to;
            }
            $result[$id] = $name . " ({$year['condo_id']['name']})";
        }
        return $result;
    }

    /**
     * This method cannot be changed: code is used as reference and its format must be kept as is.
     */
    public static function calcCode($self) {
        $result = [];
        $self->read(['date_from', 'date_to']);
        foreach($self as $id => $fiscalYear) {
            $fiscal_year_from = date('Y', $fiscalYear['date_from']);
            $fiscal_year_to = date('Y', $fiscalYear['date_to']);
            $result[$id] = substr($fiscal_year_from, -2) . ( ($fiscal_year_from === $fiscal_year_to) ? '' : '-' . substr($fiscal_year_to, -2));
        }
        return $result;
    }

    public static function calcPreviousFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'date_from', 'date_to']);
        foreach($self as $id => $fiscalYear) {
            $result[$id] = self::search([
                    ['condo_id', '=', $fiscalYear['condo_id']],
                    ['date_to', '=', strtotime('-1 day', $fiscalYear['date_from'])]
                ])
                ->read(['id'])
                ->first();
        }
        return $result;
    }

    public static function getActions() {
        return [
            'generate_periods' => [
                'description'   => 'Generate the periods according to the fiscal year definition (only for draft fiscal year).',
                'policies'      => [],
                'function'      => 'doGeneratePeriods'
            ],
            'generate_sequences' => [
                'description'   => 'Generate the mandatory sequences for the fiscal year.',
                'policies'      => [],
                'function'      => 'doGenerateSequences'
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon' => 'draw',
                'transitions' => [
                    'preopen' => [
                        'description' => 'Update the fiscal year status to `preopen`.',
                        'onafter' => 'onafterPreOpen',
                        'policies' => [
                            'can_be_preopened',
                        ],
                        'status' => 'preopen',
                    ],
                    'open' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'help' => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'onafter' => 'onafterOpen',
                        'policies' => [
                            'can_be_opened',
                        ],
                        'status' => 'open',
                    ],
                ],
            ],
            'preopen' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon' => 'drive_file_rename_outline',
                'transitions' => [
                    'open' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'help' => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'onafter' => 'onafterOpen',
                        'policies' => [
                            'can_be_opened',
                        ],
                        'status' => 'open',
                    ],
                ],
            ],
            'open' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon' => 'pending',
                'transitions' => [
                    'preclose' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'help' => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'policies' => [
                            'can_be_preclosed',
                        ],
                        'status' => 'preclosed',
                    ],
                ],
            ],
            'preclosed' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon' => 'lock_open',
                'transitions' => [
                    'close' => [
                        'description' => 'Handle actions related to fiscal year closing.',
                        'help' => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'onafter' => 'onafterClose',
                        'policies' => [
                            'can_be_closed',
                        ],
                        'status' => 'closed',
                    ],
                ],
            ],
            'closed' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon' => 'lock',
                'transitions' => [
                    'unclose' => [
                        'description' => 'Handle actions related to fiscal year closing.',
                        'help' => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'onafter' => 'onafterUnClose',
                        'status' => 'preclosed',
                    ],
                ],
            ],
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_be_preopened' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanBePreOpened'
            ],
            'can_be_opened' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanBeOpened'
            ],
            'can_be_preclosed' => [
                'description' => 'Verifies that a fiscal year can be set (or set back) to preclosed status.',
                'function'    => 'policyCanBePreClosed'
            ]
        ];
    }


    public static function policyCanBePreOpened($self): array {
        $result = [];
        $self->read(['status']);

        foreach($self as $id => $fiscalYear) {
            // status of fiscal year must be 'draft'
            if($fiscalYear['status'] != 'draft') {
                $result[$id] = [
                    'invalid_status' => 'Fiscal year status must be draft.'
                ];
                continue;
            }

            $inconsistency = self::computeIsConsistent($id);
            if(count($inconsistency)) {
                $result[$id] = $inconsistency;
            }
        }
        return $result;
    }

    public static function policyCanBeOpened($self): array {
        $result = [];
        $self->read(['status', 'previous_fiscal_year_id', 'condo_id']);

        foreach($self as $id => $fiscalYear) {

            $nextFiscalYear = self::search([['status', '=', 'draft'], ['condo_id', '=', $fiscalYear['condo_id']]]);

            if(count($nextFiscalYear) <= 0) {
                $result[$id] = [
                    'missing_next_fiscal_year' => 'Next fiscal year must exist.'
                ];
                continue;
            }

            if(!empty(self::policyCanBePreOpened($nextFiscalYear))) {
                $result[$id] = [
                    'invalid_next_fiscal_year' => 'Next fiscal year cannot be pre opened.'
                ];
                continue;
            }

            // status of fiscal year must be 'preopen'
            if($fiscalYear['status'] != 'preopen') {
                $result[$id] = [
                    'invalid_fiscal_year' => 'Fiscal year is not in preopen status.'
                ];
                continue;
            }

            if($fiscalYear['previous_fiscal_year_id']) {
                if(!empty(self::policyCanBePreClosed(self::id($fiscalYear['previous_fiscal_year_id'])))) {
                    $result[$id] = [
                        'invalid_previous_fiscal_year' => 'Previous fiscal year cannot be pre closed.'
                    ];
                    continue;
                }
            }

            // fiscal year and periods must be consistent (each year and period must immediately follow the previous one)
            $inconsistency = self::computeIsConsistent($id);
            if(count($inconsistency)) {
                $result[$id] = $inconsistency;
            }
        }
        return $result;
    }

    public static function policyCanBePreClosed($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'date_to', 'previous_fiscal_year_id' => ['status']]);

        foreach($self as $id => $fiscalYear) {
            // if we go back from 'closed' to 'preclosed', the fiscal year must be the latest closed one
            if($fiscalYear['status'] == 'closed') {
                $closedFiscalYears = self::search([['status', '=', 'closed'], ['date_from', '>', $fiscalYear['date_to']], ['condo_id', '=', $fiscalYear['condo_id']]]);
                if(count($closedFiscalYears) > 0) {
                    $result[$id] = [
                        'cannot_be_unclosed' => 'Fiscal year cannot be unclosed while a more recent fiscal year is still closed.'
                    ];
                    continue;
                }
            }
            if(!in_array($fiscalYear['status'], ['open', 'closed'])) {
                $result[$id] = [
                    'invalid_status' => 'Fiscal year status must be open or closed.'
                ];
                continue;
            }
            if($fiscalYear['previous_fiscal_year_id']) {
                // status of previous fiscal year, if any, must be 'closed' or 'preclosed'
                if(!in_array($fiscalYear['previous_fiscal_year_id']['status'], ['preclosed', 'closed'])) {
                    $result[$id] = [
                        'invalid_previous_year_status' => 'Fiscal year status must be closed or preclosed.'
                    ];
                    continue;
                }
            }
        }
        return $result;
    }

    /**
     * Perform tasks related to fiscal year pre-opening.
     * This method assigns a current balance to it (which will not change, whatever the final duration of the fiscal year).
     * A preopened fiscal year cannot be removed anymore.
     *
     */
    public static function onafterPreOpen($self) {
        $self->read(['condo_id', 'fiscal_periods_ids' => ['date_from']]);
        foreach($self as $id => $fiscalYear) {

            // 1 - finalize periods order

            $periods = $fiscalYear['fiscal_periods_ids']->get(true);
            usort($periods, fn($a, $b) => $a['date_from'] <=> $b['date_from']);
            $order = 1;
            foreach($periods as $period) {
                // assign final order for fiscal periods
                FiscalPeriod::id($period['id'])->update(['order' => $order]);
                ++$order;
            }

            // 2 - create a dedicated balance for the fiscal year

            $currentBalance = CurrentBalance::create([
                    'condo_id'          => $fiscalYear['condo_id'],
                    'fiscal_year_id'    => $id
                ])
                ->first();
            self::id($id)->update(['current_balance_id' => $currentBalance['id']]);
        }

        $self->do('generate_sequences');
    }

    public static function onbeforeOpen($self) {
        $self->read(['status', 'condo_id']);

        foreach($self as $id => $fiscalYear) {
            // if there is no draft for next fiscal year, create one
            $nextFiscalYear = self::search([['status', '=', 'draft'], ['condo_id', '=', $fiscalYear['condo_id']]]);
            if(count($nextFiscalYear) <= 0) {
                Condominium::id($fiscalYear['condo_id'])->do('create_draft_fiscal_year');
                $nextFiscalYear = self::search([['status', '=', 'draft'], ['condo_id', '=', $fiscalYear['condo_id']]])->do('generate_periods');
            }
        }

    }

    /**
     * Perform tasks related to fiscal year opening.
     * This callback is called when Fiscal year status just switched to 'open'.
     * All tasks relating to fiscal year opening are performed here.
     *
     */
    public static function onafterOpen($self) {
        $self->read(['condo_id', 'previous_fiscal_year_id', 'fiscal_periods_ids' => ['id', 'date_from', 'date_to']]);

        foreach($self as $id => $fiscalYear) {

            // 1 - transition previous fiscal year to 'preclosed' (transition has been checked in `policyCanBeOpened()`)

            if($fiscalYear['previous_fiscal_year_id']) {
                self::id($fiscalYear['previous_fiscal_year_id'])->transition('preclose');
            }

            // 2 - transition next fiscal year to 'preopen' (existence and transition have been checked in `policyCanBeOpened()`)

            $nextFiscalYear = self::search([['status', '=', 'draft'], ['condo_id', '=', $fiscalYear['condo_id']]])->transition('preopen')->first();

            // 3 - finalize periods order
            // #memo - moved to preopen

            // 4 - create temporary carry-forward / opening-balance accounting entries in next fiscal year OPB journal

            $carryForwardJournal = Journal::search([['code', '=', 'OPB']])->first();
            if(!$carryForwardJournal) {
                throw new \Exception('missing_opb_journal', EQ_ERROR_INVALID_CONFIG);
            }

            $entry_lines = self::computeCarryForwardEntryLines($id);

            $accountingEntry = AccountingEntry::create([
                    'condo_id'          => $fiscalYear['condo_id'],
                    'journal_id'        => $carryForwardJournal['id'],
                    'status'            => 'validated',
                    'is_temp'           => true,
                    'fiscal_year_id'    => $nextFiscalYear['id'],
                    'entry_date'        => time()
                ])
                ->first();

            foreach($entry_lines as $line) {
                AccountingEntryLine::create([
                        'condo_id'              => $fiscalYear['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'account_id'            => $line['account_id'],
                        'debit'                 => $line['debit'],
                        'credit'                => $line['credit']
                    ]);
            }

            AccountingEntry::id($accountingEntry['id'])->transition('validate');

            // 5 - update current fiscal year for targeted Condominium
            Condominium::id($fiscalYear['condo_id'])->update(['current_fiscal_year_id' => $id]);
        }

    }

    /**
     * Upon creation of a fiscal year, it is necessary to create sequences for:
     * - the sale invoices:             sale.accounting.invoice.sequence.{fiscal_year_code}                             [condo_id]
     * - the purchase invoices:         purchase.accounting.invoice.period_sequence.{fiscal_year_code}                  [condo_id]
     * - the accounting entries:        finance.accounting.accounting_entry.sequence.{fiscal_year_code}.{journal_code}  [condo_id]
     */
    public static function doGenerateSequences($self) {
        $self->read(['condo_id', 'code', 'fiscal_periods_ids' => ['order']]);
        foreach($self as $id => $fiscalYear) {
            $fiscal_year_code = $fiscalYear['code'];

            $journals = Journal::search([['code', '<>', 'LEDG'], ['condo_id', '=', $fiscalYear['condo_id']]])->read(['code']);

            // init mandatory sequences
            foreach($fiscalYear['fiscal_periods_ids'] as $period_id => $fiscalPeriod) {
                $fiscal_period_code = $fiscalPeriod['order'];

                // sale invoices
                Setting::assert_sequence('sale', 'accounting', "invoice.sequence.{$fiscal_year_code}.{$fiscal_period_code}");
                Setting::init_sequence('sale', 'accounting', "invoice.sequence.{$fiscal_year_code}.{$fiscal_period_code}", ['condo_id' => $fiscalYear['condo_id']]);

                // purchase invoices
                Setting::assert_sequence('purchase', 'accounting', "invoice.period_sequence.{$fiscal_year_code}.{$fiscal_period_code}");
                Setting::init_sequence('purchase', 'accounting', "invoice.period_sequence.{$fiscal_year_code}.{$fiscal_period_code}", ['condo_id' => $fiscalYear['condo_id']]);

                // create accounting entries sequences for all existing journals
                foreach($journals as $journal) {
                    $journal_code = $journal['code'];
                    Setting::assert_sequence('finance', 'accounting', "accounting_entry.sequence.{$fiscal_year_code}.{$fiscal_period_code}.{$journal_code}");
                    Setting::init_sequence('finance', 'accounting', "accounting_entry.sequence.{$fiscal_year_code}.{$fiscal_period_code}.{$journal_code}", ['condo_id' => $fiscalYear['condo_id']]);
                }

            }

        }
    }

    /**
     * Generate final report of accounting entries
     */
    public static function onafterClose($self) {
        $self->read(['condo_id']);

        // create temporary carry-forward / opening-balance accounting entries in next fiscal year OPB journal
        $carryForwardJournal = Journal::search([['code', '=', 'OPB']])->first();
        if(!$carryForwardJournal) {
            throw new \Exception('missing_opb_journal', EQ_ERROR_INVALID_CONFIG);
        }

        foreach($self as $id => $fiscalYear) {
            $entry_lines = self::computeCarryForwardEntryLines($id);

            $nextFiscalYear = self::search([['status', '=', 'open'], ['condo_id', '=', $fiscalYear['condo_id']]])->first();

            $accountingEntry = AccountingEntry::create([
                    'condo_id'          => $fiscalYear['condo_id'],
                    'journal_id'        => $carryForwardJournal['id'],
                    'status'            => 'validated',
                    'is_temp'           => true,
                    'fiscal_year_id'    => $nextFiscalYear['id'],
                    'entry_date'        => time()
                ])
                ->first();

            foreach($entry_lines as $line) {
                AccountingEntryLine::create([
                        'condo_id'              => $fiscalYear['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'account_id'            => $line['account_id'],
                        'debit'                 => $line['debit'],
                        'credit'                => $line['credit']
                    ]);
            }

            AccountingEntry::id($accountingEntry['id'])->transition('validate');
        }
    }

    public static function doGeneratePeriods($self) {
        $self->read(['condo_id', 'date_from', 'date_to', 'previous_fiscal_year_id', 'fiscal_period_frequency', 'condo_id' => ['id', 'fiscal_year_start', 'fiscal_year_end']]);
        foreach($self as $id => $fiscalYear) {
            if(!$fiscalYear['date_from']) {
                throw new \Exception('missing_date_from', EQ_ERROR_INVALID_PARAM);
            }
            if(!$fiscalYear['date_to']) {
                throw new \Exception('missing_date_to', EQ_ERROR_INVALID_PARAM);
            }
            FiscalPeriod::search(['fiscal_year_id', '=', $id])->delete(true);
            $periods = self::computeFiscalPeriods($fiscalYear['date_from'], $fiscalYear['date_to'], $fiscalYear['fiscal_period_frequency']);

            $is_first = !boolval($fiscalYear['previous_fiscal_year_id']);

            $i = 0;
            foreach($periods as $period) {
                // handle special case for first fiscal year of a Condominium
                if($fiscalYear['condo_id'] && $is_first) {
                    if($period['date_to'] < $fiscalYear['condo_id']['fiscal_year_start']) {
                        continue;
                    }
                    // adjust first period if necessary
                    if($i == 0) {
                        if( $fiscalYear['condo_id']['fiscal_year_start'] < $period['date_from']
                            || $fiscalYear['condo_id']['fiscal_year_start'] > $period['date_from']
                        ) {
                            $period['date_from'] = $fiscalYear['condo_id']['fiscal_year_start'];
                        }
                    }
                }
                FiscalPeriod::create([
                        'condo_id'          => $fiscalYear['condo_id']['id'],
                        'fiscal_year_id'    => $id,
                        'date_from'         => $period['date_from'],
                        'date_to'           => $period['date_to']
                    ]);
                ++$i;
            }
        }
    }

    public static function doGenerateClosingBalance($self) {
    }

    private static function computeFiscalPeriods($fiscal_year_start, $fiscal_year_end, $fiscal_period_frequency) {
        $periods = [];

        $frequencies = [
            'Q' => 3,  // Quarterly
            'T' => 4,  // Tertially
            'S' => 6,  // Semi-Annually
            'A' => 12  // Annually
        ];

        if (!isset($frequencies[$fiscal_period_frequency])) {
            trigger_error("APP::invalid frequency: ".$fiscal_period_frequency, EQ_REPORT_ERROR);
            return $periods;
        }

        $months_per_period = $frequencies[$fiscal_period_frequency];

        $start_date = $fiscal_year_start;

        while($start_date < $fiscal_year_end) {
            $end_date = strtotime("+$months_per_period months", $start_date);
            $end_date = strtotime("-1 day", $end_date);

            if($end_date > $fiscal_year_end) {
                $end_date = $fiscal_year_end;
            }

            $periods[] = [
                'date_from' => $start_date,
                'date_to'   => $end_date
            ];

            $start_date = strtotime("+1 day", $end_date);
        }

        return $periods;
    }

    /**
     *  Build an array with carry forward entries.
     */
    private static function computeCarryForwardEntryLines($id): array {
        $result = [];
        // depending on the status, we take either the closing balance or the current balance
        $fiscalYear = self::id($id)->read(['status', 'current_balance_id', 'closing_balance_id'])->first();

        if($fiscalYear['status'] == 'preclosed') {
            $balanceCollection = CurrentBalance::id($fiscalYear['current_balance_id']);
        }
        elseif($fiscalYear['status'] == 'closed') {
            $balanceCollection = ClosingBalance::id($fiscalYear['closing_balance_id']);
        }
        else {
            return [];
        }

        $balance = $balanceCollection->read([
                'balance_lines_ids' => [
                    'account_id', 'credit', 'debit'
                ]
            ])
            ->first();

        $accounts_ids = array_map( fn($a) => $a['account_id'], $balance['balance_lines_ids']->get(true) );

        $map_accounts = [];
        $accounts = Account::ids($accounts_ids)->read(['account_type'])->get(true);
        foreach($accounts as $id => $account) {
            $map_accounts[$id] = $account;
        }

        // we expect the balance lines to be consistent with the chart of accounts of the related condominium
        foreach($balance['balance_lines_ids'] as $id => $line) {
            if($map_accounts[$line['account_id']] != 'B') {
                continue;
            }
            $result[] = [
                    'account_id'    => $line['account_id'],
                    'debit'         => $line['debit'],
                    'credit'        => $line['credit']
                ];
        }
        return $result;
    }

    /**
     * Check fiscal year and periods dates consistency.
     *
     */
    private static function computeIsConsistent($id): array {
        $fiscalYear = self::id($id)->read(['date_from', 'date_to', 'previous_fiscal_year_id' => ['date_to'], 'fiscal_periods_ids' => ['date_from', 'date_to']])->first();


        if(!$fiscalYear['date_from'] || !$fiscalYear['date_to']) {
            return [
                'missing_date' => 'Invalid or missing date_from or date_to.'
            ];
        }

        if($fiscalYear['date_from'] >= $fiscalYear['date_to']) {
            return [
                'invalid_date' => 'Reverse order for date_from and date_to.'
            ];
        }

        if($fiscalYear['previous_fiscal_year_id'] && strtotime('-1 day', $fiscalYear['date_from']) != $fiscalYear['previous_fiscal_year_id']['date_to']) {
            return [
                'non_contiguous_years' => 'Fiscal year does not immediately follow the previous one.'
            ];
        }

        // fiscal year must have at least one period
        if(count($fiscalYear['fiscal_periods_ids']) === 0) {
            return [
                'missing_fiscal_periods' => 'Fiscal periods have not been defined.'
            ];

        }

        // #memo - number of periods is not taken into account here, but dates must be contiguous
        $periods = $fiscalYear['fiscal_periods_ids']->get(true);
        // #memo - at this stage 'order' might not have been set
        usort($periods, fn($a, $b) => $a['date_from'] <=> $b['date_from']);
        $n = count($periods);

        if($periods[$n-1]['date_to'] != $fiscalYear['date_to']) {
            return [
                'out_of_range_date_to' => 'Last fiscal period is outside the fiscal year.'
            ];
        }

        for($i = 0; $i <  $n - 1; $i++) {
            if($periods[$i]['date_from'] < $fiscalYear['date_from']) {
                return [
                    'out_of_range_date_from' => 'A fiscal period is outside the fiscal year.'
                ];
            }
            if($periods[$i]['date_to'] > $fiscalYear['date_to']) {
                return [
                    'out_of_range_date_to' => 'A fiscal period is outside the fiscal year.'
                ];
            }
            if(strtotime('+1 day', $periods[$i]['date_to']) !== $periods[$i + 1]['date_from']) {
                return [
                    'non_contiguous_periods' => 'A fiscal period year does not immediately follow the previous one.'
                ];
            }
        }

        return [];
    }

    public static function candelete($self) {
        $self->read(['status']);
        foreach($self as $fiscalYear) {
            if($fiscalYear['status'] != 'draft') {
                return ['status' => ['non_removable' => 'Non-draft fiscal years cannot be deleted.']];
            }
        }
        return parent::candelete($self);
    }

    public static function canupdate($self) {
        $self->read(['status']);
        foreach($self as $fiscalYear) {
            if(in_array($fiscalYear['status'], ['closed', 'archived'])) {
                return ['status' => ['not_allowed' => 'Closed fiscal year cannot be modified.']];
            }
        }
        return parent::canupdate($self);
    }

}
