<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\funding;

use realestate\ownership\Ownership;
use realestate\property\PropertyLotApportionmentShare;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use realestate\property\Condominium;

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
                'default'           => 'defaultFiscalYearId',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true
            ],

            'request_type' => [
                'type'              => 'string',
                'description'       => 'Type of fund request.',
                'selection'          => [
                    'working_fund'        => 'Working Fund call',                       // fonds de roulement
                    'reserve_fund'        => 'Reserve Fund call',                       // fonds de réserve
                    'expense_provisions'  => 'Expense provision call',                  // provisions pour charge
                    'work_provisions'     => 'Provision call for exceptional expense'   // provision pour charge exceptionelle
                ],
                'default'           => 'working_fund',
                'required'          => true
            ],

            'request_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['operation_assignment', '=', 'object.request_type']]
            ],

            'request_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the request was emitted.',
                'visible'           => [['has_date_range', '=', false]]
            ],

            'request_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'Bank account to use for the request.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'payment_terms_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\PaymentTerms',
                'description'       => 'Payment terms to use for the request.',
                'domain'            => ['is_active', '=', true]
            ],

            'has_date_range' => [
                'type'              => 'boolean',
                'description'       => 'The execution of the request must be planned on a time range.',
                'default'           => false
            ],

            'date_range_frequency' => [
                'type'              => 'integer',
                'description'       => 'Interval, in months, between each execution.',
                'default'           => 1,
                'visible'           => ['has_date_range', '=', true]
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => 'First day (included) of the range.',
                'visible'           => ['has_date_range', '=', true]
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'Last day (included) of the range.',
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

            'execution_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestExecutionLine',
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

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Funding',
                'foreign_field'     => 'fund_request_id',
                'description'       => 'The fundings that relate to the fund request.'
            ]

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
                            'has_mandatory_data'
                        ],
                        'onafter'     => 'onafterActivate',
                        'status'      => 'active'
                    ]
                ]
            ],
            'active' => [
                'description' => 'Active fund request, consistent but with or without lines.',
                'icon'        => 'cancel',
                'transitions' => [
                    'cancel' => [
                        'description' => 'Cancel the fund request.',
                        'policies'    => ['can_cancel'],
                        'status'      => 'cancel'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return [
            'generate_allocation' => [
                'description'   => 'Generate the request lines according to the property lots of the condominium and their respective shares.',
                'policies'      => ['can_generate_allocation'],
                'function'      => 'doGenerateAllocation'
            ],
            'generate_executions' => [
                'description'   => 'Generate the request lines according to the property lots of the condominium and their respective shares.',
                'policies'      => ['is_balanced', 'can_generate_executions'],
                'function'      => 'doGenerateExecutions'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'has_mandatory_data' => [
                'description' => 'Checks & validate values required for activation.',
                'function'    => 'policyHasMandatoryData'
            ],
            'can_cancel' => [
                'description' => 'Verifies that there are no invoiced executions.',
                'function'    => 'policyCanCancel'
            ],
            'can_generate_allocation' => [
                'description' => 'Verifies that the allocation of a fund request can still be updated.',
                'function'    => 'policyCanGenerateAllocation'
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

    public static function policyCanCancel($self): array {
        $result = [];
        $self->read(['status', 'request_executions_ids' => ['status']]);
        foreach($self as $id => $fundRequest) {
            if($fundRequest['status'] == 'cancelled') {
                $result[$id] = [
                    'invalid_status' => 'Already cancelled.'
                ];
                continue;
            }
            foreach($fundRequest['request_executions_ids'] as $execution_id => $requestExecution) {
                if($requestExecution['status'] == 'invoice') {
                    $result[$id] = [
                        'invalid_execution_status' => 'At least one execution has been invoiced.'
                    ];
                    continue;
                }
            }
        }
        return $result;
    }

    public static function policyCanGenerateAllocation($self): array {
        $result = [];
        $self->read(['status', 'fiscal_year_id' => ['status']]);

        foreach($self as $id => $fundRequest) {
            if($fundRequest['status'] == 'cancelled') {
                $result[$id] = [
                    'invalid_status' => 'Cancelled fund requests cannot be updated.'
                ];
                continue;
            }
            if(!in_array($fundRequest['fiscal_year_id']['status'], ['open', 'preopen'])) {
                $result[$id] = [
                    'invalid_status' => 'Fiscal year must be open or pre-open.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function policyCanGenerateExecutions($self): array {
        $result = [];
        $self->read(['status', 'request_amount', 'allocated_amount']);

        foreach($self as $id => $fundRequest) {
            if($fundRequest['request_amount'] != $fundRequest['allocated_amount']) {
                $result[$id] = [
                    'non_balanced' => 'Allocated amount the request amount must match.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function policyHasMandatoryData($self): array {
        $result = [];
        $self->read(['condo_id', 'request_date', 'has_date_range', 'date_from', 'date_to', 'request_account_id', 'request_bank_account_id', 'payment_terms_id']);
        foreach($self as $id => $fundRequest) {
            if($fundRequest['has_date_range']) {
                if(!$fundRequest['date_from']) {
                    $result[$id] = [
                        'missing_date_from' => 'The start date of the time range is mandatory.'
                    ];
                }
                if(!$fundRequest['date_to']) {
                    $result[$id] = [
                        'missing_date_to' => 'The end date of the time range is mandatory.'
                    ];
                }
                if($fundRequest['date_from'] > $fundRequest['date_from']) {
                    $result[$id] = [
                        'invalid_date_interval' => 'The end date cannot be before start date.'
                    ];
                }
            }
            elseif(!$fundRequest['request_date']) {
                $result[$id] = [
                    'missing_date' => 'The date of the request is mandatory.'
                ];
            }

            if(!$fundRequest['condo_id']) {
                $result[$id] = [
                    'missing_condominium' => 'The condominium is mandatory.'
                ];
            }
            if(!$fundRequest['request_account_id']) {
                $result[$id] = [
                    'missing_accounting_account' => 'The accounting account is mandatory.'
                ];
            }
            if(!$fundRequest['request_bank_account_id']) {
                $result[$id] = [
                    'missing_bank_account' => 'The bank account is mandatory.'
                ];
            }
            if(!$fundRequest['payment_terms_id']) {
                $result[$id] = [
                    'missing_payment_terms' => 'The payment terms are mandatory.'
                ];
            }
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

    public static function defaultFiscalYearId($values) {
        if(isset($values['condo_id'])) {
            $condominium = Condominium::id($values['condo_id'])->read(['current_fiscal_year_id'])->first();
            if($condominium) {
                return $condominium['current_fiscal_year_id'];
            }
        }
        return null;
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

    public static function doGenerateAllocation($self) {
        $self->read([
                'request_date', 'has_date_range', 'date_from', 'date_to', 'request_type',
                'condo_id' => ['id', 'ownerships_ids'],
                'request_lines_ids' => ['request_amount', 'apportionment_id' => ['id', 'total_shares']]
            ]);

        foreach($self as $id => $fundRequest) {
            if(empty($fundRequest['request_lines_ids' ])) {
                continue;
            }

            $date_from = $fundRequest['has_date_range'] ? $fundRequest['date_from'] : $fundRequest['request_date'];

            // remove any previously created entry & entry lot
            FundRequestLineEntry::search(['fund_request_id', '=', $id])->delete(true);
            foreach($fundRequest['request_lines_ids'] as $request_line_id => $requestLine) {
                $map_property_lot_shares = [];
                $sum_delta = 0.0;
                foreach($fundRequest['condo_id']['ownerships_ids'] as $ownership_id) {

                    $ownership = Ownership::id($ownership_id)->read(['date_from', 'date_to', 'property_lots_ids'])->first();

                    // ignore ownerships outside of the fund request
                    if($ownership['date_to'] && $ownership['date_to'] < $date_from) {
                        continue;
                    }
                    if($fundRequest['has_date_range']) {
                        if($ownership['date_from'] && $ownership['date_from'] > $fundRequest['date_to'] ) {
                            continue;
                        }
                    }

                    $ratio = 1.0;

                    // In the case where the request is a time range and the ownership is partially within it, we need to allocate the called amount pro-rata based on the duration of the ownership.
                    // #memo - We assume that a property lot always belongs to someone and that the dates are contiguous in the event of a property transfer.
                    if($fundRequest['has_date_range'] && $fundRequest['request_type'] == 'expense_provisions') {
                        $intersect_from = $ownership['date_from'] ? max($ownership['date_from'], $fundRequest['date_from']) : $fundRequest['date_from'];
                        $intersect_to = $ownership['date_to'] ? min($ownership['date_to'], $fundRequest['date_to']) : $fundRequest['date_to'];
                        $total_days = ( ($fundRequest['date_to'] - $fundRequest['date_from']) / 86400 ) + 1;
                        $intersect_days = ( ($intersect_to - $intersect_from) / 86400 ) + 1;
                        $ratio = round($intersect_days / $total_days, 4);
                    }

                    $lineEntry = FundRequestLineEntry::create([
                            'condo_id'          => $fundRequest['condo_id']['id'],
                            'fund_request_id'   => $id,
                            'request_line_id'   => $request_line_id,
                            'ownership_id'      => $ownership_id
                        ])
                        ->first();

                    foreach($ownership['property_lots_ids'] as $property_lot_id) {

                        // if the lot has a share for this apportionment key, we add it
                        $apportionmentShare = PropertyLotApportionmentShare::search([
                                ['apportionment_id', '=', $requestLine['apportionment_id']['id']],
                                ['property_lot_id', '=', $property_lot_id]
                            ])
                            ->read(['property_lot_shares'])
                            ->first();

                        if($apportionmentShare) {

                            $amount = $ratio * $requestLine['request_amount'] * ($apportionmentShare['property_lot_shares'] / $requestLine['apportionment_id']['total_shares']);
                            $precise_amount = round($amount, 4);
                            $rounded_amount = round($amount, 2);
                            $sum_delta += ($precise_amount - $rounded_amount);

                            $entryLot = FundRequestLineEntryLot::create([
                                    'condo_id'              => $fundRequest['condo_id']['id'],
                                    'fund_request_id'       => $id,
                                    'request_line_id'       => $request_line_id,
                                    'ownership_id'          => $ownership_id,
                                    'line_entry_id'         => $lineEntry['id'],
                                    'property_lot_id'       => $property_lot_id,
                                    'apportionment_shares'  => $apportionmentShare['property_lot_shares'],
                                    'allocated_amount'      => $rounded_amount
                                ])
                                ->first();
                            $map_property_lot_shares[$apportionmentShare['property_lot_shares']][] = $entryLot['id'];
                        }
                    }
                }
                $sum_delta = round($sum_delta, 2);

                // handle residual amount, if any
                if($sum_delta != 0.0) {
                    $remaining = $sum_delta;
                    $step = $sum_delta > 0 ? 0.01 : -0.01;
                    trigger_error("APP::allocation generated a delta: $sum_delta", EQ_REPORT_DEBUG);

                    // distribute over the lots starting with those having the largest number of shares
                    krsort($map_property_lot_shares);
                    $ordered_lots_ids = array_merge(...array_values($map_property_lot_shares));
                    foreach($ordered_lots_ids as $line_entry_lot_id) {
                        $entryLot = FundRequestLineEntryLot::id($line_entry_lot_id)->read(['allocated_amount'])->first();
                        FundRequestLineEntryLot::id($line_entry_lot_id)->update(['allocated_amount' => $entryLot['allocated_amount'] + $step]);
                        $remaining -= $step;
                        if($remaining <= 0.0) {
                            break;
                        }
                    }
                    if(abs($remaining) >= 0.01) {
                        $line_entry_lot_id = reset($ordered_lots_ids);
                        $entryLot = FundRequestLineEntryLot::id($line_entry_lot_id)->read(['allocated_amount'])->first();
                        FundRequestLineEntryLot::id($line_entry_lot_id)->update(['allocated_amount' => $entryLot['allocated_amount'] + $remaining]);
                        $remaining = 0.0;
                    }
                }

            }
            // reset computed fields
            $fundRequest['request_lines_ids']->update(['allocated_amount' => null]);
        }
        $self->update(['request_amount' => null, 'allocated_amount' => null]);
    }

    /**
     *
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
                'payment_terms_id',
                'line_entries_ids' => ['ownership_id', 'allocated_amount']
            ]);

        foreach($self as $id => $fundRequest) {
            // delete non-called executions
            FundRequestExecution::search([['status', '=', 'proforma'], ['fund_request_id', '=', $id]])->delete(true);

            // create a request execution
            $execution_values = [
                    'condo_id'              => $fundRequest['condo_id'],
                    'fiscal_year_id'        => $fundRequest['fiscal_year_id'],
                    'payment_terms_id'      => $fundRequest['payment_terms_id'],
                    'fund_request_id'       => $id
                ];

            // retrieve execution dates
            $execution_dates = [];
            if(!$fundRequest['has_date_range']) {
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

            // pass-1 - remove dates for which a called execution remains

            foreach($execution_dates as $index => $execution_date) {
                // search for existing execution at the date (there should be 0 or 1)
                $existing_executions_ids = FundRequestExecution::search([['status', '=', 'invoice'], ['fund_request_id', '=', $id], ['emission_date', '=', $execution_date]])->ids();
                if(count($existing_executions_ids)) {
                    unset($execution_dates[$index]);
                    $executionLines = FundRequestExecutionLine::search(['request_execution_id', 'in', $existing_executions_ids])->read(['ownership_id', 'called_amount']);
                    foreach($executionLines as $executionLine) {
                        if(!isset($map_ownership_amounts[$executionLine['ownership_id']])) {
                            $map_ownership_amounts[$executionLine['ownership_id']] = 0.0;
                        }
                        $map_ownership_amounts[$executionLine['ownership_id']] -= $executionLine['called_amount'];
                    }
                }
            }
            // reset indexes
            $execution_dates = array_values($execution_dates);

            // pass-2 - create missing executions

            $num_intervals = count($execution_dates);

            if($num_intervals <= 0) {
                continue;
            }

            // keep track of the link between ownerships and request line entries
            $map_ownership_line_entries = [];
            foreach($fundRequest['line_entries_ids'] as $line_entry_id => $lineEntry) {
                if(!isset($map_ownership_amounts[$lineEntry['ownership_id']])) {
                    $map_ownership_amounts[$lineEntry['ownership_id']] = 0.0;
                }
                $map_ownership_amounts[$lineEntry['ownership_id']] += $lineEntry['allocated_amount'];
                $map_ownership_line_entries[$lineEntry['ownership_id']][] = $line_entry_id;
            }

            // retrieve called amount for each ownership, at each date
            $map_ownership_execution_amounts = [];

            foreach($map_ownership_amounts as $ownership_id => $allocated_amount) {
                $remaining_amount = $allocated_amount;
                $base_amount = floor($allocated_amount / $num_intervals);

                foreach($execution_dates as $index => $execution_date) {
                    $called_amount = ($index == $num_intervals - 1) ? $remaining_amount : $base_amount;
                    $map_ownership_execution_amounts[$ownership_id][$execution_date] = $called_amount;
                    $remaining_amount -= $base_amount;
                }
            }

            foreach($execution_dates as $execution_date) {
                $execution_values['emission_date'] = $execution_date;

                $requestExecution = FundRequestExecution::create($execution_values)->first();

                foreach($map_ownership_execution_amounts as $ownership_id => $map_amounts) {
                    $executionLine = FundRequestExecutionLine::create([
                            'condo_id'              => $fundRequest['condo_id'],
                            'fund_request_id'       => $id,
                            // #memo - request_execution_id is an alias of invoice_id
                            // 'request_execution_id'  => $requestExecution['id'],
                            'invoice_id'            => $requestExecution['id'],
                            'ownership_id'          => $ownership_id,
                            // 'called_amount'         => $map_amounts[$execution_date]
                            'total'                 => $map_amounts[$execution_date]
                        ])
                        ->first();
                    // link execution line and related line entries
                    FundRequestLineEntry::ids($map_ownership_line_entries[$ownership_id])->update(['execution_lines_ids' => [$executionLine['id']]]);
                }
            }
        }

    }

    public static function canupdate($self, $values) {

        $self->read(['status', 'has_date_range', 'date_from', 'date_to', 'request_date']);

        foreach($self as $id => $fundRequest) {
            $state = array_merge($fundRequest->toArray(), $values);

            if($state['status'] == 'active') {
                if(isset($values['has_date_range']) || isset($values['date_from']) || isset($values['date_to']) || isset($values['request_date'])) {
                    return ['status' => ['forbidden' => 'Dates and time range can no longer be changed.']];
                }
            }
            if($state['has_date_range']) {
                if(!isset($state['date_from'])) {
                    return ['date_from' => ['invalid' => 'Date from cannot be empty.']];
                }
                if(!isset($state['date_to'])) {
                    return ['date_to' => ['invalid' => 'Date from cannot be empty.']];
                }
            }
            else {
                if(!isset($state['request_date'])) {
                    return ['request_date' => ['missing' => 'Request date is mandatory.']];
                }
            }
        }
        return parent::canupdate($self, $values);
    }

    /**
     * Create accounting entries related to fund request.
     * - **debit** on the accounts **4101cccc** of the co-owners (identified by each co-owner)
     * - **credit** on the account **100000** (identified by the assignment code "working")
     */
    public static function onafterActivate($self) {

// non : on fait cela dans le FundRequestExecution


    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['request_type']) && $values['fiscal_year_id']) {
            if($event['request_type'] == 'expense_provisions') {
                $fiscalYear = FiscalYear::id($values['fiscal_year_id'])->read(['fiscal_period_frequency', 'date_from', 'date_to'])->first();
                $result['has_date_range'] = true;
                $result['date_range_frequency'] = ['A' => 12, 'S' => 6, 'T' => 4, 'Q' => 3][$fiscalYear['fiscal_period_frequency']];
                $result['date_from'] = $fiscalYear['date_from'];
                $result['date_to'] = $fiscalYear['date_to'];
            }
        }
        return $result;
    }
}
