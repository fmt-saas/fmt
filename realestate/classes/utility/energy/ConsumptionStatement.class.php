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
                'type'        => 'date',
                'description' => 'First day included in the consumption statement period.',
                'required'    => true
            ],

            'date_to' => [
                'type'        => 'date',
                'description' => 'Last day included in the consumption statement period.',
                'required'    => true
            ],

            'misc_operation_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'finance\accounting\MiscOperation',
                'description'    => 'Accounting operation generated when the statement is integrated.',
                'readonly'       => true
            ],

            'posting_date' => [
                'type'        => 'date',
                'description' => 'Date on which the statement must be posted in accounting.',
                'default'     => function () { return time(); }
            ],

            'document_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'documents\Document',
                'description'    => 'Original document of the consumption statement.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
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
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'required'       => true,
                'onupdate'       => 'onupdateConsumptionFileId'
            ],

            'consumption_statement_lines_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\utility\energy\ConsumptionStatementLine',
                'foreign_field'  => 'consumption_statement_id',
                'description'    => 'Property lot and ownership lines of the consumption statement.',
                'domain'         => ['condo_id', '=', 'object.condo_id']
            ],

            'consumption_statement_allocations_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\utility\energy\ConsumptionStatementAllocation',
                'foreign_field'  => 'consumption_statement_id',
                'description'    => 'Accounting allocations of the consumption statement amount.',
                'domain'         => ['condo_id', '=', 'object.condo_id']
            ],

            'statement_total' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Total amount shown on the consumption statement.',
                'help'        => 'Enter the statement total manually. It is used to verify the totals of the lines and allocations.'
            ],

            'status' => [
                'type'        => 'string',
                'description' => 'Current processing status of the consumption statement.',
                'selection'   => ['draft', 'proforma', 'sent', 'to_encode', 'encoded', 'integrated'],
                'default'     => 'draft'
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
                        'policies'    => ['allocations_are_balanced', 'can_generate_statement_lines'],
                        'onafter'     => 'onafterSend',
                        'status'      => 'sent'
                    ]
                ]
            ],
            'sent' => [
                'description' => 'The consumption statement has been sent.',
                'icon'        => 'send',
                'transitions' => [
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
                    'encode' => [
                        'description' => 'Mark the consumption statement as encoded.',
                        'policies'    => ['allocations_are_balanced', 'lines_are_balanced'],
                        'status'      => 'encoded'
                    ]
                ]
            ],
            'encoded' => [
                'description' => 'The consumption statement is ready for accounting integration.',
                'icon'        => 'check',
                'transitions' => [
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
                'transitions' => []
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
            'sync_allocations' => [
                'description' => 'Add missing draft allocations from the sections of the consumption file.',
                'policies'    => [],
                'function'    => 'doSyncAllocations'
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
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'statement_is_draft' => [
                'description' => 'Checks that the consumption statement is still in draft.',
                'function'    => 'policyStatementIsDraft'
            ],
            'can_generate_statement_lines' => [
                'description' => 'Checks that statement lines can be generated.',
                'function'    => 'policyCanGenerateStatementLines'
            ],
            'allocations_are_balanced' => [
                'description' => 'Checks that allocations match the statement total and have an apportionment.',
                'function'    => 'policyAllocationsAreBalanced'
            ],
            'lines_are_balanced' => [
                'description' => 'Checks that statement lines match the statement total.',
                'function'    => 'policyLinesAreBalanced'
            ],
            'can_integrate' => [
                'description' => 'Checks all accounting integration prerequisites.',
                'function'    => 'policyCanIntegrate'
            ]
        ]);
    }

    public static function getOperationPolicies(): array {
        return [
            EQ_R_UPDATE => [
                '*'                   => true,
                'consumption_file_id' => ['statement_is_draft']
            ]
        ];
    }

    protected static function policyStatementIsDraft($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $consumptionStatement) {
            if($consumptionStatement['status'] !== 'draft') {
                $result[$id]['statement_not_draft'] = 'The consumption file can only be changed while the statement is in draft.';
            }
        }
        return $result;
    }

    protected static function policyCanGenerateStatementLines($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $consumptionStatement) {
            if(!in_array($consumptionStatement['status'], ['sent', 'to_encode'])) {
                $result[$id]['invalid_status'] = 'Lines can only be generated while the statement is sent or in the to-encode status.';
            }
        }
        return $result;
    }

    protected static function policyAllocationsAreBalanced($self): array {
        $result = [];
        $self->read([
            'statement_total',
            'consumption_statement_allocations_ids' => ['amount', 'apportionment_id']
        ]);

        foreach($self as $id => $consumptionStatement) {
            $allocations_total = 0.0;
            foreach($consumptionStatement['consumption_statement_allocations_ids'] as $allocation) {
                $allocations_total += (float) $allocation['amount'];
                if(!$allocation['apportionment_id']) {
                    $result[$id]['missing_allocation_apportionment'] = 'Every allocation must have an apportionment before the statement can be sent.';
                }
            }
            if(round($allocations_total, 2) != round((float) $consumptionStatement['statement_total'], 2)) {
                $result[$id]['allocations_total_mismatch'] = 'The total of the statement allocations must match the statement total.';
            }
        }

        return $result;
    }

    protected static function policyLinesAreBalanced($self): array {
        $result = [];
        $self->read([
            'statement_total',
            'consumption_statement_lines_ids' => ['amount']
        ]);

        foreach($self as $id => $consumptionStatement) {
            $lines_total = 0.0;
            foreach($consumptionStatement['consumption_statement_lines_ids'] as $line) {
                $lines_total += (float) $line['amount'];
            }
            if(round($lines_total, 2) != round((float) $consumptionStatement['statement_total'], 2)) {
                $result[$id]['lines_total_mismatch'] = 'The total of the statement lines must match the statement total.';
            }
        }

        return $result;
    }

    protected static function policyCanIntegrate($self): array {
        $result = self::mergePolicyResults(
            self::policyAllocationsAreBalanced($self),
            self::policyLinesAreBalanced($self)
        );

        $self->read(['condo_id', 'misc_operation_id']);
        foreach($self as $id => $consumptionStatement) {
            if($consumptionStatement['misc_operation_id']) {
                $result[$id]['accounting_operation_already_exists'] = 'An accounting operation already exists for this statement.';
            }

            $journal = Journal::search([
                    ['condo_id', '=', $consumptionStatement['condo_id']],
                    ['journal_type', '=', 'MISC']
                ])
                ->first();
            if(!$journal) {
                $result[$id]['missing_misc_journal'] = 'A miscellaneous operations journal is required for integration.';
            }

            $privateExpenseAccount = Account::search([
                    ['condo_id', '=', $consumptionStatement['condo_id']],
                    ['operation_assignment', '=', 'private_expenses']
                ])
                ->first();
            if(!$privateExpenseAccount) {
                $result[$id]['missing_private_expense_account'] = 'A private expense account is required for integration.';
            }
        }

        return $result;
    }

    protected static function doGenerateStatementLines($self) {
        $self->read(['condo_id', 'consumption_file_id', 'date_from', 'date_to']);
        foreach($self as $id => $consumptionStatement) {
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

                    $existing = ConsumptionStatementLine::search([
                        ['consumption_statement_id', '=', $id],
                        ['property_lot_id', '=', $property_lot_id],
                        ['ownership_id', '=', $propertyLotOwnership['ownership_id']],
                        ['date_from', '=', $date_from],
                        ['date_to', '=', $date_to]
                    ]);
                    if($existing->count() > 0) {
                        continue;
                    }

                    ConsumptionStatementLine::create([
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

    protected static function doSyncAllocations($self) {
        $self->read([
            'status',
            'condo_id',
            'consumption_file_id' => [
                'consumption_file_sections_ids' => [
                    'condo_id',
                    'accounting_account_id' => ['apportionment_id']
                ]
            ],
            'consumption_statement_allocations_ids' => ['consumption_file_section_id']
        ]);

        foreach($self as $statement_id => $consumptionStatement) {
            if($consumptionStatement['status'] !== 'draft' || !$consumptionStatement['consumption_file_id']) {
                continue;
            }

            $allocations_by_section = [];
            foreach($consumptionStatement['consumption_statement_allocations_ids'] as $allocation_id => $allocation) {
                if($allocation['consumption_file_section_id']) {
                    $allocations_by_section[$allocation['consumption_file_section_id']] = $allocation_id;
                }
            }

            foreach($consumptionStatement['consumption_file_id']['consumption_file_sections_ids'] as $section_id => $section) {
                $account = $section['accounting_account_id'];
                if(!$account) {
                    continue;
                }
                $values = [
                    'condo_id'         => $section['condo_id'],
                    'account_id'       => $account['id'],
                    'apportionment_id' => $account['apportionment_id']
                ];

                if(isset($allocations_by_section[$section_id])) {
                    ConsumptionStatementAllocation::id($allocations_by_section[$section_id])->update($values);
                    continue;
                }

                ConsumptionStatementAllocation::create(array_merge($values, [
                    'consumption_statement_id'    => $statement_id,
                    'consumption_file_section_id' => $section_id,
                    'amount'                      => 0.0
                ]));
            }
        }
    }

    protected static function doDetachFileAllocations($self) {
        $self->read([
            'status',
            'consumption_statement_allocations_ids' => ['consumption_file_section_id']
        ]);

        foreach($self as $consumptionStatement) {
            if($consumptionStatement['status'] !== 'draft') {
                continue;
            }
            $allocation_ids = [];
            foreach($consumptionStatement['consumption_statement_allocations_ids'] as $allocation_id => $allocation) {
                if($allocation['consumption_file_section_id']) {
                    $allocation_ids[] = $allocation_id;
                }
            }
            if(count($allocation_ids)) {
                ConsumptionStatementAllocation::ids($allocation_ids)->update([
                    'consumption_file_section_id' => null
                ]);
            }
        }
    }

    protected static function doIntegrate($self) {
        // Accounting integration is intentionally left for a later implementation.
    }

    protected static function onafterSend($self) {
        $self->do('generate_statement_lines');
    }

    protected static function onbeforeIntegrate($self) {
        $self->do('integrate');
    }

    protected static function oninstantitate($self) {
        $self->do('sync_allocations');
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
        $self->do('sync_allocations');
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
