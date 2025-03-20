<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\funding;

use realestate\ownership\Ownership;
use realestate\property\PropertyLotApportionmentShare;
use finance\accounting\Account;
use finance\accounting\AccountingEntry;
use finance\accounting\AccountingEntryLine;
use finance\accounting\FiscalPeriod;
use finance\accounting\Journal;

class FundRequest extends \equal\orm\Model {

    public static function getName() {
        return 'Fund Request';
    }

    public static function getDescription() {
        return "A Fund Request is a formal demand for payment issued by the property manager (syndic) to co-owners to cover shared expenses. Each co-owner's called amount is calculated based on their ownership share, ensuring the total matches the planned budget.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Short description of the request, based on fiscal year and period.",
                'required'          => true
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the fund request relates to.",
                'required'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'request_type' => [
                'type'              => 'string',
                'description'       => 'Type of fund request.',
                'selection'         => [
                    'working'           => 'Working Fund call',         // fond de roulement
                    'reserve'           => 'Reserve Fund call',         // fond de réserve
                    'expense'           => 'Expense provision call',    // provisions pour charge
                    'unique_expense'    => 'Unique expense provision'   // provision pour charge exceptionelle
                ],
                'required'           => true
            ],

            'request_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the request was emitted.',
                'visible'           => [['has_date_range', '=', false], ['request_type', '<>', 'expense']]
            ],

            'has_date_range' => [
                'type'              => 'boolean',
                'description'       => 'The execution of the request must be planned on a time range.',
                'default'           => false
            ],

            'date_range_frequency' => [
                'type'              => 'integer',
                'description'       => 'Interval, in months, between each execution of the request.',
                'default'           => 1,
                'visible'           => ['has_date_range', '=', true]
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => 'First day (included) of the date range.',
                'visible'           => ['has_date_range', '=', true]
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'Last day (included) of the date range.',
                'visible'           => ['has_date_range', '=', true]
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the invoice.',
                'selection'         => [
                    'draft',
                    'active',
                    'cancelled',
                ],
                'default'           => 'draft'
            ],

