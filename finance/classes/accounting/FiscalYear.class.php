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
                    'Q' => 'Quarterly',
                    'T' => 'Tertially' ,
                    'S' => 'Semi-Annually',
                    'A' => 'Annually'
                ],
                'description'       => 'List of employees assigned to the management of the condominium.',
                'help'              => 'This value is provided at creation and can originate either from condominium settings or entered manually.',
                'default'           => 'Q'
                /*
                Quarterly (3 months)	4	Q (Q1, Q2, Q3, Q4)
                Tertially (4 months)	3	T (T1, T2, T3)
                Semi-Annually (6 months)2	S (S1, S2)
                Annually (12 months)	1	A (A1)
                */
            ],

            'fiscal_periods_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'foreign_field'     => 'fiscal_year_id',
                'description'       => "The organisation the chart belongs to.",
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

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => 'Label for identifying the fiscal year.'
            ],

            'name' => [
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
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcPreviousFiscalYearId',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "The directly previous fiscal year, if any.",
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'preopen',
                    'open',
                    'closed',
                    'reopened',
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
        $self->read(['date_from', 'date_to']);
        foreach($self as $id => $year) {
            $name = '';

            if($year['date_from']) {
                $name .= date('Y-m-d', $year['date_from']);
            }
            $name .= ' - ';
            if($year['date_to']) {
                $name .= date('Y-m-d', $year['date_to']);
            }
            $result[$id] = $name;
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
                    ['date_to', '=', strtotime("-1 day", $fiscalYear['date_from'])]
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
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon'        => 'draw',
                'transitions' => [
                    'preopen' => [
                        'description' => 'Update the invoice status based on the `invoice` field.',
                        'help'        => 'The `invoice` field is set by a dedicated controller that manages invoice approval requests.',
                        // #todo #memo - si on peut faire des écritures dans un exercice pré-open, comment gérer la modification de l'assignation des périodes (sont-elles figées dès ce moment?)
                        'policies'    => [
                            'can_be_preopened',
                        ],
                        // 'onbefore'  => '',
                        'onafter'   => 'onafterPreOpen',
                        'status'    => 'preopen'
                    ]
                ]
            ],
            'preopen' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon'        => 'drive_file_rename_outline',
                'transitions' => [
                    'open' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'help'        => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'policies'    => [
                            'can_be_opened',
                        ],
                        'onafter'   => 'onafterOpen',
                        'status'  => 'open'
                    ]
                ]
            ],
            'open' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon'        => 'pending',
                'transitions' => [
                    'preclose' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'help'        => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'policies'    => [
                            'can_be_preclosed',
                        ],
                        // 'onafter'   => 'onafterPreClose',
                        'status'  => 'preclosed'
                    ]
                ]
            ],
            'preclosed' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon'        => 'lock_open',
                'transitions' => [
                    'close' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'help'        => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'policies'    => [
                            'can_be_closed',
                        ],
                        // 'onafter'   => 'onafterClose',
                        'status'  => 'closed'
                    ]
                ]
            ],
            'closed' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon'        => 'lock',
                'transitions' => [
                ]
            ]
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
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanBePreClosed'
            ]
        ];
    }



    public static function policyCanBePreOpened($self): array {
        $result = [];
        $self->read(['date_from', 'date_to']);

        $today = time();

        foreach($self as $id => $fiscalYear) {
            // date_from and date_to must be in the future
            if($today > $fiscalYear['date_from'] || $today > $fiscalYear['date_to']) {
                $result[$id] = false;
                continue;
            }
            if(!self::computeIsConsistent($id)) {
                $result[$id] = false;
            }
        }
        return $result;
    }

    public static function policyCanBeOpened($self): array {
        $result = [];
        $self->read(['date_from', 'date_to', 'previous_fiscal_year_id']);

        $today = time();

        foreach($self as $id => $fiscalYear) {

            // previous fiscal year status must be 'open'
            if($fiscalYear['previous_fiscal_year_id']) {
                if(count(self::policyCanBePreClosed(self::id($fiscalYear['previous_fiscal_year_id'])))) {
                    $result[$id] = false;
                    continue;
                }
            }

            // current date must be included between date_from and date_to
            if($today < $fiscalYear['date_from'] || $today > $fiscalYear['date_to']) {
                $result[$id] = false;
                continue;
            }

            // fiscal year and periods must be consistent
            if(!self::computeIsConsistent($id)) {
                $result[$id] = false;
            }
        }
        return $result;
    }

    public static function policyCanBePreClosed($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $fiscalYear) {
            if($fiscalYear['status'] != 'open') {
                $result[$id] = false;
                continue;
            }
        }
        return $result;
    }

    public static function onafterPreOpen($self) {
        $self->read(['condo_id']);
        foreach($self as $id => $fiscalYear) {
            // create a dedicated balance for the fiscal year
            $currentBalance = CurrentBalance::create([
                    'condo_id'          => $fiscalYear['condo_id'],
                    'fiscal_year_id'    => $id
                ])
                ->first();
            self::id($id)->update(['current_balance_id' => $currentBalance['id']]);
        }
    }

    /**
     * Perform tasks related to fiscal year opening.
     * This callback is called when Fiscal year status just switched to 'open'.
     * All tasks relating to fiscal year opening are performed here.
     *
     */
    public static function onafterOpen($self) {
        $self->read(['condo_id', 'code', 'date_from', 'date_to', 'previous_fiscal_year_id', 'fiscal_periods_ids' => ['id', 'date_from', 'date_to']]);

        foreach($self as $id => $fiscalYear) {
            // 1 - transition previous fiscal year to 'preclosed'
            self::id($fiscalYear['previous_fiscal_year_id'])->transition('preclose');

            // 2 - finalize periods
            $periods = $fiscalYear['fiscal_periods_ids']->get(true);
            usort($periods, fn($a, $b) => $a['date_from'] <=> $b['date_from']);
            $order = 1;
            foreach($periods as $period) {
                // assign final order for fiscal periods
                FiscalPeriod::id($period['id'])->update(['order' => $order]);
                // init mandatory sequence for purchase invoices
                $fiscal_year_code = $fiscalYear['code'];
                $fiscal_period_code = $order;
                Setting::assert_sequence('finance', 'accounting', "invoice.period_sequence.{$fiscal_year_code}.{$fiscal_period_code}");
                Setting::init_sequence('finance', 'accounting', "invoice.period_sequence.{$fiscal_year_code}.{$fiscal_period_code}", ['condo_id' => $fiscalYear['condo_id']]);
                ++$order;
            }

            // 3 - create temporary carry-forward / opening-balance accounting entries
            $entry_lines = self::computeCarryForwardEntryLines($id);
            $carryForwardJournal = Journal::search([['code', '=', 'OPB']])->first();
            $accountingEntry = AccountingEntry::create([
                    'condo_id'      => $fiscalYear['condo_id'],
                    'journal_id'    => $carryForwardJournal['id'],
                    'status'        => 'validated'
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

        }
    }

    public static function onafterClose($self) {

    }

    public static function doGeneratePeriods($self) {
        $self->read(['condo_id', 'date_from', 'date_to', 'fiscal_period_frequency']);
        foreach($self as $id => $fiscalYear) {
            if(!$fiscalYear['date_from']) {
                throw new \Exception('missing_date_from', EQ_ERROR_INVALID_PARAM);
            }
            if(!$fiscalYear['date_to']) {
                throw new \Exception('missing_date_to', EQ_ERROR_INVALID_PARAM);
            }
            FiscalPeriod::search(['fiscal_year_id', '=', $id])->delete(true);
            $periods = self::computeFiscalPeriods($fiscalYear['date_from'], $fiscalYear['date_to'], $fiscalYear['fiscal_period_frequency']);
            foreach($periods as $period) {
                FiscalPeriod::create([
                        'condo_id'          => $fiscalYear['condo_id'],
                        'fiscal_year_id'    => $id,
                        'date_from'         => $period['date_from'],
                        'date_to'           => $period['date_to']
                    ]);
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
                    'credit'        => $line['debit']
                ];
        }
        return $result;
    }

    /**
     * Check fiscal year and periods dates consistency.
     *
     */
    private static function computeIsConsistent($id): bool {
        $fiscalYear = self::id($id)->read(['date_from', 'date_to', 'fiscal_periods_ids' => ['date_from', 'date_to']])->first();


        if(!$fiscalYear['date_from'] || !$fiscalYear['date_to']) {
            return false;
        }

        if($fiscalYear['date_from'] >= $fiscalYear['date_to']) {
            return false;
        }

        // fiscal year must have at least one period
        if(count($fiscalYear['fiscal_periods_ids']) === 0) {
            return false;
        }

        // #memo - number of periods is not taken into account here, but dates must be contiguous
        $periods = $fiscalYear['fiscal_periods_ids']->get(true);
        // #memo - at this stage 'order' might not have been set
        usort($periods, fn($a, $b) => $a['date_from'] <=> $b['date_from']);
        $n = count($periods);

        if($periods[0]['date_from'] != $fiscalYear['date_from']) {
            return false;
        }

        if($periods[$n-1]['date_to'] != $fiscalYear['date_to']) {
            return false;
        }

        for($i = 0; $i <  $n - 1; $i++) {
            if($periods[$i]['date_from'] < $fiscalYear['date_from']) {
                return false;
            }
            if($periods[$i]['date_to'] > $fiscalYear['date_to']) {
                return false;
            }
            if(strtotime('+1 day', $periods[$i]['date_to']) !== $periods[$i + 1]['date_from']) {
                return false;
            }
        }

        return true;
    }
}