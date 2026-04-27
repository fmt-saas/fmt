<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

use finance\accounting\FiscalPeriod;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;
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
                'domain'            => [['condo_id', '<>', null], ['condo_id', '=', 'object.condo_id']],
                'required'          => true
            ],

            'fiscal_period_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the invoice statement relates to.",
                'help'              => "Posting date is automatically assigned on the last day of the period.",
                'domain'            => [['condo_id', '<>', null], ['condo_id', '=', 'object.condo_id'], ['fiscal_year_id', '=', 'object.fiscal_year_id']]
            ],

            'request_type' => [
                'type'              => 'string',
                'description'       => 'Type of fund request.',
                'selection'          => [
                    'working_fund'        => 'Working Fund call',                       // fonds de roulement
                    'reserve_fund'        => 'Reserve Fund call',                       // fonds de réserve
                    'special_reserve_fund'=> 'Special Reserve Fund call',               // fonds de réserve particulier
                    'expense_provisions'  => 'Expense provision call',                  // provisions pour charge
                    'work_provisions'     => 'Provision call for exceptional expense'   // provision pour charge exceptionnelle
                ],
                'default'           => 'working_fund',
                'required'          => true
            ],

            'request_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'ondelete'          => 'null',
                'domain'            => [
                        ['condo_id', '<>', null],
                        ['condo_id', '=', 'object.condo_id'],
                        ['operation_assignment', '=', 'object.request_type'],
                        ['is_control_account', '=', false]
                    ]
            ],

            'request_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'Date at which the request was emitted.',
                'visible'           => [['has_date_range', '=', false]]
            ],

            'request_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'Bank account to use for the request.',
                'domain'            => [
                    ['condo_id', '<>', null],
                    ['condo_id', '=', 'object.condo_id'],
                    ['object_class', '=', 'finance\bank\CondominiumBankAccount']
                ]
            ],

            'payment_terms_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\PaymentTerms',
                'description'       => 'Payment terms to use for the request.',
                'domain'            => ['is_active', '=', true]
            ],

            'has_ownership_proration' => [
                'type'              => 'boolean',
                'description'       => 'If active, amount requested for is splitted between ownerships impacted by a Transfer within each period.',
                'default'           => false
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
                'usage'             => 'date/plain',
                'description'       => 'First day (included) of the range.',
                'visible'           => ['has_date_range', '=', true],
                'onupdate'          => 'onupdateDateFrom',
            ],

            'date_to' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
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
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'fund_request_id',
                'description'       => 'The fundings that relate to the execution (sale invoice).'
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
            if($fundRequest['status'] === 'cancelled') {
                $result[$id] = [
                    'invalid_status' => 'Already cancelled.'
                ];
                continue;
            }
            foreach($fundRequest['request_executions_ids'] as $execution_id => $requestExecution) {
                if($requestExecution['status'] == 'posted') {
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
            if($fundRequest['status'] === 'cancelled') {
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
                    'non_balanced' => 'Allocated amount and request amount must match.'
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
                if($fundRequest['request_date'] != $fundRequest['date_from']) {
                    $result[$id] = [
                        'inconsistent_request_date' => 'when set, the start date must match request date.'
                    ];
                }
            }
            // #memo - request_date must always be set (it is used for filtering)
            if(!$fundRequest['request_date']) {
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

    protected static function policyIsBalanced($self): array {
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

    /**
     * Make sure that the request date is updated when the date_from is changed.
     * This is needed to ensure that the request date is always set (required for further filtering).
     *
     */
    protected static function onupdateDateFrom($self) {
        $self->read(['has_date_range', 'date_from']);
        foreach($self as $id => $fundRequest) {
            if($fundRequest['has_date_range'] && $fundRequest['date_from']) {
                self::id($id)->update(['request_date' => $fundRequest['date_from']]);
            }
        }
    }

    private static function amountToCents($amount): int {
        return (int) round(((float) $amount) * 100);
    }

    private static function centsToAmount($amount_cents): float {
        return round(((int) $amount_cents) / 100, 2);
    }

    private static function resolveActiveOwnershipId(array $property_lot_ownerships, int $execution_date, int $property_lot_id): int {
        $active_ownership_id = null;

        foreach($property_lot_ownerships as $propertyLotOwnership) {
            if($propertyLotOwnership['date_from'] > $execution_date) {
                continue;
            }
            if($propertyLotOwnership['date_to'] && $propertyLotOwnership['date_to'] < $execution_date) {
                continue;
            }

            if($active_ownership_id !== null) {
                throw new \Exception("multiple_active_ownerships_for_property_lot_{$property_lot_id}", EQ_ERROR_INVALID_PARAM);
            }

            $active_ownership_id = $propertyLotOwnership['ownership_id'];
        }

        if($active_ownership_id === null) {
            throw new \Exception("missing_active_ownership_for_property_lot_{$property_lot_id}", EQ_ERROR_INVALID_PARAM);
        }

        return $active_ownership_id;
    }

    private static function resolveProratedOwnershipAmounts(array $property_lot_ownerships, int $period_from, int $period_to, int $amount_cents, int $property_lot_id): array {
        $allocations = [];
        $covered_days = 0;

        foreach($property_lot_ownerships as $propertyLotOwnership) {
            if($propertyLotOwnership['date_from'] > $period_to) {
                continue;
            }
            if($propertyLotOwnership['date_to'] && $propertyLotOwnership['date_to'] < $period_from) {
                continue;
            }

            $overlap_from = max($period_from, $propertyLotOwnership['date_from']);
            $overlap_to = $propertyLotOwnership['date_to'] ? min($period_to, $propertyLotOwnership['date_to']) : $period_to;

            if($overlap_from > $overlap_to) {
                continue;
            }

            $days = (int) ((($overlap_to - $overlap_from) / 86400) + 1);
            $covered_days += $days;

            $allocations[] = [
                'ownership_id' => $propertyLotOwnership['ownership_id'],
                'days' => $days,
                'amount_cents' => 0
            ];
        }

        $total_days = (int) ((($period_to - $period_from) / 86400) + 1);

        if(!count($allocations) || $covered_days !== $total_days) {
            throw new \Exception("invalid_ownership_coverage_for_property_lot_{$property_lot_id}", EQ_ERROR_INVALID_PARAM);
        }

        $remaining_cents = $amount_cents;
        foreach($allocations as $index => $allocation) {
            if($index === count($allocations) - 1) {
                $allocations[$index]['amount_cents'] = $remaining_cents;
                continue;
            }

            $allocated_cents = intdiv($amount_cents * $allocation['days'], $total_days);
            $allocations[$index]['amount_cents'] = $allocated_cents;
            $remaining_cents -= $allocated_cents;
        }

        $result = [];
        foreach($allocations as $allocation) {
            if($allocation['amount_cents'] === 0) {
                continue;
            }
            if(!isset($result[$allocation['ownership_id']])) {
                $result[$allocation['ownership_id']] = 0;
            }
            $result[$allocation['ownership_id']] += $allocation['amount_cents'];
        }

        return $result;
    }

    public static function doGenerateAllocation($self) {
        $self->read([
                'condo_id',
                'date_from',
                'date_to',
                'request_lines_ids' => ['request_amount', 'apportionment_id' => ['id', 'total_shares']]
            ]);

        foreach($self as $id => $fundRequest) {
            if(empty($fundRequest['request_lines_ids' ])) {
                continue;
            }

            // remove any previously created entry & entry lot
            FundRequestLineEntry::search(['fund_request_id', '=', $id])->delete(true);

            $apportionments_ids = [];
            foreach($fundRequest['request_lines_ids'] as $requestLine) {
                $apportionments_ids[$requestLine['apportionment_id']['id']] = true;
            }

            $map_apportionment_shares = [];

            if(count($apportionments_ids)) {
                $apportionmentShares = PropertyLotApportionmentShare::search([
                        ['apportionment_id', 'in', array_keys($apportionments_ids)]
                    ])
                    ->read(['apportionment_id', 'property_lot_id', 'property_lot_shares']);

                foreach($apportionmentShares as $apportionmentShare) {
                    $map_apportionment_shares[$apportionmentShare['apportionment_id']][] = $apportionmentShare;
                }
            }

            // retrieve ownerships currently assigned to property lots
            // we can only have a single one for each property lot : the ownership at the time of the FundRequest
            $propertyLotOwnerships = PropertyLotOwnership::search([
                    [['condo_id', '=', $fundRequest['condo_id']], ['date_from', '<=', $fundRequest['date_from']], ['date_to', '=', null]],
                    [['condo_id', '=', $fundRequest['condo_id']], ['date_to', '>=', $fundRequest['date_from']]]
                ])
                ->read([
                    'property_lot_id',
                    'ownership_id'
                ]);

            $map_property_lot_ownership = [];
            foreach($propertyLotOwnerships as $propertyLotOwnership) {
                $map_property_lot_ownership[$propertyLotOwnership['property_lot_id']] = $propertyLotOwnership['ownership_id'];
            }

            foreach($fundRequest['request_lines_ids'] as $request_line_id => $requestLine) {
                $map_property_lot_shares = [];
                $allocated_cents = 0;
                $apportionment_id = $requestLine['apportionment_id']['id'];
                $total_shares = (int) $requestLine['apportionment_id']['total_shares'];

                if($total_shares <= 0) {
                    throw new \Exception("missing_total_shares_for_apportionment_{$apportionment_id}", EQ_ERROR_INVALID_PARAM);
                }

                $map_ownership_line_entry = [];

                foreach($map_apportionment_shares[$apportionment_id] ?? [] as $apportionmentShare) {
                    $property_lot_id = $apportionmentShare['property_lot_id'];
                    $ownership_id = $map_property_lot_ownership[$property_lot_id] ?? null;

                    if(!isset($map_ownership_line_entry[$ownership_id])) {
                        $map_ownership_line_entry[$ownership_id] = FundRequestLineEntry::create([
                                'condo_id'          => $fundRequest['condo_id'],
                                'fund_request_id'   => $id,
                                'request_line_id'   => $request_line_id,
                                'ownership_id'      => $ownership_id
                            ])
                            ->first();
                    }

                    $lineEntry = $map_ownership_line_entry[$ownership_id];

                    $amount = $requestLine['request_amount'] * ($apportionmentShare['property_lot_shares'] / $total_shares);
                    $rounded_amount = round($amount, 2);
                    $allocated_cents += self::amountToCents($rounded_amount);

                    $entryLot = FundRequestLineEntryLot::create([
                            'condo_id'              => $fundRequest['condo_id'],
                            'fund_request_id'       => $id,
                            'request_line_id'       => $request_line_id,
                            'line_entry_id'         => $lineEntry['id'],
                            'property_lot_id'       => $property_lot_id,
                            'apportionment_shares'  => $apportionmentShare['property_lot_shares'],
                            'allocated_amount'      => $rounded_amount
                        ])
                        ->first();

                    $map_property_lot_shares[$apportionmentShare['property_lot_shares']][] = $entryLot['id'];
                }

                $delta_cents = self::amountToCents($requestLine['request_amount']) - $allocated_cents;

                if($delta_cents != 0) {
                    trigger_error("APP::allocation generated a delta: {$delta_cents}", EQ_REPORT_DEBUG);

                    krsort($map_property_lot_shares);
                    $ordered_lots_ids = count($map_property_lot_shares) ? array_merge(...array_values($map_property_lot_shares)) : [];

                    if(!count($ordered_lots_ids)) {
                        throw new \Exception("missing_property_lots_for_request_line_{$request_line_id}", EQ_ERROR_INVALID_PARAM);
                    }

                    $step = ($delta_cents > 0) ? 1 : -1;
                    $remaining = abs($delta_cents);
                    $index = 0;

                    while($remaining > 0) {
                        $line_entry_lot_id = $ordered_lots_ids[$index % count($ordered_lots_ids)];
                        $entryLot = FundRequestLineEntryLot::id($line_entry_lot_id)->read(['allocated_amount'])->first();
                        FundRequestLineEntryLot::id($line_entry_lot_id)->update([
                                'allocated_amount' => round($entryLot['allocated_amount'] + self::centsToAmount($step), 2)
                            ]);
                        --$remaining;
                        ++$index;
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
                'has_ownership_proration',
                'request_date',
                'has_date_range',
                'date_range_frequency',
                'date_from',
                'date_to',
                'payment_terms_id',
                'line_entries_ids' => ['request_line_id', 'entry_lots_ids' => ['property_lot_id', 'allocated_amount']]
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

            $map_lot_amounts = [];
            $map_property_lot_line_entries = [];

            foreach($fundRequest['line_entries_ids'] as $line_entry_id => $lineEntry) {
                foreach($lineEntry['entry_lots_ids'] as $entryLot) {
                    if(!isset($map_lot_amounts[$entryLot['property_lot_id']])) {
                        $map_lot_amounts[$entryLot['property_lot_id']] = 0;
                    }
                    $map_lot_amounts[$entryLot['property_lot_id']] += self::amountToCents($entryLot['allocated_amount']);
                    $map_property_lot_line_entries[$entryLot['property_lot_id']][$line_entry_id] = true;
                }
            }

            // pass-1 - remove dates for which a called execution remains

            foreach($execution_dates as $index => $execution_date) {
                // search for existing execution at the date (there should be 0 or 1)
                $existing_executions_ids = FundRequestExecution::search([['status', '=', 'posted'], ['fund_request_id', '=', $id], ['posting_date', '=', $execution_date]])->ids();
                if(count($existing_executions_ids)) {
                    unset($execution_dates[$index]);
                    $executionLineEntries = FundRequestExecutionLineEntry::search([
                            ['request_execution_id', 'in', $existing_executions_ids]
                        ])
                        ->read(['property_lot_id', 'called_amount']);

                    foreach($executionLineEntries as $executionLineEntry) {
                        if(!isset($map_lot_amounts[$executionLineEntry['property_lot_id']])) {
                            $map_lot_amounts[$executionLineEntry['property_lot_id']] = 0;
                        }
                        $map_lot_amounts[$executionLineEntry['property_lot_id']] -= self::amountToCents($executionLineEntry['called_amount']);
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

            $property_lots_ids = array_keys($map_lot_amounts);
            $map_property_lot_ownerships = [];

            if(count($property_lots_ids)) {
                $propertyLotOwnerships = PropertyLotOwnership::search([
                        ['property_lot_id', 'in', $property_lots_ids]
                    ])
                    ->read(['property_lot_id', 'ownership_id', 'date_from', 'date_to']);

                foreach($propertyLotOwnerships as $propertyLotOwnership) {
                    $map_property_lot_ownerships[$propertyLotOwnership['property_lot_id']][] = $propertyLotOwnership;
                }
            }

            $map_lot_execution_amounts = [];
            $map_execution_period_to = [];

            foreach($map_lot_amounts as $property_lot_id => $allocated_amount) {
                if($allocated_amount === 0) {
                    continue;
                }

                $remaining_amount = $allocated_amount;
                $base_amount = intdiv($allocated_amount, $num_intervals);

                foreach($execution_dates as $index => $execution_date) {
                    $called_amount = ($index == $num_intervals - 1) ? $remaining_amount : $base_amount;
                    $map_lot_execution_amounts[$execution_date][$property_lot_id] = $called_amount;
                    if($fundRequest['has_date_range']) {
                        $next_execution_date = $execution_dates[$index + 1] ?? null;
                        $map_execution_period_to[$execution_date] = $next_execution_date ? strtotime('-1 day', $next_execution_date) : $fundRequest['date_to'];
                    }
                    else {
                        $map_execution_period_to[$execution_date] = $execution_date;
                    }
                    $remaining_amount -= $base_amount;
                }
            }

            $map_line_entry_execution_lines = [];

            foreach($execution_dates as $execution_date) {
                if(empty($map_lot_execution_amounts[$execution_date])) {
                    continue;
                }

                $map_ownership_amounts = [];
                $map_ownership_lot_amounts = [];
                $map_ownership_line_entries = [];

                foreach($map_lot_execution_amounts[$execution_date] ?? [] as $property_lot_id => $called_amount) {
                    if($called_amount === 0) {
                        continue;
                    }

                    if($fundRequest['has_date_range'] && $fundRequest['has_ownership_proration']) {
                        $ownership_amounts = self::resolveProratedOwnershipAmounts(
                            $map_property_lot_ownerships[$property_lot_id] ?? [],
                            $execution_date,
                            $map_execution_period_to[$execution_date],
                            $called_amount,
                            $property_lot_id
                        );
                    }
                    else {
                        $ownership_amounts = [
                            self::resolveActiveOwnershipId($map_property_lot_ownerships[$property_lot_id] ?? [], $execution_date, $property_lot_id) => $called_amount
                        ];
                    }

                    foreach($ownership_amounts as $ownership_id => $ownership_amount) {
                        if(!isset($map_ownership_amounts[$ownership_id])) {
                            $map_ownership_amounts[$ownership_id] = 0;
                        }
                        if(!isset($map_ownership_lot_amounts[$ownership_id][$property_lot_id])) {
                            $map_ownership_lot_amounts[$ownership_id][$property_lot_id] = 0;
                        }

                        $map_ownership_amounts[$ownership_id] += $ownership_amount;
                        $map_ownership_lot_amounts[$ownership_id][$property_lot_id] += $ownership_amount;

                        foreach($map_property_lot_line_entries[$property_lot_id] ?? [] as $line_entry_id => $value) {
                            $map_ownership_line_entries[$ownership_id][$line_entry_id] = true;
                        }
                    }
                }

                if(!count($map_ownership_amounts)) {
                    continue;
                }

                $execution_values['posting_date'] = $execution_date;
                $execution_values['date_from'] = $execution_date;
                $execution_values['date_to'] = $map_execution_period_to[$execution_date] ?? $execution_date;
                // #memo - by default, payment terms related to sale invoices are applied (due_date is set at invoice emission)
                // $execution_values['due_date'] = $execution_date;

                $requestExecution = FundRequestExecution::create($execution_values)->first();

                foreach($map_ownership_amounts as $ownership_id => $called_amount) {
                    $executionLine = FundRequestExecutionLine::create([
                            'condo_id'              => $fundRequest['condo_id'],
                            'fund_request_id'       => $id,
                            // #memo - request_execution_id is an alias of invoice_id
                            'invoice_id'            => $requestExecution['id'],
                            'ownership_id'          => $ownership_id,
                            'total'                 => self::centsToAmount($called_amount)
                        ])
                        ->first();

                    foreach(array_keys($map_ownership_line_entries[$ownership_id] ?? []) as $line_entry_id) {
                        $map_line_entry_execution_lines[$line_entry_id][$executionLine['id']] = true;
                    }

                    foreach($map_ownership_lot_amounts[$ownership_id] ?? [] as $property_lot_id => $lot_amount) {
                        FundRequestExecutionLineEntry::create([
                                'condo_id'                  => $fundRequest['condo_id'],
                                'fund_request_id'           => $id,
                                'request_execution_id'      => $requestExecution['id'],
                                'request_execution_line_id' => $executionLine['id'],
                                'ownership_id'              => $ownership_id,
                                'property_lot_id'           => $property_lot_id,
                                'called_amount'             => self::centsToAmount($lot_amount)
                            ]);
                    }
                }
            }

            foreach($map_line_entry_execution_lines as $line_entry_id => $execution_lines_ids) {
                FundRequestLineEntry::id($line_entry_id)->update(['execution_lines_ids' => array_keys($execution_lines_ids)]);
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
        // no : this is done in FundRequestExecution
    }

    /**
     * Make sure request_date is always present and consistent with date_from
     *
     */
    public static function oncreate($self, $values) {
        if(isset($values['has_date_range']) && $values['has_date_range'] && isset($values['date_from'])) {
            $self->update(['request_date' => $values['date_from']]);
        }
    }

    public static function onchange($view, $event, $values) {
        $result = [];

        switch($view) {
            case 'form.default':
                if(isset($event['request_date'])) {
                    $result['request_date'] = $event['request_date'];
                    $result['date_from'] = $event['request_date'];
                }
                elseif(isset($event['date_from'])) {
                    $result['request_date'] = $event['date_from'];
                }

                // all values below must be consistent with a given condominium
                if(!$values['condo_id']) {
                    return $result;
                }

                if(isset($result['request_date'])) {
                    $fiscalYear = null;
                    $fiscalPeriod = FiscalPeriod::search([['condo_id', '=', $values['condo_id']], ['date_from', '<=', $result['request_date']], ['date_to', '>=', $result['request_date']]])
                        ->read(['id', 'name', 'date_to', 'fiscal_year_id'])
                        ->first();

                    if($fiscalPeriod) {
                        $fiscalYear = FiscalYear::id($fiscalPeriod['fiscal_year_id'])->read(['id', 'name'])->first();
                    }
                    if($fiscalPeriod && $fiscalYear) {
                        $result['fiscal_period_id'] = ['id' => $fiscalPeriod['id'], 'name' => $fiscalPeriod['name']];
                        $result['fiscal_year_id'] = ['id' => $fiscalYear['id'], 'name' => $fiscalYear['name']];
                        $result['date_to'] = $fiscalPeriod['date_to'];
                    }
                }

                if(isset($event['request_type']) && $values['fiscal_year_id']) {
                    $result['request_account_id'] = null;
                    if($event['request_type'] == 'expense_provisions') {
                        $fiscalYear = FiscalYear::id($values['fiscal_year_id'])->read(['fiscal_period_frequency', 'date_from', 'date_to'])->first();
                        $result['has_date_range'] = true;
                        $result['date_range_frequency'] = ['A' => 12, 'S' => 6, 'T' => 4, 'Q' => 3][$fiscalYear['fiscal_period_frequency']];
                        $result['date_from'] = $fiscalYear['date_from'];
                        $result['date_to'] = $fiscalYear['date_to'];
                    }
                }

                if(isset($event['fiscal_period_id'])) {
                    $fiscalPeriod = FiscalPeriod::id($event['fiscal_period_id'])->read(['date_from', 'date_to'])->first();
                    $result['date_from'] = $fiscalPeriod['date_from'];
                    $result['date_to'] = $fiscalPeriod['date_to'];
                    $result['request_date'] = $fiscalPeriod['date_from'];
                }
                break;
            default:
                break;
        }

        return $result;
    }
}