            'request_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcRequestAmount',
                'store'             => true,
                'readonly'          => true,
                'description'       => 'Total requested amount of the fund call.'
            ],

            'allocated_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcAllocatedAmount',
                'store'             => true,
                'readonly'          => true,
                'description'       => 'Total allocated amount currently assigned to co-owners.'
            ],

            'request_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestLine',
                'foreign_field'     => 'fund_request_id',
                'description'       => "Lines of the Fund request."
            ],

            'request_executions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'foreign_field'     => 'fund_request_id',
                'description'       => "Scheduled executions of the Fund request."
            ],

            'line_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestLineEntry',
                'foreign_field'     => 'fund_request_id',
                'description'       => "Line entries of the Fund request."
            ],

            'entry_lots_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestLineEntryLot',
                'foreign_field'     => 'fund_request_id',
                'description'       => "Line entries by lots of the Fund request."
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'Draft fund request, still waiting for completion.',
                'icon'        => 'draw',
                'transitions' => [
                    'activate' => [
                        'description' => 'Update the fund request to `active`.',
                        'policies'    => [
                            'is_balanced',
                        ],
                        'onafter'   => 'onafterActivate',
                        'status'    => 'active'
                    ]
                ]
            ],
        ];
    }

    public static function getActions() {
        return [
            'generate_allocation' => [
                'description'   => 'Generate the request lines according to the property lots of the condominium and their respective shares.',
                'policies'      => ['can_generate_lines'],
                'function'      => 'doGenerateLines'
            ],
            'generate_executions' => [
                'description'   => 'Generate the request lines according to the property lots of the condominium and their respective shares.',
                'policies'      => ['can_generate_executions'],
                'function'      => 'doGenerateExecutions'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_generate_lines' => [
                'description' => 'Verifies that a fund request is still a draft.',
                'function'    => 'policyCanGenerateLines'
            ],
            'can_generate_executions' => [
                'description' => 'Verifies that a fund request is still a draft.',
                'function'    => 'policyCanGenerateExecutions'
            ],
            'is_balanced' => [
                'description' => 'Verifies that request amount matches allocated amount.',
                'function'    => 'policyIsBalanced'
            ]
        ];
    }

    public static function policyCanGenerateLines($self): array {
        $result = [];
        $self->read(['status']);

        foreach($self as $id => $fundRequest) {
            if($fundRequest['status'] != 'draft') {
                $result[$id] = [
                    'invalid_status' => 'Fund request status must be draft.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function policyCanGenerateExecutions($self): array {
        $result = [];
        $self->read(['status']);

        foreach($self as $id => $fundRequest) {
        }
        return $result;
    }

    public static function policyIsBalanced($self): array {
        $result = [];
        $self->read(['request_amount', 'allocated_amount']);
        foreach($self as $id => $fundRequest) {
            if(round($fundRequest['request_amount'], 2) != round($fundRequest['allocated_amount'], 2)) {
                $result[$id] = [
                    'not_balanced' => 'A part of the request has not been called.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function calcAllocatedAmount($self) {
        $result = [];
        $self->read(['request_lines_ids' => ['allocated_amount']]);
        foreach($self as $id => $fundRequest) {
            if(empty($fundRequest['request_lines_ids'])) {
                continue;
            }
            $result[$id] = 0.0;
            foreach($fundRequest['request_lines_ids'] as $requestLine) {
                $result[$id] += $requestLine['allocated_amount'];
            }
        }
        return $result;
    }

    public static function calcRequestAmount($self) {
        $result = [];
        $self->read(['request_lines_ids' => ['request_amount']]);
        foreach($self as $id => $fundRequest) {
            if(empty($fundRequest['request_lines_ids'])) {
                continue;
            }
            $result[$id] = 0.0;
            foreach($fundRequest['request_lines_ids'] as $requestLine) {
                $result[$id] += $requestLine['request_amount'];
            }
        }
        return $result;
    }

    public static function doGenerateLines($self) {
        $self->read(['condo_id' => ['id', 'ownerships_ids'], 'request_lines_ids' => ['request_amount', 'apportionment_id' => ['id', 'total_shares']]]);

        foreach($self as $id => $fundRequest) {
            if (empty($fundRequest['request_lines_ids' ])) {
                continue;
            }
            // remove any previously created entry & entry lot
            FundRequestLineEntry::search(['fund_request_id', '=', $id])->delete(true);
            foreach($fundRequest['request_lines_ids'] as $request_line_id => $requestLine) {
                $map_property_lot_shares = [];
                $sum_delta = 0.0;
                foreach($fundRequest['condo_id']['ownerships_ids'] as $ownership_id) {
                    $lineEntry = FundRequestLineEntry::create([
                            'condo_id'          => $fundRequest['condo_id']['id'],
                            'fund_request_id'   => $id,
                            'request_line_id'   => $request_line_id,
                            'ownership_id'      => $ownership_id
                        ])
                        ->first();

                    $ownership = Ownership::id($ownership_id)->read(['property_lots_ids'])->first();
                    foreach($ownership['property_lots_ids'] as $property_lot_id) {

                        // if the lot has a share for this apportionment key, we add it
                        $apportionmentShare = PropertyLotApportionmentShare::search([
                                ['apportionment_id', '=', $requestLine['apportionment_id']['id']],
                                ['property_lot_id', '=', $property_lot_id]
                            ])
                            ->read(['property_lot_shares'])
                            ->first();

                        if($apportionmentShare) {
                            $map_property_lot_shares[$apportionmentShare['property_lot_shares']][] = $property_lot_id;
                            $amount = $requestLine['request_amount'] * ($apportionmentShare['property_lot_shares'] / $requestLine['apportionment_id']['total_shares']);
                            $precise_amount = round($amount, 4);
                            $rounded_amount = round($amount, 2);
                            $sum_delta += ($precise_amount - $rounded_amount);

                            FundRequestLineEntryLot::create([
                                    'condo_id'          => $fundRequest['condo_id']['id'],
                                    'fund_request_id'   => $id,
                                    'request_line_id'   => $request_line_id,
                                    'ownership_id'      => $ownership_id,
                                    'line_entry_id'     => $lineEntry['id'],
                                    'property_lot_id'   => $property_lot_id,
                                    'allocated_amount'     => $rounded_amount
                                ]);
                        }
                    }
                }
                $sum_delta = round($sum_delta, 2);
                if($sum_delta != 0.0) {
                    $remaining = $sum_delta;
                    // #todo - soit répartir sur les lots disposant du plus grand nombre de parts
                    /*
                    krsort($map_property_lot_shares);
                    $ordered_lots_ids = array_merge(...array_values($map_property_lot_shares));
                    foreach($ordered_lots_ids as $property_lot_id) {
                        if($remaining <= 0.0) {
                            break;
                        }
                    }
                    */
                    // soit créer une écriture de reliquats d'arrondi
                }

            }
            // reset computed fields
            $fundRequest['request_lines_ids']->update(['allocated_amount' => null]);
        }
        $self->update(['request_amount' => null, 'allocated_amount' => null]);
    }

    /**
     * - appel pour working fund est imputé en une seule fois : choisir une date
     * - appel pour reserve fund est arbitraire (une seule ou plusieurs fois): pouvoir choisir une plage de dates
     * - appel des provisions pour charge se font selon les périodes de l'exercice (pas de choix)
     * - une provision pour charge exceptionnelle est imputée en une seule fois: choisir date
     */
    public static function doGenerateExecutions($self) {

        $self->read([
                'condo_id',
                'fiscal_year_id',
                'request_type',
                'request_date',
                'has_date_range',
                'date_range_frequency',
                'date_from',
                'date_to',
                'request_amount',
                'line_entries_ids' => ['ownership_id', 'allocated_amount']
            ]);

        foreach($self as $id => $fundRequest) {
            // #todo - do not delete called executions
            FundRequestExecution::search(['fund_request_id', '=', $id])->delete(true);

            // create a request execution
            $execution_values = [
                    'condo_id'              => $fundRequest['condo_id'],
                    'fiscal_year_id'        => $fundRequest['fiscal_year_id'],
                    'fund_request_id'       => $id
                ];

            // retrieve execution dates
            $execution_dates = [];
            if($fundRequest['request_type'] == 'expense') {
                // fetch all periods from fiscal year, and use each first date
                $fiscal_periods = FiscalPeriod::search(['fiscal_year_id', '=', $fundRequest['fiscal_year_id']])
                    ->read(['date_from'])
                    ->get();
                foreach($fiscal_periods as $fiscal_period_id => $fiscal_period) {
                    $execution_dates[] = $fiscal_period['date_from'];
                }
            }
            elseif(!$fundRequest['has_date_range']) {
                $execution_dates[] = $fundRequest['request_date'];
            }
            else {
                $current_date = $fundRequest['date_from'];
                while($current_date < $fundRequest['date_to']) {
                    $execution_dates[] = $current_date;
                    $current_date = strtotime("+ {$fundRequest['date_range_frequency']} months", $current_date);
                }
            }

            // map of called amounts by ownership
            $map_ownership_amounts = [];
            foreach($fundRequest['line_entries_ids'] as $lineEntry) {
                if(!isset($map_ownership_amounts[$lineEntry['ownership_id']])) {
                    $map_ownership_amounts[$lineEntry['ownership_id']] = 0.0;
                }
                $map_ownership_amounts[$lineEntry['ownership_id']] += $lineEntry['allocated_amount'];
            }

            // retrieve called amount for each ownership, at each date
            $map_ownership_execution_amounts = [];

            foreach($map_ownership_amounts as $ownership_id => $allocated_amount) {
                $remaining_amount = $allocated_amount;
                $num_intervals = count($execution_dates);
                $base_amount = floor($allocated_amount / $num_intervals);

                foreach($execution_dates as $index => $execution_date) {
                    $called_amount = ($index == $num_intervals - 1) ? $remaining_amount : $base_amount;
                    $map_ownership_execution_amounts[$ownership_id][$execution_date] = $called_amount;
                    $remaining_amount -= $base_amount;
                }
            }

            foreach($execution_dates as $execution_date) {
                $execution_values['execution_date'] = $execution_date;
                $requestExecution = FundRequestExecution::create($execution_values)->first();

                foreach($map_ownership_execution_amounts as $ownership_id => $map_amounts) {
                    FundRequestExecutionLine::create([
                            'condo_id'              => $fundRequest['condo_id'],
                            'request_execution_id'  => $requestExecution['id'],
                            'ownership_id'          => $ownership_id,
                            'called_amount'         => $map_amounts[$execution_date]
                        ]);
                }
            }
        }

    }

    public static function canupdate($self, $values) {
        if(isset($values['has_date_range'])) {
            if($values['has_date_range']) {
                $self->read(['date_from', 'date_to']);
                if(!isset($values['date_from'], $values['date_to'])) {
                    foreach($self as $id => $fundRequest) {
                        if(!isset($fundRequest['date_from'])) {
                            return ['date_from' => ['invalid' => 'Date from cannot be empty.']];
                        }
                        if(!isset($fundRequest['date_to'])) {
                            return ['date_to' => ['invalid' => 'Date from cannot be empty.']];
                        }
                    }
                }
            }
            else {
                if(!isset($values['request_date'])) {
                    if(isset($values['request_type']) && $values['request_type'] != 'expense') {
                        return ['request_date' => ['missing' => 'Request date is mandatory.']];
                    }
                }
            }
        }
        if(isset($values['date_from']) || isset($values['date_to'])) {
            if(is_null($values['date_from'])) {
                return ['date_from' => ['invalid' => 'Date from cannot be empty.']];
            }
            if(is_null($values['date_to'])) {
                return ['date_to' => ['invalid' => 'Date to cannot be empty.']];
            }
        }
        return parent::canupdate($self, $values);
    }

    /**
     * Create accounting entries related to fund request.
     * - **debit** on the accounts **4101cccc** of the co-owners (identified by each co-owner)
     * - **credit** on the account **100000** (identified by the assignment code "working_fund")
     */
    public static function onafterActivate($self) {




    }
}
