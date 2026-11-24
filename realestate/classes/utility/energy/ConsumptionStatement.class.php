<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

use DateTimeImmutable;
use DateTimeZone;
use finance\accounting\Account;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\accounting\MiscOperation;
use finance\accounting\MiscOperationLine;
use realestate\property\PropertyLotOwnership;

class ConsumptionStatement extends \equal\orm\Model {

    public static function getName() {
        return 'Consumption Statement';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'           => 'many2one',
                'description'    => 'The condominium the consumption statement relates to.',
                'foreign_object' => 'realestate\property\Condominium',
                'required'       => true,
                'readonly'       => true
            ],

            'fiscal_year_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'finance\accounting\FiscalYear',
                'description'    => 'Fiscal year the consumption statement relates to.',
                'required'       => true,
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'fiscal_period_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'finance\accounting\FiscalPeriod',
                'description'    => 'Period of the fiscal year the consumption statement relates to.',
                'help'           => 'Posting date is automatically assigned on the last day of the period.',
                'domain'         => [['condo_id', '<>', null], ['condo_id', '=', 'object.condo_id'], ['fiscal_year_id', '=', 'object.fiscal_year_id']]
            ],

            'date_from' => [
                'type'           => 'date',
                'description'    => 'First day included in the consumption statement period.',
                'required'       => true
            ],

            'date_to' => [
                'type'           => 'date',
                'description'    => 'Last day included in the consumption statement period.',
                'required'       => true,
                'onupdate'       => 'onupdateDateTo'
            ],

            'posting_date' => [
                'type'           => 'date',
                'description'    => 'Date on which the statement must be posted in accounting.'
            ],

            'document_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'documents\Document',
                'description'    => 'Original document of the consumption statement.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'visible'        => ['status', 'not in', ['draft', 'proforma']]
            ],

            'has_shared_document' => [
                'type'        => 'boolean',
                'description' => 'Indicates whether the consumption statement document is shared.',
                'default'     => false,
                'visible'     => ['document_id', '<>', null]
            ],

            // #memo - kept optional for compatibility with statements with meter-based workflow.
            'consumption_meter_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\utility\energy\ConsumptionMeter',
                'description'    => 'Legacy master meter associated with the consumption statement.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['meter_scope', '=', 'master']]
            ],

            'consumption_file_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\utility\energy\ConsumptionFile',
                'description'    => 'Consumption file the statement belongs to.',
                'domain'         => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null],
                    ['status', '=', 'ready']
                ],
                'required'       => true,
                'onupdate'       => 'onupdateConsumptionFileId'
            ],

            'fixed_share_heating' => [
                'type'        => 'float',
                'usage'       => 'amount/percent',
                'description' => 'Fixed share applied to heating costs; the variable share is the complement to 100%.'
            ],

            'has_deduct_advances' => [
                'type'        => 'boolean',
                'description' => 'Indicates whether advances are deducted without creating allocation lines.',
                'default'     => false
            ],

            'consumption_statement_calculation_lines_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\utility\energy\ConsumptionStatementCalculationLine',
                'foreign_field'  => 'consumption_statement_id',
                'description'    => 'Property lot and ownership calculation lines of the consumption statement.',
                'domain'         => ['condo_id', '=', 'object.condo_id']
            ],

            'consumption_statement_allocation_lines_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\utility\energy\ConsumptionStatementAllocationLine',
                'foreign_field'  => 'consumption_statement_id',
                'description'    => 'Accounting allocations of the consumption statement amount.',
                'domain'         => ['condo_id', '=', 'object.condo_id']
            ],

            'consumption_statement_submission_lines_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\utility\energy\ConsumptionStatementSubmissionLine',
                'foreign_field'  => 'consumption_statement_id',
                'description'    => 'Submitted amounts of the consumption statement.',
                'domain'         => ['condo_id', '=', 'object.condo_id']
            ],

            'allocation_provider_information' => [
                'type'        => 'string',
                'usage'       => 'text/plain',
                'description' => 'Submission-line summary for the allocation provider.'
            ],

            'statement_total' => [
                'type'           => 'float',
                'usage'          => 'amount/money:2',
                'description'    => 'Total amount shown on the consumption statement.',
                'help'           => 'Enter the statement total manually. It is used to verify the totals of the lines and allocations.',
                'visible'        => ['status', 'not in', ['draft', 'proforma']]
            ],

            'status' => [
                'type'           => 'string',
                'description'    => 'Current processing status of the consumption statement.',
                'selection'      => [
                    'draft',
                    'proforma',
                    'sent',
                    'to_encode',
                    'encoded',
                    'integrated'
                ],
                'default'        => 'draft'
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'The consumption statement is being prepared.',
                'icon'        => 'edit',
                'transitions' => [
                    'mark_proforma' => [
                        'description' => 'Mark the statement as proforma.',
                        'policies'    => ['submission_lines_have_apportionment'],
                        'status'      => 'proforma'
                    ]
                ]
            ],
            'proforma' => [
                'description' => 'The consumption statement allocations can still be adjusted.',
                'icon'        => 'description',
                'transitions' => [
                    'send' => [
                        'description' => 'Mark the statement as sent.',
                        'policies'    => ['can_generate_statement_lines'],
                        'onafter'     => 'onafterSend',
                        'status'      => 'sent'
                    ]
                ]
            ],
            'sent' => [
                'description' => 'The consumption statement has been sent.',
                'icon'        => 'send',
                'transitions' => [
                    'revert_send' => [
                        'description' => 'Return the consumption statement to proforma.',
                        'status'      => 'proforma'
                    ],
                    'mark_to_encode' => [
                        'description' => 'Open the statement lines for encoding.',
                        'status'      => 'to_encode'
                    ]
                ]
            ],
            'to_encode' => [
                'description' => 'The consumption statement lines must be encoded.',
                'icon'        => 'edit_note',
                'transitions' => [
                    'revert_mark_to_encode' => [
                        'description' => 'Return the consumption statement to sent.',
                        'status'      => 'sent'
                    ],
                    'encode' => [
                        'description' => 'Mark the consumption statement as encoded.',
                        'policies'    => [
                            'lines_are_balanced',
                            'allocations_can_be_balanced',
                            'calculation_line_page_ranges_are_valid'
                        ],
                        'onbefore'    => 'onbeforeEncode',
                        'onafter'     => 'onafterEncode',
                        'status'      => 'encoded'
                    ]
                ]
            ],
            'encoded' => [
                'description' => 'The consumption statement is ready for accounting integration.',
                'icon'        => 'check',
                'transitions' => [
                    'revert_encode' => [
                        'description' => 'Reopen the consumption statement lines for encoding.',
                        'status'      => 'to_encode'
                    ],
                    'integrate' => [
                        'description' => 'Integrate the consumption statement into accounting.',
                        'policies'    => ['can_integrate'],
                        'onbefore'    => 'onbeforeIntegrate',
                        'status'      => 'integrated'
                    ]
                ]
            ],
            'integrated' => [
                'description' => 'The consumption statement has been integrated into accounting.',
                'icon'        => 'done_all',
                'transitions' => [
                    'unlock' => [
                        'description' => 'Cancel the related miscellaneous operations and return the statement to proforma.',
                        'policies'    => ['can_unlock'],
                        'onbefore'    => 'onbeforeUnlock',
                        'status'      => 'encoded'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'generate_statement_lines' => [
                'description' => 'Generate missing statement lines from the property lots configured on the consumption file.',
                'policies'    => ['can_generate_statement_lines'],
                'function'    => 'doGenerateStatementLines'
            ],
            'sync_submission_lines' => [
                'description' => 'Add missing draft submission lines from the sections of the consumption file.',
                'policies'    => [],
                'function'    => 'doSyncSubmissionLines'
            ],
            'sync_allocation_provider_information' => [
                'description' => 'Synchronize the allocation-provider information with the statement submission lines.',
                'policies'    => ['is_editable'],
                'function'    => 'doSyncAllocationProviderInformation'
            ],
            'apply_rounding_allocation' => [
                'description' => 'Balance a statement allocation difference below two euros with the rounding account.',
                'policies'    => ['allocations_can_be_balanced'],
                'function'    => 'doApplyRoundingAllocation'
            ],
            'detach_file_allocations' => [
                'description' => 'Detach allocations generated from the previous consumption file.',
                'policies'    => [],
                'function'    => 'doDetachFileAllocations'
            ],
            'integrate' => [
                'description' => 'Integrate the consumption statement into accounting.',
                'policies'    => ['can_integrate'],
                'function'    => 'doIntegrate'
            ],
            'unlock' => [
                'description' => 'Cancel the related miscellaneous operations before returning the statement to proforma.',
                'policies'    => ['can_unlock'],
                'function'    => 'doUnlock'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'is_draft' => [
                'description' => 'Checks that the consumption statement is still in draft.',
                'function'    => 'policyIsDraft'
            ],
            'is_editable' => [
                'description' => 'Checks that the consumption statement is still editable.',
                'function'    => 'policyIsEditable'
            ],
            'submission_lines_have_apportionment' => [
                'description' => 'Checks that every submission line has an apportionment.',
                'function'    => 'policySubmissionLinesHaveApportionment'
            ],
            'can_generate_statement_lines' => [
                'description' => 'Checks that statement lines can be generated.',
                'function'    => 'policyCanGenerateStatementLines'
            ],
            'allocations_are_balanced' => [
                'description' => 'Checks that allocations match the statement total and have an apportionment.',
                'function'    => 'policyAllocationsAreBalanced'
            ],
            'allocations_can_be_balanced' => [
                'description' => 'Checks that allocations match the statement total or can be balanced with a rounding allocation.',
                'function'    => 'policyAllocationsCanBeBalanced'
            ],
            'lines_are_balanced' => [
                'description' => 'Checks that statement lines match the statement total.',
                'function'    => 'policyLinesAreBalanced'
            ],
            'calculation_line_page_ranges_are_valid' => [
                'description' => 'Checks that calculation-line page ranges are complete, valid and do not overlap when a source document is set.',
                'function'    => 'policyCalculationLinePageRangesAreValid'
            ],
            'can_integrate' => [
                'description' => 'Checks all accounting integration prerequisites.',
                'function'    => 'policyCanIntegrate'
            ],
            'can_unlock' => [
                'description' => 'Checks that the related miscellaneous operations can be cancelled.',
                'function'    => 'policyCanUnlock'
            ]
        ]);
    }

    public static function getOperationPolicies(): array {
        return [
            EQ_R_UPDATE => [
                '*'                               => true,
                'consumption_file_id'             => ['is_draft'],
                'allocation_provider_information' => ['is_editable']
            ]
        ];
    }

    protected static function onupdateDateTo($self) {
        $self->read(['date_to']);
        foreach($self as $id => $consumptionStatement) {
            self::id($id)->update(['posting_date' => $consumptionStatement['date_to']]);
        }
    }

    protected static function policyIsDraft($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $consumptionStatement) {
            if($consumptionStatement['status'] !== 'draft') {
                $result[$id]['statement_not_draft'] = 'The consumption file can only be changed while the statement is in draft.';
            }
        }
        return $result;
    }

    protected static function policyIsEditable($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $consumptionStatement) {
            if(!in_array($consumptionStatement['status'], ['draft', 'proforma'], true)) {
                $result[$id]['allocation_provider_information_locked'] = 'Allocation-provider information can only be edited while the statement is in draft or proforma.';
            }
        }
        return $result;
    }

    protected static function policySubmissionLinesHaveApportionment($self): array {
        $result = [];
        $self->read([
            'consumption_statement_submission_lines_ids' => ['apportionment_id']
        ]);

        foreach($self as $id => $consumptionStatement) {
            foreach($consumptionStatement['consumption_statement_submission_lines_ids'] as $submissionLine) {
                if(!$submissionLine['apportionment_id']) {
                    $result[$id]['missing_submission_line_apportionment'] = 'Every submission line must have an apportionment before the statement can be marked as proforma.';
                    break;
                }
            }
        }

        return $result;
    }

    protected static function policyCanGenerateStatementLines($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $consumptionStatement) {
            if(in_array($consumptionStatement['status'], ['encoded', 'integrated'])) {
                $result[$id]['invalid_status'] = 'Lines can only be generated while the statement is sent or in the to-encode status.';
            }
        }
        return $result;
    }

    protected static function policyAllocationsAreBalanced($self): array {
        $result = [];
        $self->read([
            'statement_total',
            'consumption_statement_allocation_lines_ids' => ['amount', 'apportionment_id']
        ]);

        foreach($self as $id => $consumptionStatement) {
            $allocations_total = 0.0;
            foreach($consumptionStatement['consumption_statement_allocation_lines_ids'] as $allocation) {
                $allocations_total += (float) $allocation['amount'];
                if(!$allocation['apportionment_id']) {
                    $result[$id]['missing_allocation_apportionment'] = 'Every allocation must have an apportionment before the statement can be encoded.';
                }
            }
            if(round($allocations_total, 2) != round((float) $consumptionStatement['statement_total'], 2)) {
                $result[$id]['allocations_total_mismatch'] = 'The total of the statement allocations must match the statement total.';
            }
        }

        return $result;
    }

    protected static function policyAllocationsCanBeBalanced($self): array {
        $result = [];
        $self->read([
            'condo_id',
            'statement_total',
            'consumption_statement_allocation_lines_ids' => ['amount', 'apportionment_id']
        ]);

        foreach($self as $id => $consumptionStatement) {
            $allocations_total = 0.0;
            foreach($consumptionStatement['consumption_statement_allocation_lines_ids'] as $allocation) {
                $allocations_total += (float) $allocation['amount'];
                if(!$allocation['apportionment_id']) {
                    $result[$id]['missing_allocation_apportionment'] = 'Every allocation must have an apportionment before the statement can be encoded.';
                }
            }

            $delta = round((float) $consumptionStatement['statement_total'] - $allocations_total, 2);
            if($delta == 0.0) {
                continue;
            }
            if(abs($delta) >= 2.0) {
                $result[$id]['allocations_total_mismatch'] = 'The total of the statement allocations must match the statement total.';
                continue;
            }

            $roundingAccount = Account::search([
                    ['condo_id', '=', $consumptionStatement['condo_id']],
                    ['operation_assignment', '=', 'rounding_adjustment']
                ])
                ->read(['apportionment_id'])
                ->first();
            if(!$roundingAccount) {
                $result[$id]['missing_rounding_account'] = 'A rounding account is required to balance the statement allocations.';
            }
            elseif(!$roundingAccount['apportionment_id']) {
                $result[$id]['missing_rounding_account_apportionment'] = 'The rounding account must have an apportionment.';
            }
        }

        return $result;
    }

    protected static function policyLinesAreBalanced($self): array {
        $result = [];
        $self->read([
            'statement_total',
            'consumption_statement_calculation_lines_ids' => ['amount']
        ]);

        foreach($self as $id => $consumptionStatement) {
            $lines_total = 0.0;
            foreach($consumptionStatement['consumption_statement_calculation_lines_ids'] as $line) {
                $lines_total += (float) $line['amount'];
            }
            $lines_total_cents = (int) round($lines_total * 100);
            $statement_total_cents = (int) round((float) $consumptionStatement['statement_total'] * 100);
            if($lines_total_cents !== $statement_total_cents) {
                $result[$id]['lines_total_mismatch'] = 'The total of the statement lines must match the statement total.';
            }
        }

        return $result;
    }

    protected static function policyCalculationLinePageRangesAreValid($self): array {
        $result = [];
        $self->read([
            'document_id',
            'has_shared_document',
            'consumption_statement_calculation_lines_ids' => ['page_from', 'page_to']
        ]);

        foreach($self as $id => $consumptionStatement) {
            if(!$consumptionStatement['document_id'] || $consumptionStatement['has_shared_document']) {
                continue;
            }

            $page_ranges = [];
            foreach($consumptionStatement['consumption_statement_calculation_lines_ids'] as $calculationLine) {
                if($calculationLine['page_from'] === null || $calculationLine['page_to'] === null) {
                    $result[$id]['missing_calculation_line_page_range'] = 'Every calculation line must have a page range before the statement can be encoded.';
                    continue;
                }

                $page_from = filter_var($calculationLine['page_from'], FILTER_VALIDATE_INT);
                $page_to = filter_var($calculationLine['page_to'], FILTER_VALIDATE_INT);
                if($page_from === false || $page_to === false || $page_from < 1 || $page_to < $page_from) {
                    $result[$id]['invalid_calculation_line_page_range'] = 'Every calculation-line page range must start on a positive page and end on or after its first page.';
                    continue;
                }

                $page_ranges[] = [
                    'page_from' => $page_from,
                    'page_to'   => $page_to
                ];
            }

            usort($page_ranges, function($range_a, $range_b) {
                return $range_a['page_from'] <=> $range_b['page_from'];
            });

            $last_page_to = null;
            foreach($page_ranges as $page_range) {
                if($last_page_to !== null && $page_range['page_from'] <= $last_page_to) {
                    $result[$id]['overlapping_calculation_line_page_ranges'] = 'A page cannot belong to more than one calculation line.';
                    break;
                }
                $last_page_to = $page_range['page_to'];
            }
        }

        return $result;
    }

    protected static function policyCanIntegrate($self): array {
        $result = self::mergePolicyResults(
            self::policyAllocationsAreBalanced($self),
            self::policyLinesAreBalanced($self)
        );

        $self->read([
            'status',
            'condo_id',
            'posting_date',
            'consumption_file_id' => ['accounting_account_id'],
            'consumption_statement_calculation_lines_ids' => ['ownership_id', 'property_lot_id']
        ]);
        foreach($self as $id => $consumptionStatement) {
            if($consumptionStatement['status'] !== 'encoded') {
                $result[$id]['invalid_integration_status'] = 'Only encoded consumption statements can be integrated into accounting.';
            }
            if(!$consumptionStatement['posting_date']) {
                $result[$id]['missing_posting_date'] = 'A posting date is required for accounting integration.';
            }
            elseif($consumptionStatement['posting_date'] >= strtotime('tomorrow midnight')) {
                $result[$id]['invalid_posting_date'] = 'Posting date cannot be in the future.';
            }

            foreach($consumptionStatement['consumption_statement_calculation_lines_ids'] as $calculationLine) {
                if(!$calculationLine['ownership_id']) {
                    $result[$id]['missing_calculation_line_ownership'] = 'Every calculation line must have an ownership before integration.';
                }
                if(!$calculationLine['property_lot_id']) {
                    $result[$id]['missing_calculation_line_property_lot'] = 'Every calculation line must have a property lot before integration.';
                }
            }

            $journal = Journal::search([
                    ['condo_id', '=', $consumptionStatement['condo_id']],
                    ['journal_type', '=', 'MISC']
                ])
                ->first();
            if(!$journal) {
                $result[$id]['missing_misc_journal'] = 'A miscellaneous operations journal is required for integration.';
            }

            $consumptionFile = $consumptionStatement['consumption_file_id'];
            if(!$consumptionFile || !$consumptionFile['accounting_account_id']) {
                $result[$id]['missing_consumption_statement_account'] = 'The consumption file must define an accounting account for integration.';
            }
        }

        return $result;
    }

    protected static function policyCanUnlock($self): array {
        $result = [];
        $self->read(['status']);

        foreach($self as $id => $consumptionStatement) {
            if($consumptionStatement['status'] !== 'integrated') {
                $result[$id]['invalid_unlock_status'] = 'Only integrated consumption statements can be unlocked.';
                continue;
            }

            $miscOperations = MiscOperation::search([
                    ['consumption_statement_id', '=', $id],
                    ['status', '=', 'posted']
                ])
                ->read([
                    'fiscal_period_id' => ['status']
                ]);

            if($miscOperations->count() === 0) {
                $result[$id]['missing_posted_misc_operation'] = 'No posted miscellaneous operation was found for this consumption statement.';
                continue;
            }

            foreach($miscOperations as $miscOperation) {
                if(!$miscOperation['fiscal_period_id']) {
                    $result[$id]['missing_fiscal_period'] = 'The miscellaneous operation must have a fiscal period.';
                    break;
                }

                if($miscOperation['fiscal_period_id']['status'] === 'closed') {
                    $result[$id]['closed_fiscal_period'] = 'A consumption statement cannot be unlocked when its accounting period is closed.';
                    break;
                }
            }
        }

        return $result;
    }

    protected static function doGenerateStatementLines($self) {
        $self->read([
            'condo_id',
            'consumption_file_id',
            'date_from',
            'date_to',
            'has_deduct_advances',
            'consumption_statement_submission_lines_ids' => [
                'consumption_file_section_id',
                'account_id',
                'apportionment_id',
                'amount'
            ],
            'consumption_statement_allocation_lines_ids' => [
                'consumption_file_section_id',
                'account_id',
                'apportionment_id',
                'amount'
            ]
        ]);
        foreach($self as $id => $consumptionStatement) {
            if(!$consumptionStatement['has_deduct_advances']) {
                $allocations_by_section = [];
                $unlinked_allocations_by_values = [];
                foreach($consumptionStatement['consumption_statement_allocation_lines_ids'] as $allocation_id => $allocation) {
                    if($allocation['consumption_file_section_id']) {
                        $allocations_by_section[$allocation['consumption_file_section_id']] = $allocation_id;
                        continue;
                    }

                    $allocation_key = implode(':', [
                        $allocation['account_id'],
                        $allocation['apportionment_id'],
                        number_format((float) $allocation['amount'], 2, '.', '')
                    ]);
                    $unlinked_allocations_by_values[$allocation_key] = ($unlinked_allocations_by_values[$allocation_key] ?? 0) + 1;
                }

                foreach($consumptionStatement['consumption_statement_submission_lines_ids'] as $submissionLine) {
                    $values = [
                        'condo_id'                 => $consumptionStatement['condo_id'],
                        'consumption_statement_id' => $id,
                        'account_id'               => $submissionLine['account_id'],
                        'apportionment_id'         => $submissionLine['apportionment_id'],
                        'amount'                   => $submissionLine['amount']
                    ];

                    $section_id = $submissionLine['consumption_file_section_id'];
                    if($section_id) {
                        if(isset($allocations_by_section[$section_id])) {
                            ConsumptionStatementAllocationLine::id($allocations_by_section[$section_id])->update($values);
                            continue;
                        }

                        ConsumptionStatementAllocationLine::create(array_merge($values, [
                            'consumption_file_section_id' => $section_id
                        ]));
                        continue;
                    }

                    $allocation_key = implode(':', [
                        $submissionLine['account_id'],
                        $submissionLine['apportionment_id'],
                        number_format((float) $submissionLine['amount'], 2, '.', '')
                    ]);
                    if(($unlinked_allocations_by_values[$allocation_key] ?? 0) > 0) {
                        --$unlinked_allocations_by_values[$allocation_key];
                        continue;
                    }

                    ConsumptionStatementAllocationLine::create($values);
                }
            }

            $fileLots = ConsumptionFileLot::search([
                    ['consumption_file_id', '=', $consumptionStatement['consumption_file_id']]
                ])
                ->read(['property_lot_id', 'property_lot_extref']);

            foreach($fileLots as $fileLot) {
                $property_lot_id = $fileLot['property_lot_id'];
                $propertyLotOwnerships = PropertyLotOwnership::search([
                        ['condo_id', '=', $consumptionStatement['condo_id']],
                        ['property_lot_id', '=', $property_lot_id],
                        ['date_from', '<=', $consumptionStatement['date_to']]
                    ])
                    ->read(['date_from', 'date_to', 'ownership_id']);

                foreach($propertyLotOwnerships as $propertyLotOwnership) {
                    if(
                        $propertyLotOwnership['date_to']
                        && $propertyLotOwnership['date_to'] < $consumptionStatement['date_from']
                    ) {
                        continue;
                    }

                    $date_from = max($propertyLotOwnership['date_from'], $consumptionStatement['date_from']);
                    $date_to = $propertyLotOwnership['date_to']
                        ? min($propertyLotOwnership['date_to'], $consumptionStatement['date_to'])
                        : $consumptionStatement['date_to'];

                    if(ConsumptionStatementCalculationLine::search([
                            ['consumption_statement_id', '=', $id],
                            ['property_lot_id', '=', $property_lot_id],
                            ['ownership_id', '=', $propertyLotOwnership['ownership_id']],
                            ['date_from', '=', $date_from],
                            ['date_to', '=', $date_to]
                        ])->count() > 0) {
                        continue;
                    }

                    ConsumptionStatementCalculationLine::create([
                        'condo_id'                 => $consumptionStatement['condo_id'],
                        'consumption_statement_id' => $id,
                        'property_lot_id'          => $property_lot_id,
                        'property_lot_extref'      => $fileLot['property_lot_extref'],
                        'ownership_id'             => $propertyLotOwnership['ownership_id'],
                        'date_from'                => $date_from,
                        'date_to'                  => $date_to,
                        'amount'                   => 0.0
                    ]);
                }
            }
        }
    }

    protected static function doSyncSubmissionLines($self) {
        $self->read([
            'status',
            'condo_id',
            'consumption_file_id' => [
                'consumption_file_sections_ids' => [
                    'condo_id',
                    'units',
                    'accounting_account_id',
                    'apportionment_id'
                ]
            ],
            'consumption_statement_submission_lines_ids' => ['consumption_file_section_id']
        ]);

        foreach($self as $statement_id => $consumptionStatement) {
            if($consumptionStatement['status'] !== 'draft' || !$consumptionStatement['consumption_file_id']) {
                continue;
            }

            $submission_lines_by_section = [];
            foreach($consumptionStatement['consumption_statement_submission_lines_ids'] as $submission_line_id => $submissionLine) {
                if($submissionLine['consumption_file_section_id']) {
                    $submission_lines_by_section[$submissionLine['consumption_file_section_id']] = $submission_line_id;
                }
            }

            foreach($consumptionStatement['consumption_file_id']['consumption_file_sections_ids'] as $section_id => $section) {
                $values = [
                    'condo_id'         => $section['condo_id'],
                    'account_id'       => $section['accounting_account_id'],
                    'apportionment_id' => $section['apportionment_id'],
                    'units'            => $section['units']
                ];

                if(isset($submission_lines_by_section[$section_id])) {
                    ConsumptionStatementSubmissionLine::id($submission_lines_by_section[$section_id])->update($values);
                    continue;
                }

                ConsumptionStatementSubmissionLine::create(array_merge($values, [
                    'consumption_statement_id'    => $statement_id,
                    'consumption_file_section_id' => $section_id,
                    'amount'                      => 0.0
                ]));
            }
        }
    }

    protected static function doSyncAllocationProviderInformation($self) {
        $self->read([
            'consumption_statement_submission_lines_ids' => [
                'description',
                'account_id' => ['name', 'description'],
                'calculation_method',
                'amount',
                'quantity',
                'units',
                'unit_price'
            ]
        ]);

        $format_number = static function(float $value, int $precision): string {
            $formatted_value = number_format($value, $precision, ',', '.');
            if($precision > 0) {
                $formatted_value = rtrim(rtrim($formatted_value, '0'), ',');
            }
            return $formatted_value;
        };

        foreach($self as $id => $consumptionStatement) {
            $lines = [];
            foreach($consumptionStatement['consumption_statement_submission_lines_ids'] as $submissionLine) {
                $account = $submissionLine['account_id'];
                $label = $account
                    ? trim((string) ($account['description'] ?: $account['name']))
                    : '';
                if($label === '') {
                    continue;
                }

                $details = '';
                if(
                    $submissionLine['calculation_method'] === 'unit_price'
                    && (float) $submissionLine['unit_price'] !== 0.0
                ) {
                    $unit_price = $format_number((float) $submissionLine['unit_price'], 5) . ' €';
                    if($submissionLine['units']) {
                        $unit_price .= '/' . $submissionLine['units'];
                    }
                    $details = $unit_price;
                }
                elseif((float) $submissionLine['amount'] !== 0.0) {
                    $details = $format_number((float) $submissionLine['amount'], 2) . ' €';
                }

                if((float) $submissionLine['quantity'] !== 0.0) {
                    $quantity = $format_number((float) $submissionLine['quantity'], 2);
                    if($submissionLine['units']) {
                        $quantity .= ' ' . $submissionLine['units'];
                    }
                    $details .= ($details === '' ? '' : ' ') . '(' . $quantity . ')';
                }

                $description = trim((string) $submissionLine['description']);
                if($description !== '') {
                    $details .= ($details === '' ? '' : ' - ') . $description;
                }

                $lines[] = $label . ($details === '' ? '' : ' : ' . $details);
            }
            self::id($id)->update([
                'allocation_provider_information' => implode("\n", $lines)
            ]);
        }
    }

    protected static function doApplyRoundingAllocation($self) {
        $self->read([
            'status',
            'condo_id',
            'statement_total',
            'consumption_statement_allocation_lines_ids' => ['amount']
        ]);

        foreach($self as $statement_id => $consumptionStatement) {
            if($consumptionStatement['status'] !== 'to_encode') {
                continue;
            }

            $allocations_total = 0.0;
            foreach($consumptionStatement['consumption_statement_allocation_lines_ids'] as $allocation) {
                $allocations_total += (float) $allocation['amount'];
            }

            $delta = round((float) $consumptionStatement['statement_total'] - $allocations_total, 2);
            if($delta == 0.0 || abs($delta) >= 2.0) {
                continue;
            }

            $roundingAccount = Account::search([
                    ['condo_id', '=', $consumptionStatement['condo_id']],
                    ['operation_assignment', '=', 'rounding_adjustment']
                ])
                ->read(['apportionment_id'])
                ->first();
            if(!$roundingAccount) {
                throw new \Exception('missing_rounding_account', EQ_ERROR_INVALID_CONFIG);
            }
            if(!$roundingAccount['apportionment_id']) {
                throw new \Exception('missing_rounding_account_apportionment', EQ_ERROR_INVALID_CONFIG);
            }

            ConsumptionStatementAllocationLine::create([
                'condo_id'                 => $consumptionStatement['condo_id'],
                'consumption_statement_id' => $statement_id,
                'account_id'               => $roundingAccount['id'],
                'apportionment_id'         => $roundingAccount['apportionment_id'],
                'amount'                   => $delta
            ]);
        }
    }

    protected static function doDetachFileAllocations($self) {
        $self->read([
            'status',
            'consumption_statement_allocation_lines_ids' => ['consumption_file_section_id']
        ]);

        foreach($self as $consumptionStatement) {
            if($consumptionStatement['status'] !== 'draft') {
                continue;
            }
            $allocation_ids = [];
            foreach($consumptionStatement['consumption_statement_allocation_lines_ids'] as $allocation_id => $allocation) {
                if($allocation['consumption_file_section_id']) {
                    $allocation_ids[] = $allocation_id;
                }
            }
            if(count($allocation_ids)) {
                ConsumptionStatementAllocationLine::ids($allocation_ids)->update([
                    'consumption_file_section_id' => null
                ]);
            }
        }
    }

    protected static function doIntegrate($self) {
        $self->read([
            'condo_id',
            'date_from',
            'date_to',
            'posting_date',
            'consumption_file_id' => ['accounting_account_id'],
            'consumption_statement_calculation_lines_ids' => [
                'ownership_id',
                'property_lot_id',
                'amount'
            ],
            'consumption_statement_allocation_lines_ids' => [
                'account_id',
                'apportionment_id',
                'amount'
            ]
        ]);

        foreach($self as $id => $consumptionStatement) {
            $journal = Journal::search([
                    ['condo_id', '=', $consumptionStatement['condo_id']],
                    ['journal_type', '=', 'MISC']
                ])
                ->first();

            $account = Account::id($consumptionStatement['consumption_file_id']['accounting_account_id'])
                ->read(['tenant_share', 'owner_share'])
                ->first();

            $description = 'Consumption statement';
            $miscOperation = MiscOperation::create([
                    'condo_id'                  => $consumptionStatement['condo_id'],
                    'description'               => $description,
                    'journal_id'                => $journal['id'],
                    'posting_date'              => $consumptionStatement['posting_date'],
                    'consumption_statement_id'  => $id
                ])
                ->first();

            foreach($consumptionStatement['consumption_statement_calculation_lines_ids'] as $calculationLine) {
                $amount = round((float) $calculationLine['amount'], 2);
                MiscOperationLine::create([
                    'condo_id'                  => $consumptionStatement['condo_id'],
                    'misc_operation_id'         => $miscOperation['id'],
                    'account_id'                => $account['id'],
                    'is_private_consumption'    => true,
                    'ownership_id'              => $calculationLine['ownership_id'],
                    'property_lot_id'           => $calculationLine['property_lot_id'],
                    'description'               => $description,
                    'owner_share'               => $account['owner_share'],
                    'tenant_share'              => $account['tenant_share'],
                    'debit'                     => $amount > 0.0 ? abs($amount) : 0.0,
                    'credit'                    => $amount < 0.0 ? abs($amount) : 0.0
                ]);
            }

            foreach($consumptionStatement['consumption_statement_allocation_lines_ids'] as $allocationLine) {
                $amount = round((float) $allocationLine['amount'], 2);

                $allocationAccount = Account::id($allocationLine['account_id'])
                    ->read(['tenant_share', 'owner_share'])
                    ->first();

                MiscOperationLine::create([
                    'condo_id'          => $consumptionStatement['condo_id'],
                    'misc_operation_id' => $miscOperation['id'],
                    'account_id'        => $allocationLine['account_id'],
                    'apportionment_id'  => $allocationLine['apportionment_id'],
                    'owner_share'       => $allocationAccount['owner_share'],
                    'tenant_share'      => $allocationAccount['tenant_share'],
                    'description'       => $description,
                    'debit'             => $amount < 0.0 ? abs($amount) : 0.0,
                    'credit'            => $amount > 0.0 ? abs($amount) : 0.0
                ]);
            }

            MiscOperation::id($miscOperation['id'])
                ->transition('publish')
                ->transition('post');
        }
    }

    protected static function doUnlock($self) {
        foreach($self as $id => $consumptionStatement) {
            MiscOperation::search([
                    ['consumption_statement_id', '=', $id],
                    ['status', '=', 'posted']
                ])
                ->do('cancel');
        }
    }

    protected static function onafterSend($self) {
        $self->do('generate_statement_lines');
    }

    protected static function onbeforeEncode($self) {
        $self->do('apply_rounding_allocation');
    }

    protected static function onafterEncode($self, $cron) {
        $self->read(['document_id']);

        foreach($self as $id => $consumptionStatement) {
            if(!$consumptionStatement['document_id']) {
                continue;
            }

            $task_name = "realestate_utility_energy_ConsumptionStatement_split-document.{$id}";
            $cron->cancel($task_name);
            $cron->schedule(
                $task_name,
                time() + 1,
                'realestate_utility_energy_ConsumptionStatement_split-document',
                ['id' => $id]
            );
        }
    }

    protected static function onbeforeIntegrate($self) {
        $self->do('integrate');
    }

    protected static function onbeforeUnlock($self) {
        $self->do('unlock');
    }

    protected static function oninstantiate($self) {
        $self->do('sync_submission_lines');
    }

    protected static function onbeforeupdate($self, $values) {
        if(!array_key_exists('consumption_file_id', $values)) {
            return;
        }

        $new_consumption_file_id = $values['consumption_file_id'];
        $self->read(['status', 'consumption_file_id']);
        foreach($self as $id => $consumptionStatement) {
            if(
                $consumptionStatement['status'] === 'draft'
                && $consumptionStatement['consumption_file_id'] !== $new_consumption_file_id
            ) {
                self::id($id)->do('detach_file_allocations');
            }
        }
    }

    protected static function onupdateConsumptionFileId($self) {
        $self->do('sync_submission_lines');
    }

    public static function onchange($event, $values): array {
        $result = [];

        if(isset($event['condo_id']) && $event['condo_id']) {
            $condo_id = $event['condo_id'];
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $condo_id],
                    ['status', '=', 'open']
                ])
                ->read(['id', 'name'])
                ->first();
            if($fiscalYear) {
                $result['fiscal_year_id'] = [
                    'id'   => $fiscalYear['id'],
                    'name' => $fiscalYear['name']
                ];
                $values['fiscal_year_id'] = $fiscalYear['id'];
            }
        }

        $consumption_file_id = null;
        if(array_key_exists('consumption_file_id', $event)) {
            $consumption_file_id = $event['consumption_file_id'];
        }
        elseif(!empty($values['consumption_file_id'])) {
            $consumption_file_id = $values['consumption_file_id'];
        }

        $should_propose_date_from = array_key_exists('consumption_file_id', $event)
            || array_key_exists('fiscal_year_id', $event)
            || array_key_exists('condo_id', $event);

        if($should_propose_date_from && $consumption_file_id) {
            $previousStatement = self::search(
                    [
                        ['consumption_file_id', '=', $consumption_file_id],
                        ['status', 'in', ['to_encode', 'encoded', 'integrated']]
                    ],
                    ['sort' => ['date_to' => 'desc']]
                )
                ->read(['date_to'])
                ->first();
            if($previousStatement && $previousStatement['date_to']) {
                $result['date_from'] = self::nextCalendarDay($previousStatement['date_to']);
            }
        }

        $fiscal_year_id = null;
        if(array_key_exists('fiscal_year_id', $event)) {
            $fiscal_year_id = $event['fiscal_year_id'];
        }
        elseif(!empty($values['fiscal_year_id'])) {
            $fiscal_year_id = $values['fiscal_year_id'];
        }

        if($should_propose_date_from && !isset($result['date_from']) && $fiscal_year_id) {
            $fiscalYear = FiscalYear::id($fiscal_year_id)->read(['date_from'])->first();
            if($fiscalYear && $fiscalYear['date_from']) {
                $result['date_from'] = $fiscalYear['date_from'];
            }
        }

        $date_from = null;
        if(array_key_exists('date_from', $event) && $event['date_from']) {
            $date_from = $event['date_from'];
        }
        elseif(isset($result['date_from'])) {
            $date_from = $result['date_from'];
        }

        if($date_from) {
            $result['date_to'] = self::calendarYearEnd($date_from);
        }

        return $result;
    }

    private static function nextCalendarDay(int $timestamp): int {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify('+1 day')
            ->getTimestamp();
    }

    private static function calendarYearEnd(int $timestamp): int {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify('+1 year')
            ->modify('-1 day')
            ->getTimestamp();
    }

    private static function mergePolicyResults(array ...$results): array {
        $merged = [];
        foreach($results as $result) {
            foreach($result as $id => $errors) {
                $merged[$id] = array_merge($merged[$id] ?? [], $errors);
            }
        }
        return $merged;
    }
}
