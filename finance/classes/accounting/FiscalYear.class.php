<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use equal\orm\Model;
use fmt\setting\Setting;
use realestate\property\Condominium;

class FiscalYear extends Model {

    public static function getName() {
        return "Fiscal Year";
    }

    public static function getLink() {
        return "/app/#/condo/:condo_id/accounting/fiscal-year/object.id";
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
                'order'             => 'code',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'ondetach'          => 'delete'
            ],

            'opening_balance_id' => [
                'type'              => 'many2one',
                'description'       => "The opening balance of the fiscal year.",
                'foreign_object'    => 'finance\accounting\OpeningBalance',
                'ondelete'          => 'null'
            ],

            'closing_balance_id' => [
                'type'              => 'many2one',
                'description'       => "The closing (final) balance of the whole fiscal year.",
                'help'              => "The closing balance is generated once, when the fiscal year reaches the 'closed' status. Distinct closing balances can be generated for the periods of the fiscal year.",
                'foreign_object'    => 'finance\accounting\ClosingBalance',
                'ondelete'          => 'null'
            ],

            'closing_balances_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\ClosingBalance',
                'foreign_field'     => 'fiscal_year_id',
                'description'       => "The closing balance that refer to the fiscal year."
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
                'usage'             => 'date/plain',
                'description'       => 'First day of the fiscal year (included).',
                'dependents'        => ['name', 'code', 'previous_fiscal_year_id'],
                'onupdate'          => 'onupdateDateFrom'
            ],

            'date_to' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'Last day of the fiscal year (included).',
                'dependents'        => ['name', 'code', 'previous_fiscal_year_id'],
                'onupdate'          => 'onupdateDateTo'
            ],

            'previous_fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'function'          => 'calcPreviousFiscalYearId',
                'store'             => true,
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
                    'preclosed',
                    'closed'
                ],
                'default'           => 'draft',
                'description'       => 'Status of the fiscal year.',
                'dependents'        => ['name']
            ]

        ];
    }

    /**
     * This method is just for display and can be adapted if necessary
     */
    protected static function calcName($self) {
        $result = [];
        $self->read(['condo_id' => ['name'], 'status', 'date_from', 'date_to']);
        foreach($self as $id => $year) {
            if(!$year['date_from'] || !$year['date_to']) {
                continue;
            }

            $year_from = date('Y', $year['date_from']);
            $year_to = date('Y', $year['date_to']);

            $name = $year_from;

            if(strcmp($year_from, $year_to) !== 0) {
                $name .= '-' . $year_to;
            }
            $result[$id] = $name . ' (' . $year['status'] . ')';
        }
        return $result;
    }

    /**
     * This method cannot be changed: code is used as reference and its format must be kept as is.
     */
    protected static function calcCode($self) {
        $result = [];
        $self->read(['date_from', 'date_to']);
        foreach($self as $id => $fiscalYear) {
            $fiscal_year_from = date('Y', $fiscalYear['date_from']);
            $fiscal_year_to = date('Y', $fiscalYear['date_to']);
            $result[$id] = substr($fiscal_year_from, -2) . ( ($fiscal_year_from === $fiscal_year_to) ? '' : '-' . substr($fiscal_year_to, -2));
        }
        return $result;
    }

    /**
     * Retrieve previous fiscal year, if any, based on given date_from.
     */
    protected static function calcPreviousFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'date_from']);
        foreach($self as $id => $fiscalYear) {
            $fiscalYear = self::search([
                    ['condo_id', '=', $fiscalYear['condo_id']],
                    ['date_to', '=', strtotime('-1 day', $fiscalYear['date_from'])]
                ])
                ->read(['id'])
                ->first();

            if($fiscalYear) {
                $result[$id] = $fiscalYear['id'];
            }
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
                'policies'      => ['can_generate_sequences'],
                'function'      => 'doGenerateSequences'
            ],
            'attempt_transition' => [
                'description'   => 'Attempt to apply a transition on the FiscalYear.',
                'policies'      => [],
                'function'      => 'doAttemptTransition'
            ],
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
                        'policies' => [
                            'can_preopen'
                        ],
                        'onbefore' => 'onbeforePreopen',
                        'onafter' => 'onafterPreopen',
                        'status' => 'preopen'
                    ],
                    'open' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'help' => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'onafter' => 'onafterOpen',
                        'policies' => [
                            'can_open'
                        ],
                        'status' => 'open'
                    ],
                ],
            ],
            'preopen' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon' => 'drive_file_rename_outline',
                'transitions' => [
                    'open' => [
                        'description' => 'Mark a fiscal year as open and maintain consistency with previous and next years.',
                        'help' => 'A fiscal year can be opened before the previous one is definitely closed (previous has to be preclosed).',
                        'onafter' => 'onafterOpen',
                        'policies' => [
                            'can_open',
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
                        'onafter' => 'onafterPreclose',
                        'policies' => [
                            'can_preclose',
                        ],
                        'status' => 'preclosed',
                    ],
                    'close' => [
                        'description' => 'Handle actions related to fiscal year closing.',
                        'help' => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'onbefore' => 'onbeforeClose',
                        'onafter' => 'onafterClose',
                        'policies' => [
                            'can_close',
                        ],
                        'status' => 'closed',
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
                        'onbefore' => 'onbeforeClose',
                        'onafter' => 'onafterClose',
                        'policies' => [
                            'can_close',
                        ],
                        'status' => 'closed',
                    ],
                ],
            ],
            'closed' => [
                'description' => 'Draft fiscal year, still waiting to be completed for validation.',
                'icon' => 'lock',
                'transitions' => [
                    'repreclose' => [
                        'description' => 'Handle actions related to fiscal year closing.',
                        'help' => 'A fiscal year can be opened before the previous one is definitely closed.',
                        'policies' => [
                            'can_repreclose',
                        ],
                        'onafter' => 'onafterRePreclose',
                        'status' => 'preclosed',
                    ],
                ],
            ],
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_preopen' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanPreopen'
            ],
            'can_open' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanOpen'
            ],
            'can_preclose' => [
                'description' => 'Verifies that a fiscal year can be set (or set back) to preclosed status.',
                'function'    => 'policyCanPreclose'
            ],
            'can_close' => [
                'description' => 'Verifies that a fiscal year can be closed according its configuration.',
                'function'    => 'policyCanClose'
            ],
            'can_repreclose' => [
                'description' => 'Verifies that a fiscal year can be set back to preclosed status.',
                'function'    => 'policyCanRePreclose'
            ],
            'can_open_fiscal_year' => [
                'description' => 'Verifies that a fiscal year can be opened according to user roles.',
                'function'    => 'policyCanOpenFiscalYear'
            ],
            'can_generate_sequences' => [
                'description' => 'Verifies that a sequences can be generated.',
                'function'    => 'policyCanGenerateSequence'
            ]
        ];
    }

    protected static function policyCanGenerateSequence($self) {
        $result = [];
        $self->read(['condo_id', 'code', 'fiscal_periods_ids' => ['code']]);
        foreach($self as $id => $fiscalYear) {

            if(!isset($fiscalYear['code'])) {
                $result[$id] = [
                    'missing_fiscal_year_code' => 'Fiscal year without code.'
                ];
            }

            // init mandatory sequences
            foreach($fiscalYear['fiscal_periods_ids'] as $period_id => $fiscalPeriod) {
                if(!isset($fiscalPeriod['code'])) {
                    $result[$id] = [
                        'missing_fiscal_period_code' => 'Fiscal period without code.'
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * @param   \fmt\access\AccessController $access
     */
    protected static function policyCanOpenFiscalYear($self, $user_id, $access) {
        $result = [];

        $self->read(['condo_id' => ['id', 'creator']]);

        foreach($self as $id => $fiscalYear) {
            if($fiscalYear['condo_id']['creator'] === $user_id) {
                // #memo - this is necessary to allow auto opening at Condo import from an XLS file
                continue;
            }
            if(!$access->userHasCondoRole($user_id, ['manager', 'accountant'], $fiscalYear['condo_id']['id'])) {
                $result[$id] = [
                    'not_allowed' => 'User missing mandatory role.'
                ];
            }
        }

        return $result;
    }

    protected static function policyCanClose($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'date_to']);
        foreach($self as $id => $fiscalYear) {
            if(!in_array($fiscalYear['status'], ['open', 'preclosed'])) {
                $result[$id] = [
                    'invalid_status' => 'Fiscal year status must be open or preclosed.'
                ];
                continue;
            }
        }

        return $result;
    }

    protected static function policyCanPreopen($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'date_to']);

        foreach($self as $id => $fiscalYear) {
            // status of fiscal year must be 'draft'
            if($fiscalYear['status'] !== 'draft') {
                $result[$id] = [
                    'invalid_status' => 'Fiscal year status must be draft.'
                ];
                continue;
            }

            // a preopen year cannot preceed an open year
            $fiscalYears = FiscalYear::search([
                    ['condo_id', '=', $fiscalYear['condo_id']],
                    ['date_from', '>', $fiscalYear['date_to']],
                    ['id', '<>', $id],
                    ['status', '=', 'open']
                ]);

            if($fiscalYears->count() > 0) {
                $result[$id] = [
                    'invalid_status' => 'Fiscal year cannot preceed a fiscal year already open.'
                ];
                continue;
            }

            $inconsistency = self::computeIsValid($id);
            if(count($inconsistency)) {
                $result[$id] = $inconsistency;
            }
        }
        return $result;
    }

    protected static function policyCanOpen($self): array {
        $result = [];
        $self->read(['status', 'previous_fiscal_year_id', 'date_from', 'date_to', 'condo_id']);

        foreach($self as $id => $fiscalYear) {

            $nextFiscalYear = self::search([
                    ['id', '<>', $id],
                    ['date_from', '>', $fiscalYear['date_to']],
                    ['condo_id', '=', $fiscalYear['condo_id']]
                ],
                ['limit' => 1, 'sort'  => ['date_from' => 'asc']]);

            if(!$nextFiscalYear->first()) {
                $result[$id] = [
                    'missing_next_fiscal_year' => 'Next fiscal year must exist.'
                ];
                continue;
            }

            if(!empty(self::policyCanPreopen($nextFiscalYear))) {
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

            /*
            if($fiscalYear['previous_fiscal_year_id']) {
                if(!empty(self::policyCanPreclose(self::id($fiscalYear['previous_fiscal_year_id'])))) {
                    $result[$id] = [
                        'invalid_previous_fiscal_year' => 'Previous fiscal year cannot be pre closed.'
                    ];
                    continue;
                }
            }
            */

            // fiscal year and periods must be consistent (each year and period must immediately follow the previous one)
            $inconsistency = self::computeIsValid($id);
            if(count($inconsistency)) {
                $result[$id] = $inconsistency;
            }
        }
        return $result;
    }

    public static function policyCanPreclose($self): array {
        $result = [];
        $self->read([
            'status', 'condo_id', 'date_to',
            'fiscal_periods_ids' => ['status'],
            'previous_fiscal_year_id' => ['status']
        ]);

        foreach($self as $id => $fiscalYear) {
            if($fiscalYear['status'] !== 'open') {
                $result[$id] = [
                    'invalid_status' => 'Fiscal year status must be open or closed.'
                ];
                continue;
            }
            if($fiscalYear['previous_fiscal_year_id']) {
                // status of previous fiscal year, if any, must be 'closed'
                if($fiscalYear['previous_fiscal_year_id']['status'] !== 'closed') {
                    $result[$id] = [
                        'invalid_previous_year_status' => 'Fiscal year status must be closed or preclosed.'
                    ];
                    continue;
                }
            }
            // all periods of the fiscal year must be closed
            foreach($fiscalYear['fiscal_periods_ids'] as $fiscalPeriod) {
                if(!in_array($fiscalPeriod['status'], ['preclosed', 'closed'])) {
                    $result[$id] = [
                        'invalid_period_status' => 'All fiscal periods of the fiscal year must be closed.'
                    ];
                    continue 2;
                }
            }
        }
        return $result;
    }

    /**
     * Check for reverting from Closed to Preclosed status.
     * This is useful when the fiscal year is closed by mistake or when we need to reopen it for a short period of time (e.g. to make some adjustments after closing).
     */
    public static function policyCanRePreclose($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'date_to', 'previous_fiscal_year_id' => ['status']]);

        foreach($self as $id => $fiscalYear) {
            // if we go back from 'closed' to 'preclosed', the fiscal year must be the latest closed one
            if($fiscalYear['status'] == 'closed') {
                $closedFiscalYears = self::search([
                    ['status', '=', 'closed'],
                    ['date_from', '>', $fiscalYear['date_to']],
                    ['condo_id', '=', $fiscalYear['condo_id']]
                ]);
                if(count($closedFiscalYears) > 0) {
                    $result[$id] = [
                        'cannot_be_reopen' => 'Fiscal year cannot be reopen while a more recent fiscal year is still closed.'
                    ];
                    continue;
                }
            }
            if($fiscalYear['status'] !== 'closed') {
                $result[$id] = [
                    'invalid_status' => 'Fiscal year status must be open or closed.'
                ];
                continue;
            }
            if($fiscalYear['previous_fiscal_year_id']) {
                // status of previous fiscal year, if any, must be 'closed'
                if($fiscalYear['previous_fiscal_year_id']['status'] !== 'closed') {
                    $result[$id] = [
                        'invalid_previous_year_status' => 'Fiscal year status must be closed or preclosed.'
                    ];
                    continue;
                }
            }
            // find the year that immediately succeeds the current one, whatever its status
            $nextFiscalYear = self::search([
                        ['condo_id', '=', $fiscalYear['condo_id']],
                        ['date_from', '>', $fiscalYear['date_to']]
                    ],
                    ['sort' => ['date_from' => 'asc']]
                )
                ->read(['status'])
                ->first();

            if(!$nextFiscalYear) {
                $result[$id] = [
                    'missing_next_year' => 'A next fiscal year must exist.'
                ];
                continue;
            }
        }
        return $result;
    }

    /**
    * Attempts to perform a transition to the specified state ($values['status']).
    * Does nothing if the fiscal year is already in the target state.
     */
    protected static function doAttemptTransition($self, $values) {
        $self->read(['condo_id', 'status']);
        $map_status_transition = [
                'open'          => 'open',
                'preopen'       => 'preopen',
                'preclosed'     => 'preclose',
                'closed'        => 'close',
            ];
        foreach($self as $id => $fiscalYear) {
            if(!isset($values['status'])) {
                continue;
            }
            // #memo - $values['status'] is the target status
            if($values['status'] === $fiscalYear['status']) {
                continue;
            }
            if(!isset($map_status_transition[$values['status']])) {
                continue;
            }
            self::id($id)->transition($map_status_transition[$values['status']]);
        }
    }

    protected static function onbeforePreopen($self) {
        $self->read(['condo_id', 'fiscal_periods_ids']);
        foreach($self as $id => $fiscalYear) {
            if(count($fiscalYear['fiscal_periods_ids']) <= 0) {
                self::id($id)->do('generate_periods');
            }
        }
    }

    /**
     * Perform tasks related to fiscal year pre-opening.
     * This method assigns a current balance to it (which will not change, whatever the final duration of the fiscal year).
     * A preopened fiscal year cannot be removed anymore.
     *
     */
    protected static function onafterPreopen($self) {
        $self->read(['condo_id', 'date_to', 'fiscal_period_frequency', 'fiscal_periods_ids' => ['date_from']]);
        foreach($self as $id => $fiscalYear) {

            // #todo - use Condo::do('create_draft_fiscal_year')

            // retrieve next fiscal year (take the year that immediately succeeds the current one, whatever its status)
            $nextFiscalYear = self::search([
                    ['condo_id', '=', $fiscalYear['condo_id']],
                    ['date_from', '>', $fiscalYear['date_to']]
                ],
                ['sort' => ['date_from' => 'asc']])
                ->first();

            if(!$nextFiscalYear) {
                $date_from = strtotime(date('Y-m-d 00:00:00', $fiscalYear['date_to']) . ' +1 day');
                FiscalYear::create([
                        'date_from'                 => $date_from,
                        'date_to'                   => strtotime('-1 day', strtotime(date('Y-m-d 00:00:00', $date_from) . ' +1 year')),
                        'condo_id'                  => $fiscalYear['condo_id'],
                        'fiscal_period_frequency'   => $fiscalYear['fiscal_period_frequency'],
                        'status'                    => 'draft',
                    ]);
            }

            // finalize periods order
            FiscalPeriod::ids($fiscalYear['fiscal_periods_ids'])
                ->update(['code' => null, 'name' => null])
                ->read(['code']);

            self::id($id)->update(['name' => null]);
        }

        // generate sequences for the fiscal year
        $self->do('generate_sequences');
    }

    protected static function onafterRePreclose($self) {
        $self->read(['condo_id', 'date_to', 'fiscal_periods_ids' => ['id', 'name', '@sort' => ['date_to' => 'desc'], '@limit' => 1]]);
        foreach($self as $id => $fiscalYear) {
            // set back last period to preclosed status
            $fiscalYear['fiscal_periods_ids']->transition('repreclose');
            // take the year that immediately succeeds the current one, whatever its status
            $nextFiscalYear = self::search([
                        ['condo_id', '=', $fiscalYear['condo_id']],
                        ['date_from', '>', $fiscalYear['date_to']]
                    ],
                    ['sort' => ['date_from' => 'asc']]
                )
                ->read(['status'])
                ->first();

            // remove OpeningBalance of Year+1
            OpeningBalance::search(['fiscal_year_id', '=', $nextFiscalYear['id']])->delete(true);
            // remove ClosingBalance
            ClosingBalance::search(['fiscal_year_id', '=', $id])->delete(true);
        }
    }

    /*
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
    */

    /**
     * Perform tasks related to fiscal year opening.
     * This callback is called when Fiscal year status just switched to 'open'.
     * All tasks relating to fiscal year opening are performed here.
     *
     */
    public static function onafterOpen($self) {
        $self->read(['condo_id', 'date_from', 'date_to', 'previous_fiscal_year_id' => ['status'], 'fiscal_periods_ids' => ['id', 'date_from', 'date_to']]);

        foreach($self as $id => $fiscalYear) {

            // 1 - transition previous fiscal year to 'preclosed' (transition has been checked in `policyCanOpen()`)
            /*
            NO
            if($fiscalYear['previous_fiscal_year_id'] && $fiscalYear['previous_fiscal_year_id']['status'] === 'open') {
                self::id($fiscalYear['previous_fiscal_year_id'])->transition('preclose');
            }
            */

            // 2 - transition next fiscal year to 'preopen' (existence and transition have been checked in `policyCanOpen()`)

            // retrieve next fiscal year (take the year that immediately succeeds the current one, whatever its status)
            $nextFiscalYear = self::search([
                    ['condo_id', '=', $fiscalYear['condo_id']],
                    ['date_from', '>', $fiscalYear['date_to']]
                ],
                ['sort' => ['date_from' => 'asc']])
                ->first();

            if(!$nextFiscalYear) {
                throw new \Exception('missing_mandatory_next_fiscal_year', EQ_ERROR_UNKNOWN);
            }

            self::id($nextFiscalYear['id'])->transition('preopen')->first();


            // 3 - finalize periods order
            // #memo - moved to preopen

            // 4 - create temporary carry-forward / opening-balance accounting entries in next fiscal year OPB journal
            // #memo - this is done exclusively in FiscalYear (pre)closing

            /*
            $carryForwardJournal = Journal::search([['condo_id', '=', $fiscalYear['condo_id']], ['journal_type', '=', 'OPEN']])->first();
            if(!$carryForwardJournal) {
                throw new \Exception('missing_opb_journal', EQ_ERROR_INVALID_CONFIG);
            }

            $entry_lines = self::computeCarryForwardEntryLines($id);

            if(count($entry_lines)) {

                $accountingEntry = AccountingEntry::create([
                        'condo_id'          => $fiscalYear['condo_id'],
                        'journal_id'        => $carryForwardJournal['id'],
                        'is_temp'           => true,
                        'fiscal_year_id'    => $nextFiscalYear['id'],
                        'entry_date'        => time()
                    ])
                    ->first();

                foreach($entry_lines as $line) {

                    $newLine = AccountingEntryLine::create([
                            'condo_id'              => $fiscalYear['condo_id'],
                            'accounting_entry_id'   => $accountingEntry['id'],
                            'account_id'            => $line['account_id'],
                            'debit'                 => $line['debit'],
                            'credit'                => $line['credit']
                        ])
                        ->first();

                    Matching::create([
                            'condo_id'              => $fiscalYear['condo_id'],
                            'accounting_account_id' => $line['account_id']
                        ])
                        ->update(['accounting_entry_lines_ids' => [$line['id'], $newLine['id']]]);

                }

                AccountingEntry::id($accountingEntry['id'])->transition('validate');

            }
            */

            // 5 - update current fiscal year for targeted Condominium
            Condominium::id($fiscalYear['condo_id'])
                ->update([
                    'current_fiscal_year_id' => $id
                ]);

            self::id($id)->update(['name' => null]);
        }

    }

    /**
     * Upon creation of a fiscal year (onafterOpen), it is necessary to create sequences for:
     * - sale invoices:        sale.accounting.invoice.sequence.{fiscal_year_code}                                                  [condo_id]
     * - purchase invoices:    purchase.accounting.invoice.sequence.{fiscal_year_code}.{fiscal_period_code}                         [condo_id]
     * - accounting entries:   finance.accounting.accounting_entry.sequence.{fiscal_year_code}.{fiscal_period_code}.{journal_code}  [condo_id]
     */
    public static function doGenerateSequences($self) {
        $self->read(['condo_id', 'code', 'fiscal_periods_ids' => ['code']]);
        foreach($self as $id => $fiscalYear) {
            $fiscal_year_code = $fiscalYear['code'];

            $journals = Journal::search([['journal_type', '<>', 'LEDG'], ['condo_id', '=', $fiscalYear['condo_id']]])
                ->read(['code', 'sub_journals_ids' => ['code'] ]);

            // init mandatory sequences
            foreach($fiscalYear['fiscal_periods_ids'] as $period_id => $fiscalPeriod) {
                $fiscal_period_code = $fiscalPeriod['code'] ?? '1';

                // sale invoices
                Setting::assert_sequence('sale', 'accounting', "invoice.sequence.{$fiscal_year_code}.{$fiscal_period_code}", 1, ['condo_id' => $fiscalYear['condo_id']]);

                // purchase invoices
                Setting::assert_sequence('purchase', 'accounting', "invoice.sequence.{$fiscal_year_code}.{$fiscal_period_code}", 1, ['condo_id' => $fiscalYear['condo_id']]);

                // create accounting entries sequences for all existing journals
                foreach($journals as $journal) {
                    $journal_code = $journal['code'];
                    Setting::assert_sequence('finance', 'accounting', "accounting_entry.sequence.{$fiscal_year_code}.{$fiscal_period_code}.{$journal_code}", 1, ['condo_id' => $fiscalYear['condo_id']]);

                    // create sequences for sub-journals, if any
                    if($journal['sub_journals_ids'] && count($journal['sub_journals_ids'])) {
                        foreach($journal['sub_journals_ids'] as $sub_journal) {
                            $sub_journal_code = $sub_journal['code'];
                            Setting::assert_sequence('finance', 'accounting', "accounting_entry.sequence.{$fiscal_year_code}.{$fiscal_period_code}.{$sub_journal_code}", 1, ['condo_id' => $fiscalYear['condo_id']]);
                        }
                    }
                }

            }

        }
    }

    protected static function onbeforeClose($self) {

        $self->read(['condo_id', 'closing_balance_id', 'date_from', 'date_to']);

        foreach($self as $id => $fiscalYear) {

            // 2) generate closing balance for the fiscal year

            // remove any previously created closing balance
            if($fiscalYear['closing_balance_id']) {
                ClosingBalance::id($fiscalYear['closing_balance_id'])->delete(true);
            }

            // generate a closing balance
            $closingBalance = ClosingBalance::create([
                    'condo_id'       => $fiscalYear['condo_id'],
                    'fiscal_year_id' => $id
                ])
                ->transition('validate')
                ->first();

            self::id($id)->update(['closing_balance_id' => $closingBalance['id']]);

            // #memo - OpeningBalance for next fiscal year is created in `onafterClose`
        }
    }

    /**
     * #memo - closing balance accounting entries are generated in onbeforeClose
     */
    protected static function onafterClose($self) {
        $self->read(['condo_id', 'date_from', 'date_to']);

        foreach($self as $id => $fiscalYear) {

            // retrieve next fiscal year and attempt to open it
            try {
                // take the year that immediately succeeds the current one, whatever its status
                $nextFiscalYear = self::search([
                            ['condo_id', '=', $fiscalYear['condo_id']],
                            ['date_from', '>', $fiscalYear['date_to']]
                        ],
                        ['sort' => ['date_from' => 'asc']]
                    )
                    ->read(['status'])
                    ->first();

                if(!$nextFiscalYear) {
                    throw new \Exception('missing_mandatory_next_fiscal_year', EQ_ERROR_UNKNOWN);
                }

                if($nextFiscalYear['status'] !== 'preopen') {
                    throw new \Exception('invalid_status_for_next_fiscal_year', EQ_ERROR_UNKNOWN);
                }

                // remove any previously created opening balance
                OpeningBalance::search([
                        ['condo_id', '=', $fiscalYear['condo_id']],
                        ['fiscal_year_id', '=', $nextFiscalYear['id']]
                    ])
                    ->delete(true);

                $openingBalance = OpeningBalance::create([
                        'condo_id'       => $fiscalYear['condo_id'],
                        'fiscal_year_id' => $nextFiscalYear['id']
                    ])
                    ->transition('validate')
                    ->first();

                self::id($nextFiscalYear['id'])->update(['opening_balance_id' => $openingBalance['id']]);

                // transition following year according to logic
                // this will cascade on all future fiscal years (next to 'open' and post next to 'preopen')
                self::id($nextFiscalYear['id'])->transition('open');
            }
            catch(\Exception $e) {
                trigger_error("APP::unexpected inconsistency with following fiscal years" . $e->getMessage(), EQ_REPORT_ERROR);
            }

            self::id($id)->update(['name' => null]);
        }
    }

    /**
     * Generate a temporary Closing Balance.
     *
     */
    protected static function onafterPreclose($self) {
        $self->read(['condo_id', 'closing_balance_id', 'date_from', 'date_to']);

        foreach($self as $id => $fiscalYear) {

            // remove any previously created closing balance
            if($fiscalYear['closing_balance_id']) {
                ClosingBalance::id($fiscalYear['closing_balance_id'])->delete(true);
            }

            // generate a draft/preview (pending) closing balance
            $closingBalance = ClosingBalance::create([
                    'condo_id'       => $fiscalYear['condo_id'],
                    'fiscal_year_id' => $id
                ])
                ->do('generate_balance_lines');

            self::id($id)->update([
                    'name' => null,
                    'closing_balance_id' => $closingBalance['id']
                ]);
        }
    }

    protected static function doGeneratePeriods($self) {
        $self->read(['date_from', 'date_to', 'previous_fiscal_year_id', 'fiscal_period_frequency', 'condo_id' => ['id', 'fiscal_year_start', 'fiscal_year_end']]);
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
            $n = count($periods);

            foreach($periods as $period) {
                // handle special case for first fiscal year of a Condominium
                if($is_first) {
                    if($i == 0 && isset($fiscalYear['condo_id']['fiscal_year_start'])) {
                        $day   = date('d', $fiscalYear['condo_id']['fiscal_year_start']);
                        $month = date('m', $fiscalYear['condo_id']['fiscal_year_start']);
                        $year  = date('Y', $period['date_from']);

                        $adjusted_start = strtotime("$year-$month-$day");

                        if($adjusted_start !== $period['date_from']) {
                            $period['date_from'] = $adjusted_start;
                        }
                    }
                    elseif($i == $n-1 && isset($fiscalYear['condo_id']['fiscal_year_end'])) {
                        $day   = date('d', $fiscalYear['condo_id']['fiscal_year_end']);
                        $month = date('m', $fiscalYear['condo_id']['fiscal_year_end']);
                        $year  = date('Y', $period['date_to']);

                        $adjusted_end = strtotime("$year-$month-$day");

                        if($adjusted_end !== $period['date_to']) {
                            $period['date_to'] = $adjusted_end;
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
            if(!$i) {
                trigger_error("APP::Failed creating periods from {$fiscalYear['date_from']} to {$fiscalYear['date_to']} for condo {$fiscalYear['condo_id']['id']}", EQ_REPORT_ERROR);
                throw new \Exception('failed_creating_fiscal_periods', EQ_ERROR_UNKNOWN);
            }
        }
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
     * Build an array with carry forward entries.
     *
     */
    /*
    private static function computeCarryForwardEntryLines($id): array {
        $result = [];

        $closingBalance = ClosingBalance::search([
                ['fiscal_year_id', '=', $id]
            ])
            ->read([
                'balance_lines_ids' => [
                    'account_id',
                    'debit_balance',
                    'credit_balance'
                ]
            ])
            ->first();

        if(!$closingBalance) {
            return [];
        }

        $map_accounts_ids = [];

        foreach($closingBalance['balance_lines_ids'] as $line) {
            $map_accounts_ids[$line['account_id']] = true;
        }

        $accounts = Account::ids(array_keys($map_accounts_ids))->read(['account_type']);

        $map_account_types = [];
        foreach($accounts as $id => $account) {
            $map_account_types[$id] = $account['account_type'];
        }

        foreach($closingBalance['balance_lines_ids'] as $line) {

            if($map_account_types[$line['account_id']] !== 'B') {
                continue;
            }

            $result[] = [
                'account_id' => $line['account_id'],
                'debit'      => $line['credit_balance'],
                'credit'     => $line['debit_balance']
            ];
        }

        return $result;
    }
    */

    /**
     * Check fiscal year and periods dates consistency.
     *
     */
    private static function computeIsValid($id): array {
        $fiscalYear = self::id($id)->read(['condo_id', 'date_from', 'date_to', 'previous_fiscal_year_id' => ['date_to'], 'fiscal_periods_ids' => ['date_from', 'date_to']])->first();


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

        $condoFiscalYears = FiscalYear::search([['condo_id', '=', $fiscalYear['condo_id']], ['status', '<>', 'draft'], ['id', '<>', $id]])
            ->read(['date_from', 'date_to']);

        foreach($condoFiscalYears as $fiscal_year_id => $condoFiscalYear) {
            $overlap = ($fiscalYear['date_from'] <= $condoFiscalYear['date_to']) && ($fiscalYear['date_to'] >= $condoFiscalYear['date_from']);

            if($overlap) {
                return [
                    'overlapping_years' => 'At least one other fiscal year overlaps with this one.'
                ];
            }
        }


        // #memo - if missing, periods will be generated in onbeforePreopen

        if(count($fiscalYear['fiscal_periods_ids']) > 0) {
            // #memo - number of periods is not taken into account here, but dates must be contiguous
            $periods = $fiscalYear['fiscal_periods_ids']->get(true);
            // #memo - at this stage 'code' might not have been set
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
        }

        return [];
    }

    protected static function onupdateDateFrom($self) {
        $self->do('generate_periods');
    }

    protected static function onupdateDateTo($self) {
        $self->do('generate_periods');
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

    public static function canupdate($self, $values) {
        $self->read(['status', 'date_to']);
        foreach($self as $id => $fiscalYear) {
            $allowed_fields = ['name'];
            if(count(array_diff(array_keys($values), $allowed_fields)) > 0) {
                if(in_array($fiscalYear['status'], ['closed', 'archived'])) {
                    return ['status' => ['not_allowed_closed' => 'Closed fiscal year cannot be modified.']];
                }
                if($fiscalYear['status'] <> 'draft') {
                    // if modifying the end date AND there are no accounting entries for this fiscal year beyond that date: allow
                    if(isset($values['date_to'])) {
                        $accounting_entries_ids = AccountingEntry::search([
                                ['fiscal_year_id', '=', $id],
                                ['entry_date', '>', $values['date_to']],
                                ['is_cancelled', '=', false],
                                ['status', '=', 'validated']
                            ])
                            ->ids();
                        if(count($accounting_entries_ids) > 0) {
                            return ['status' => ['not_allowed_entries' => 'There are accounting entries for the Fiscal year after given end date.']];
                        }
                    }
                    // otherwise always refuse
                    elseif(isset($values['fiscal_periods_ids']) ||
                        isset($values['date_from']) ||
                        isset($values['condo_id']) ||
                        isset($values['organisation_id'])
                    ) {
                        return ['status' => ['not_allowed' => 'Fiscal year configuration cannot be modified once published.']];
                    }
                }
            }
        }
        return parent::canupdate($self);
    }

}
