<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property\transfer;

use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;
use finance\accounting\Account;
use finance\accounting\AccountBalanceChange;
use finance\accounting\FiscalPeriod;
use finance\accounting\Journal;
use finance\accounting\MiscOperation;
use finance\accounting\MiscOperationLine;
use finance\bank\CondominiumBankAccount;
use realestate\finance\accounting\CondoFund;
use realestate\funding\ExpenseStatement;
use realestate\funding\ExpenseStatementOwnerLine;
use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestExecutionLineEntry;
use realestate\ownership\Ownership;
use realestate\ownership\OwnershipCommunicationPreference;
use realestate\property\PropertyLotApportionmentShare;

class OwnershipTransferSettlement extends \equal\orm\Model {

    public function getTable() {
        return 'realestate_property_transfer_settlement';
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'        => 'computed',
                'result_type' => 'string',
                'description' => 'Technical name of the ownership transfer settlement.',
                'function'    => 'calcName',
                'store'       => true,
                'readonly'    => true
            ],

            'ownership_transfer_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\OwnershipTransfer',
                'description'    => 'Ownership transfer regularized by the settlement.',
                'required'       => true,
                'readonly'       => true,
                'ondelete'       => 'cascade',
                'dependents'     => ['name']
            ],

            'condo_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\Condominium',
                'description'    => 'Condominium concerned by the settlement.',
                'required'       => true,
                'readonly'       => true
            ],

            'seller_ownership_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\ownership\Ownership',
                'description'    => 'Ownership selling the transferred property lots.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'required'       => true,
                'readonly'       => true
            ],

            'buyer_ownership_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\ownership\Ownership',
                'description'    => 'Ownership acquiring the transferred property lots.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'required'       => true,
                'readonly'       => true
            ],

            'transfer_date' => [
                'type'        => 'date',
                'description' => 'Economic date from which the buyer owns the property lots.',
                'required'    => true,
                'readonly'    => true
            ],

            'snapshot_at' => [
                'type'        => 'datetime',
                'description' => 'Date and time when accounted sources were frozen.',
                'required'    => true,
                'readonly'    => true
            ],

            'accounting_date' => [
                'type'        => 'date',
                'description' => 'Date used to post the settlement miscellaneous operations.',
                'required'    => true,
                'default'     => fn() => time()
            ],

            'validated_at' => [
                'type'        => 'datetime',
                'description' => 'Date and time at which the settlement was validated.',
                'readonly'    => true
            ],

            'closed_at' => [
                'type'        => 'datetime',
                'description' => 'Date and time at which the settlement was closed.',
                'readonly'    => true
            ],

            'seller_net_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Net amount credited to the seller; a negative value is debited.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'buyer_net_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Net amount debited to the buyer; a negative value is credited.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'has_accounted_sources' => [
                'type'        => 'boolean',
                'description' => 'Indicates whether relevant accounted sources were found.',
                'default'     => false,
                'readonly'    => true
            ],

            'alert_summary' => [
                'type'        => 'string',
                'usage'       => 'text/plain',
                'description' => 'Summary of the accounted sources requiring attention.',
                'readonly'    => true
            ],

            'lines_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementLine',
                'foreign_field'  => 'settlement_id',
                'description'    => 'Detailed economic movements calculated for the settlement.'
            ],

            'misc_operations_ids' => [
                'type'            => 'many2many',
                'foreign_object'  => 'finance\accounting\MiscOperation',
                'foreign_field'   => 'ownership_transfer_settlements_ids',
                'rel_table'       => 'realestate_property_transfer_settlement_operation',
                'rel_foreign_key' => 'misc_operation_id',
                'rel_local_key'   => 'settlement_id',
                'description'     => 'Miscellaneous operations generated by the settlement.',
                'domain'          => ['condo_id', '=', 'object.condo_id'],
                'readonly'        => true
            ],

            'correspondences_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementCorrespondence',
                'foreign_field'  => 'settlement_id',
                'description'    => 'Seller and buyer correspondences generated for the settlement.'
            ],

            'correspondences_dispatch_started_at' => [
                'type'        => 'datetime',
                'description' => 'Date and time at which correspondence delivery was scheduled.',
                'readonly'    => true
            ],

            'correspondences_exporting_task_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'documents\export\ExportingTask',
                'description'    => 'Export task containing settlement correspondences to print.',
                'readonly'       => true,
                'ondelete'       => 'null'
            ],

            'logs' => [
                'type'        => 'string',
                'usage'       => 'text/plain',
                'description' => 'Technical log of settlement processing.',
                'readonly'    => true
            ],

            'status' => [
                'type'        => 'string',
                'selection'   => ['pending', 'validated', 'closed'],
                'description' => 'Current lifecycle status of the settlement.',
                'default'     => 'pending',
                'required'    => true,
                'readonly'    => true
            ]
        ];
    }

    public function getUnique() {
        return [
            ['ownership_transfer_id']
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Pending settlement, waiting for accounting validation.',
                'icon'        => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Post the generated accounting operations and validate the settlement.',
                        'policies'    => ['can_validate'],
                        'onbefore'    => 'onbeforeValidate',
                        'onafter'     => 'onafterValidate',
                        'status'      => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Validated settlement, waiting to be closed.',
                'icon'        => 'done',
                'transitions' => [
                    'close' => [
                        'description' => 'Close the validated settlement.',
                        'policies'    => ['can_close'],
                        'onafter'     => 'onafterClose',
                        'status'      => 'closed'
                    ]
                ]
            ],
            'closed' => [
                'description' => 'Closed settlement, no further action can be taken.',
                'icon'        => 'lock',
                'transitions' => []
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'generate_lines' => [
                'description' => 'Generate settlement lines from working fund balances, posted fund requests, and posted expense statements.',
                'policies'    => ['can_generate_lines'],
                'function'    => 'doGenerateLines'
            ],
            'generate_operations' => [
                'description' => 'Generate one proforma miscellaneous operation per corrected accounting source.',
                'policies'    => ['can_generate_operations'],
                'function'    => 'doGenerateOperations'
            ],
            'generate_correspondences' => [
                'description' => 'Generate settlement correspondences for the seller and buyer ownerships.',
                'policies'    => [],
                'function'    => 'doGenerateCorrespondences'
            ],
            'dispatch_correspondences' => [
                'description' => 'Generate documents and dispatch settlement correspondences by email or postal export.',
                'policies'    => ['can_dispatch_correspondences'],
                'function'    => 'doDispatchCorrespondences'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_generate_lines' => [
                'description' => 'Checks that the settlement is complete and pending, and that working fund apportionments are configured.',
                'function'    => 'policyCanGenerateLines'
            ],
            'can_generate_operations' => [
                'description' => 'Checks that settlement lines can be prepared as proforma operations on the selected accounting date.',
                'function'    => 'policyCanGenerateOperations'
            ],
            'can_validate' => [
                'description' => 'Checks that every generated miscellaneous operation can be posted.',
                'function'    => 'policyCanValidate'
            ],
            'can_dispatch_correspondences' => [
                'description' => 'Checks that seller and buyer correspondences are ready for delivery.',
                'function'    => 'policyCanDispatchCorrespondences'
            ],
            'can_close' => [
                'description' => 'Checks that all settlement correspondences have been delivered.',
                'function'    => 'policyCanClose'
            ]
        ]);
    }

    protected static function policyCanGenerateLines($self): array {
        $result = [];

        $self->read([
            'status',
            'ownership_transfer_id' => ['property_lots_ids'],
            'condo_id',
            'seller_ownership_id',
            'buyer_ownership_id',
            'transfer_date',
            'snapshot_at',
            'misc_operations_ids' => ['status']
        ]);

        foreach($self as $id => $settlement) {
            if($settlement['status'] !== 'pending') {
                $result[$id] = ['settlement_not_pending' => 'Settlement lines can only be generated while the settlement is pending.'];
                continue;
            }

            foreach($settlement['misc_operations_ids'] as $miscOperation) {
                if($miscOperation['status'] !== 'proforma') {
                    $result[$id] = ['misc_operations_not_proforma' => 'Settlement lines can only be regenerated while every existing miscellaneous operation is proforma.'];
                    continue 2;
                }
            }

            if(!$settlement['ownership_transfer_id']) {
                $result[$id] = ['missing_ownership_transfer' => 'The ownership transfer is mandatory.'];
                continue;
            }

            $property_lots_ids = $settlement['ownership_transfer_id']['property_lots_ids'] ?? [];
            if(!count($property_lots_ids)) {
                $result[$id] = ['missing_property_lots' => 'The ownership transfer must contain at least one property lot.'];
                continue;
            }

            if(!$settlement['condo_id']) {
                $result[$id] = ['missing_condo' => 'The condominium is mandatory.'];
                continue;
            }

            if(!$settlement['seller_ownership_id']) {
                $result[$id] = ['missing_seller' => 'The seller ownership is mandatory.'];
                continue;
            }

            if(!$settlement['buyer_ownership_id']) {
                $result[$id] = ['missing_buyer' => 'The buyer ownership is mandatory.'];
                continue;
            }

            if((int) $settlement['seller_ownership_id'] === (int) $settlement['buyer_ownership_id']) {
                $result[$id] = ['identical_ownerships' => 'Seller and buyer ownerships must be different.'];
                continue;
            }

            if(!$settlement['transfer_date']) {
                $result[$id] = ['missing_transfer_date' => 'The ownership transfer date is mandatory.'];
                continue;
            }

            if(!$settlement['snapshot_at']) {
                $result[$id] = ['missing_snapshot' => 'The accounted source snapshot is mandatory.'];
                continue;
            }

            $transfer_date = (int) $settlement['transfer_date'];
            $snapshot_at = (int) $settlement['snapshot_at'];
            if($snapshot_at < $transfer_date) {
                $result[$id] = ['snapshot_before_transfer' => 'The accounted source snapshot cannot precede the ownership transfer date.'];
                continue;
            }

            $workingFunds = CondoFund::search([
                    ['condo_id', '=', $settlement['condo_id']],
                    ['fund_type', '=', 'working_fund']
                ])
                ->read(['fund_account_id', 'apportionment_id', 'total_shares']);

            if(!count($workingFunds)) {
                $result[$id] = ['missing_working_fund' => 'The condominium must have a working fund.'];
                continue;
            }

            foreach($workingFunds as $condo_fund_id => $workingFund) {
                if(!$workingFund['fund_account_id']) {
                    $result[$id] = ['missing_working_fund_account' => "Working fund {$condo_fund_id} has no accounting account."];
                    break;
                }

                if(!$workingFund['apportionment_id']) {
                    $result[$id] = ['missing_working_fund_apportionment' => "Working fund {$condo_fund_id} has no apportionment."];
                    break;
                }

                if(($workingFund['total_shares'] ?? 0) <= 0) {
                    $result[$id] = ['missing_working_fund_shares' => "Working fund {$condo_fund_id} has no total apportionment shares."];
                    break;
                }

                $shares_count = count(PropertyLotApportionmentShare::search([
                        ['apportionment_id', '=', $workingFund['apportionment_id']],
                        ['property_lot_id', 'in', $property_lots_ids]
                    ])
                    ->ids());

                if($shares_count !== count($property_lots_ids)) {
                    $result[$id] = ['missing_property_lot_share' => "Some transferred property lots have no share for working fund {$condo_fund_id}."];
                    break;
                }
            }
        }

        return $result;
    }

    protected static function policyCanGenerateOperations($self): array {
        $result = [];

        $self->read([
            'status',
            'condo_id',
            'seller_ownership_id',
            'buyer_ownership_id',
            'accounting_date',
            'lines_ids' => [
                'source_type',
                'condo_fund_id'             => ['name'],
                'fund_request_execution_id' => ['name'],
                'expense_statement_id'      => ['name'],
                'property_lot_id'            => ['name'],
                'applied_amount'
            ]
        ]);

        foreach($self as $id => $settlement) {
            if($settlement['status'] === 'closed') {
                $result[$id] = ['settlement_closed' => 'A closed settlement cannot generate accounting operations.'];
                continue;
            }

            if(!count($settlement['lines_ids'])) {
                $result[$id] = ['missing_settlement_lines' => 'Settlement lines must be generated before accounting operations.'];
                continue;
            }

            try {
                $groups = self::groupSettlementLines($id, $settlement['lines_ids']);
            }
            catch(\Exception $e) {
                $result[$id] = [$e->getMessage() => 'A settlement line has an invalid accounting source.'];
                continue;
            }

            if(!count($groups)) {
                $result[$id] = ['missing_applied_amounts' => 'At least one settlement line must have a non-zero retained amount.'];
                continue;
            }

            // Repeated generation can reuse operations that are already ready
            // for validation or posted.
            $groups_to_create = [];
            foreach($groups as $group) {
                $existingOperation = OwnershipTransferSettlementOperation::search([
                        ['settlement_id', '=', $id],
                        ['operation_key', '=', $group['operation_key']]
                    ])
                    ->read(['misc_operation_id' => ['status', 'misc_operation_lines_ids']])
                    ->first();

                if(
                    !$existingOperation
                    || !$existingOperation['misc_operation_id']
                    || !in_array($existingOperation['misc_operation_id']['status'], ['proforma', 'posted'], true)
                    || (
                        $existingOperation['misc_operation_id']['status'] === 'proforma'
                        && !count($existingOperation['misc_operation_id']['misc_operation_lines_ids'])
                    )
                ) {
                    $groups_to_create[] = $group;
                }
            }

            if(!count($groups_to_create)) {
                continue;
            }

            if(!$settlement['accounting_date']) {
                $result[$id] = ['missing_accounting_date' => 'The accounting date is mandatory.'];
                continue;
            }

            if((int) $settlement['accounting_date'] >= strtotime('tomorrow midnight')) {
                $result[$id] = ['future_accounting_date' => 'The accounting date cannot be in the future.'];
                continue;
            }

            $fiscalPeriod = FiscalPeriod::search([
                    ['condo_id', '=', $settlement['condo_id']],
                    ['date_from', '<=', $settlement['accounting_date']],
                    ['date_to', '>=', $settlement['accounting_date']]
                ])
                ->read(['status', 'fiscal_year_status'])
                ->first();

            if(!$fiscalPeriod) {
                $result[$id] = ['missing_accounting_period' => 'No fiscal period covers the accounting date.'];
                continue;
            }

            if(!in_array($fiscalPeriod['status'], ['open', 'preclosed'], true)) {
                $result[$id] = ['invalid_accounting_period' => 'The accounting period must be open or preclosed.'];
                continue;
            }

            if(!in_array($fiscalPeriod['fiscal_year_status'], ['preopen', 'open', 'preclosed'], true)) {
                $result[$id] = ['invalid_accounting_fiscal_year' => 'The fiscal year does not allow accounting operations.'];
                continue;
            }

            $journal = Journal::search([
                    ['condo_id', '=', $settlement['condo_id']],
                    ['journal_type', '=', 'MISC']
                ])
                ->first();

            if(!$journal) {
                $result[$id] = ['missing_misc_journal' => 'A miscellaneous-operation journal is required.'];
                continue;
            }

            $bankAccount = CondominiumBankAccount::search([
                    ['condo_id', '=', $settlement['condo_id']],
                    ['is_primary', '=', true]
                ])
                ->first();

            if(!$bankAccount) {
                $result[$id] = ['missing_primary_bank_account' => 'A primary condominium bank account is required to post owner movements.'];
                continue;
            }

            foreach($groups_to_create as $group) {
                try {
                    self::computeOwnershipAccountId(
                        $settlement['condo_id'],
                        $settlement['seller_ownership_id'],
                        $group['operation_assignment']
                    );
                    self::computeOwnershipAccountId(
                        $settlement['condo_id'],
                        $settlement['buyer_ownership_id'],
                        $group['operation_assignment']
                    );
                }
                catch(\Exception $e) {
                    $result[$id] = [$e->getMessage() => 'An owner accounting account required by a settlement source is missing.'];
                    break;
                }
            }
        }

        return $result;
    }

    protected static function policyCanValidate($self): array {
        $result = [];

        foreach($self as $id => $settlement) {
            $wrappers = OwnershipTransferSettlementOperation::search([
                    ['settlement_id', '=', $id]
                ])
                ->read([
                    'misc_operation_id' => [
                        'status'
                    ]
                ]);

            foreach($wrappers as $wrapper) {
                if(!$wrapper['misc_operation_id']) {
                    $result[$id] = ['incomplete_existing_operation' => 'A generated operation has no traceable miscellaneous operation.'];
                    break;
                }

                $miscOperation = $wrapper['misc_operation_id'];
                if($miscOperation['status'] !== 'proforma') {
                    $result[$id] = ['misc_operations_not_proforma' => 'Every generated miscellaneous operation must be proforma before validation.'];
                    break;
                }
            }
        }

        return $result;
    }

    protected static function policyCanDispatchCorrespondences($self): array {
        $result = [];

        $self->read([
            'status',
            'correspondences_dispatch_started_at',
            'correspondences_ids' => ['recipient_role', 'owner_id']
        ]);

        foreach($self as $id => $settlement) {
            if($settlement['status'] !== 'validated') {
                $result[$id] = ['settlement_not_validated' => 'Correspondences can only be dispatched after accounting validation.'];
                continue;
            }

            if($settlement['correspondences_dispatch_started_at']) {
                $result[$id] = ['correspondences_already_dispatched' => 'Correspondence delivery has already been scheduled.'];
                continue;
            }

            $roles = [];
            foreach($settlement['correspondences_ids'] as $correspondence) {
                if(!$correspondence['owner_id']) {
                    $result[$id] = ['missing_correspondence_recipient' => 'A correspondence has no recipient.'];
                    continue 2;
                }
                $roles[$correspondence['recipient_role']] = true;
            }

            if(!isset($roles['seller']) || !isset($roles['buyer'])) {
                $result[$id] = ['missing_settlement_correspondences' => 'Seller and buyer correspondences are required.'];
            }
        }

        return $result;
    }

    protected static function policyCanClose($self): array {
        $result = [];

        $self->read([
            'correspondences_dispatch_started_at',
            'correspondences_exporting_task_id' => ['status', 'is_exported'],
            'correspondences_ids' => ['recipient_role', 'communication_method', 'is_sent']
        ]);

        foreach($self as $id => $settlement) {
            if(!$settlement['correspondences_dispatch_started_at']) {
                $result[$id] = ['correspondences_not_dispatched' => 'Correspondence delivery must be started before closing the settlement.'];
                continue;
            }

            $roles = [];
            $has_postal_correspondence = false;
            foreach($settlement['correspondences_ids'] as $correspondence) {
                $roles[$correspondence['recipient_role']] = true;
                if($correspondence['communication_method'] === 'email') {
                    if(!$correspondence['is_sent']) {
                        $result[$id] = ['correspondence_email_not_sent' => 'At least one correspondence email has not been queued.'];
                        continue 2;
                    }
                }
                else {
                    $has_postal_correspondence = true;
                }
            }

            if(!isset($roles['seller']) || !isset($roles['buyer'])) {
                $result[$id] = ['missing_settlement_correspondences' => 'Seller and buyer correspondences are required.'];
                continue;
            }

            if($has_postal_correspondence) {
                $exportingTask = $settlement['correspondences_exporting_task_id'];
                if(!$exportingTask || $exportingTask['status'] !== 'ready') {
                    $result[$id] = ['correspondence_export_not_ready' => 'The postal correspondence export is not ready.'];
                    continue;
                }
                if(!$exportingTask['is_exported']) {
                    $result[$id] = ['correspondence_export_not_downloaded' => 'The postal correspondence export has not been downloaded.'];
                }
            }
        }

        return $result;
    }

    protected static function doGenerateLines($self) {
        // Read raw values: eQual exposes dates and datetimes as integer timestamps.
        $self->read([
            'ownership_transfer_id' => ['property_lots_ids'],
            'condo_id',
            'seller_ownership_id',
            'buyer_ownership_id',
            'transfer_date',
            'snapshot_at',
            'misc_operations_ids'
        ]);

        foreach($self as $id => $settlement) {
            $property_lots_ids = array_values($settlement['ownership_transfer_id']['property_lots_ids']);
            sort($property_lots_ids, SORT_NUMERIC);
            $transfer_date = (int) $settlement['transfer_date'];
            $snapshot_at = (int) $settlement['snapshot_at'];
            // The buyer owns the lots from the transfer date, so the seller's
            // working fund position is taken at the end of the previous day.
            $balance_date = strtotime('-1 day', $transfer_date);
            // Keep one group per accounting source so rounding remains balanced
            // when the lines are later converted into one operation per source.
            $line_groups = [];

            // 1. Transfer the sold lots' share of every working fund from the
            // seller to the buyer, based on the fund balance before the sale.
            $workingFunds = CondoFund::search([
                    ['condo_id', '=', $settlement['condo_id']],
                    ['fund_type', '=', 'working_fund']
                ])
                ->read(['fund_account_id', 'apportionment_id', 'total_shares']);

            foreach($workingFunds as $condo_fund_id => $workingFund) {
                // Use the latest known balance change on or before the balance date.
                $balanceChange = AccountBalanceChange::search([
                        ['condo_id', '=', $settlement['condo_id']],
                        ['account_id', '=', $workingFund['fund_account_id']],
                        ['date', '<=', $balance_date]
                    ], [
                        'sort'  => ['date' => 'desc'],
                        'limit' => 1
                    ])
                    ->read(['date', 'debit_balance', 'credit_balance'])
                    ->first();

                $fund_balance = $balanceChange
                    ? (float) $balanceChange['credit_balance'] - (float) $balanceChange['debit_balance']
                    : 0.0;

                // Allocate the fund balance according to each sold lot's shares.
                $shares = PropertyLotApportionmentShare::search([
                        ['apportionment_id', '=', $workingFund['apportionment_id']],
                        ['property_lot_id', 'in', $property_lots_ids]
                    ])
                    ->read(['property_lot_id', 'property_lot_shares']);

                $source_lines = [];

                foreach($shares as $share) {
                    $amount = $fund_balance * $share['property_lot_shares'] / $workingFund['total_shares'];
                    $source_lines[] = [
                        'correction_type'           => 'working_fund_transfer',
                        'source_type'               => 'working_fund',
                        'condo_fund_id'             => $condo_fund_id,
                        'property_lot_id'           => $share['property_lot_id'],
                        'period_from'               => $balance_date,
                        'period_to'                 => $balance_date,
                        'total_days'                => 0,
                        'seller_days'               => 0,
                        'buyer_days'                => 0,
                        'actual_seller_amount'      => $amount,
                        'actual_buyer_amount'       => 0.0,
                        'theoretical_seller_amount' => 0.0,
                        'theoretical_buyer_amount'  => $amount,
                        'calculated_amount'         => $amount
                    ];
                }

                if(count($source_lines)) {
                    $line_groups[] = $source_lines;
                }
            }

            // 2. Compare posted fund requests with the ownership allocation that
            // should apply economically on and after the transfer date.
            $requestExecutions = FundRequestExecution::search([
                    ['condo_id', '=', $settlement['condo_id']],
                    ['status', '=', 'posted'],
                    ['posting_date', '<=', $snapshot_at]
                ])
                ->read(['posting_date', 'date_from', 'date_to']);

            foreach($requestExecutions as $execution_id => $requestExecution) {
                $period_from = (int) ($requestExecution['date_from'] ?: $requestExecution['posting_date']);
                $period_to = (int) ($requestExecution['date_to'] ?: $period_from);
                if($period_to < $period_from) {
                    throw new \Exception('invalid_fund_request_period', EQ_ERROR_INVALID_CONFIG);
                }

                // Period bounds are inclusive and the transfer day belongs to the buyer.
                [$total_days, $seller_days, $buyer_days] = self::computePeriodDayAllocation(
                    $period_from,
                    $period_to,
                    $transfer_date
                );
                $correction_type = $period_from >= $transfer_date
                    ? 'post_transfer_call'
                    : 'current_period_provision';

                // Aggregate what was actually charged to seller and buyer for each lot.
                $fundRequestExecutionLineEntries = FundRequestExecutionLineEntry::search([
                        ['request_execution_id', '=', $execution_id],
                        ['property_lot_id', 'in', $property_lots_ids],
                        ['ownership_id', 'in', [$settlement['seller_ownership_id'], $settlement['buyer_ownership_id']]]
                    ])
                    ->read(['ownership_id', 'property_lot_id', 'called_amount']);

                $amounts_by_lot = [];
                foreach($fundRequestExecutionLineEntries as $entry) {
                    $property_lot_id = $entry['property_lot_id'];
                    $role = (int) $entry['ownership_id'] === (int) $settlement['seller_ownership_id'] ? 'seller' : 'buyer';
                    $amounts_by_lot[$property_lot_id][$role] = ($amounts_by_lot[$property_lot_id][$role] ?? 0.0) + $entry['called_amount'];
                }

                ksort($amounts_by_lot, SORT_NUMERIC);
                $source_lines = [];

                foreach($amounts_by_lot as $property_lot_id => $amounts) {
                    $actual_seller_amount = $amounts['seller'] ?? 0.0;
                    $actual_buyer_amount = $amounts['buyer'] ?? 0.0;
                    $source_amount = $actual_seller_amount + $actual_buyer_amount;
                    $theoretical_seller_amount = $source_amount * $seller_days / $total_days;
                    $theoretical_buyer_amount = $source_amount - $theoretical_seller_amount;
                    // A positive correction refunds the seller and charges the buyer.
                    $calculated_amount = $actual_seller_amount - $theoretical_seller_amount;

                    $source_lines[] = [
                        'correction_type'           => $correction_type,
                        'source_type'               => 'fund_request_execution',
                        'fund_request_execution_id' => $execution_id,
                        'property_lot_id'           => $property_lot_id,
                        'period_from'               => $period_from,
                        'period_to'                 => $period_to,
                        'total_days'                => $total_days,
                        'seller_days'               => $seller_days,
                        'buyer_days'                => $buyer_days,
                        'actual_seller_amount'      => $actual_seller_amount,
                        'actual_buyer_amount'       => $actual_buyer_amount,
                        'theoretical_seller_amount' => $theoretical_seller_amount,
                        'theoretical_buyer_amount'  => $theoretical_buyer_amount,
                        'calculated_amount'         => $calculated_amount
                    ];
                }

                if(count($source_lines)) {
                    $line_groups[] = $source_lines;
                }
            }

            // 3. Recompute each posted periodic statement against the ownership
            // history resulting from the transfer. The posted source remains intact.
            $expenseStatements = ExpenseStatement::search([
                    ['condo_id', '=', $settlement['condo_id']],
                    ['status', '=', 'posted'],
                    ['posting_date', '<=', $snapshot_at]
                ])
                ->read([
                    'posting_date',
                    'fiscal_period_id' => ['date_from', 'date_to'],
                    'fiscal_year_id'   => ['date_to', 'status']
                ]);

            foreach($expenseStatements as $expense_statement_id => $expenseStatement) {
                $fiscal_period = $expenseStatement['fiscal_period_id'];
                $fiscal_year = $expenseStatement['fiscal_year_id'];

                // The annual closing statement is final and must not be adjusted.
                if(
                    $fiscal_year['status'] === 'closed'
                    && (int) $fiscal_period['date_to'] === (int) $fiscal_year['date_to']
                ) {
                    continue;
                }

                $statementOwnerLines = ExpenseStatementOwnerLine::search([
                        ['invoice_id', '=', $expense_statement_id],
                        ['property_lot_id', 'in', $property_lots_ids],
                        ['ownership_id', 'in', [
                            $settlement['seller_ownership_id'],
                            $settlement['buyer_ownership_id']
                        ]]
                    ])
                    ->read(['ownership_id', 'property_lot_id', 'price']);

                // A statement without an owner line for a transferred lot is unrelated.
                if(!count($statementOwnerLines)) {
                    continue;
                }

                $actual_amounts_by_lot = [];
                foreach($statementOwnerLines as $statementOwnerLine) {
                    $property_lot_id = $statementOwnerLine['property_lot_id'];
                    $role = (int) $statementOwnerLine['ownership_id'] === (int) $settlement['seller_ownership_id']
                        ? 'seller'
                        : 'buyer';
                    $actual_amounts_by_lot[$property_lot_id][$role] =
                        ($actual_amounts_by_lot[$property_lot_id][$role] ?? 0.0)
                        + (float) $statementOwnerLine['price'];
                }

                $theoreticalData = ExpenseStatement::simulateOwnershipTransferData(
                    $expense_statement_id,
                    $settlement['seller_ownership_id'],
                    $settlement['buyer_ownership_id'],
                    $property_lots_ids,
                    $transfer_date
                );

                $property_lots_map = array_fill_keys($property_lots_ids, true);
                $theoretical_amounts_by_lot = [];
                foreach($theoreticalData['owners'] as $owner) {
                    if((int) $owner['id'] === (int) $settlement['seller_ownership_id']) {
                        $role = 'seller';
                    }
                    elseif((int) $owner['id'] === (int) $settlement['buyer_ownership_id']) {
                        $role = 'buyer';
                    }
                    else {
                        continue;
                    }

                    foreach($owner['lines'] as $ownerLine) {
                        $property_lot_id = $ownerLine['property_lot_id'];
                        if(!isset($property_lots_map[$property_lot_id])) {
                            continue;
                        }

                        $theoretical_amounts_by_lot[$property_lot_id][$role] =
                            ($theoretical_amounts_by_lot[$property_lot_id][$role] ?? 0.0)
                            + (float) ($ownerLine['owner'] ?? 0.0)
                            + (float) ($ownerLine['tenant'] ?? 0.0);
                    }
                }

                [$total_days, $seller_days, $buyer_days] = self::computePeriodDayAllocation(
                    (int) $fiscal_period['date_from'],
                    (int) $fiscal_period['date_to'],
                    $transfer_date
                );

                $statement_property_lots_ids = array_values(array_unique(array_merge(
                    array_keys($actual_amounts_by_lot),
                    array_keys($theoretical_amounts_by_lot)
                )));
                sort($statement_property_lots_ids, SORT_NUMERIC);

                $source_lines = [];
                foreach($statement_property_lots_ids as $property_lot_id) {
                    $actual_seller_amount = $actual_amounts_by_lot[$property_lot_id]['seller'] ?? 0.0;
                    $actual_buyer_amount = $actual_amounts_by_lot[$property_lot_id]['buyer'] ?? 0.0;
                    $theoretical_seller_amount = $theoretical_amounts_by_lot[$property_lot_id]['seller'] ?? 0.0;
                    $theoretical_buyer_amount = $theoretical_amounts_by_lot[$property_lot_id]['buyer'] ?? 0.0;

                    $source_lines[] = [
                        'correction_type'           => 'expense_statement_adjustment',
                        'source_type'               => 'expense_statement',
                        'expense_statement_id'      => $expense_statement_id,
                        'property_lot_id'           => $property_lot_id,
                        'period_from'               => $fiscal_period['date_from'],
                        'period_to'                 => $fiscal_period['date_to'],
                        'total_days'                => $total_days,
                        'seller_days'               => $seller_days,
                        'buyer_days'                => $buyer_days,
                        'actual_seller_amount'      => $actual_seller_amount,
                        'actual_buyer_amount'       => $actual_buyer_amount,
                        'theoretical_seller_amount' => $theoretical_seller_amount,
                        'theoretical_buyer_amount'  => $theoretical_buyer_amount,
                        'calculated_amount'         => $actual_seller_amount - $theoretical_seller_amount
                    ];
                }

                if(count($source_lines)) {
                    $line_groups[] = $source_lines;
                }
            }

            // 4. Round each source independently. The last lot absorbs the cent
            // residue so the rounded lines retain the rounded source total.
            $lines = [];
            foreach($line_groups as $source_lines) {
                usort($source_lines, fn($left, $right) => $left['property_lot_id'] <=> $right['property_lot_id']);
                $remaining = round(array_sum(array_column($source_lines, 'calculated_amount')), 2);
                $last_line = array_pop($source_lines);

                foreach($source_lines as $line) {
                    $line['applied_amount'] = round($line['calculated_amount'], 2);
                    $remaining = round($remaining - $line['applied_amount'], 2);
                    $lines[] = $line;
                }

                $last_line['applied_amount'] = $remaining;
                $lines[] = $last_line;
            }

            // 5. Replace the pending settlement lines only after every source was calculated.
            // Manual changes are intentionally discarded when generation is rerun.
            $total = 0.0;
            $line_count = 0;
            // Existing proforma operation lines depend on the settlement lines
            // and must be cleared before those lines are replaced.
            if(count($settlement['misc_operations_ids'])) {
                MiscOperationLine::search([
                        ['misc_operation_id', 'in', $settlement['misc_operations_ids']]
                    ])
                    ->delete(true);
            }
            OwnershipTransferSettlementLine::search(['settlement_id', '=', $id])->delete(true);

            foreach($lines as $line) {
                if(abs($line['applied_amount']) < 0.005) {
                    continue;
                }

                $line['settlement_id'] = $id;
                OwnershipTransferSettlementLine::create($line);
                $total += $line['applied_amount'];
                ++$line_count;
            }

            // #important #lifecycle - `write()` is required for these internally generated readonly fields.
            // `update()` would silently discard them; no lifecycle callback is expected here.
            $source_count = count($line_groups);
            self::id($id)->write([
                'seller_net_amount'     => round($total, 2),
                'buyer_net_amount'      => round($total, 2),
                'has_accounted_sources' => $source_count > 0,
                'alert_summary'         => sprintf('%d settlement line(s) generated from %d accounted source(s).', $line_count, $source_count)
            ]);
        }
    }

    /**
     * Generate settlement correspondences for the seller and buyer communication channels.
     */
    protected static function doGenerateCorrespondences($self) {
        $self->read([
            'condo_id',
            'seller_ownership_id',
            'buyer_ownership_id',
            'correspondences_dispatch_started_at'
        ]);

        foreach($self as $id => $settlement) {
            if($settlement['correspondences_dispatch_started_at']) {
                throw new \Exception('correspondences_already_dispatched', EQ_ERROR_INVALID_PARAM);
            }

            OwnershipTransferSettlementCorrespondence::search([
                ['settlement_id', '=', $id]
            ])->delete(true);

            $ownerships_by_role = [
                'seller' => $settlement['seller_ownership_id'],
                'buyer'  => $settlement['buyer_ownership_id']
            ];

            $map_ownerships = Ownership::ids(array_values($ownerships_by_role))
                ->read(['representative_owner_id'])
                ->get();

            foreach($ownerships_by_role as $recipient_role => $ownership_id) {
                if(!isset($map_ownerships[$ownership_id]) || !$map_ownerships[$ownership_id]['representative_owner_id']) {
                    continue;
                }

                $communication_methods = [
                    'email'                     => false,
                    'postal'                    => false,
                    'postal_registered'         => false,
                    'postal_registered_receipt' => false
                ];

                $communicationPreference = OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $settlement['condo_id']],
                        ['ownership_id', '=', $ownership_id],
                        ['communication_reason', '=', 'technical_communication']
                    ])
                    ->read([
                        'has_channel_email',
                        'has_channel_postal',
                        'has_channel_postal_registered',
                        'has_channel_postal_registered_receipt'
                    ])
                    ->first();

                if($communicationPreference) {
                    $communication_methods = [
                        'email'                     => $communicationPreference['has_channel_email'],
                        'postal'                    => $communicationPreference['has_channel_postal'],
                        'postal_registered'         => $communicationPreference['has_channel_postal_registered'],
                        'postal_registered_receipt' => $communicationPreference['has_channel_postal_registered_receipt']
                    ];
                }

                if(!in_array(true, $communication_methods, true)) {
                    $communication_methods['postal_registered'] = true;
                }

                foreach($communication_methods as $communication_method => $enabled) {
                    if(!$enabled) {
                        continue;
                    }

                    OwnershipTransferSettlementCorrespondence::create([
                        'condo_id'             => $settlement['condo_id'],
                        'settlement_id'        => $id,
                        'recipient_role'       => $recipient_role,
                        'ownership_id'         => $ownership_id,
                        'owner_id'             => $map_ownerships[$ownership_id]['representative_owner_id'],
                        'communication_method' => $communication_method
                    ]);
                }
            }
        }
    }

    protected static function doDispatchCorrespondences($self, $cron) {
        $self->read([
            'name',
            'condo_id',
            'correspondences_exporting_task_id',
            'correspondences_ids' => ['communication_method']
        ]);

        foreach($self as $id => $settlement) {
            if($settlement['correspondences_exporting_task_id']) {
                ExportingTask::id($settlement['correspondences_exporting_task_id'])->delete(true);
            }

            $communication_methods = [];
            foreach($settlement['correspondences_ids'] as $correspondence) {
                $communication_methods[$correspondence['communication_method']] = true;
            }

            if(isset($communication_methods['email'])) {
                $cron->schedule(
                    "realestate.ownership-transfer-settlement.send-correspondences.{$id}",
                    time() + 60,
                    'realestate_property_transfer_OwnershipTransferSettlement_send-emails',
                    ['id' => $id]
                );
            }

            $postal_methods = array_diff(array_keys($communication_methods), ['email']);
            $exporting_task_id = null;
            if(count($postal_methods)) {
                $exportingTask = ExportingTask::create([
                        'name'         => "{$settlement['name']} - Export des correspondances",
                        'condo_id'     => $settlement['condo_id'],
                        'object_class' => static::class,
                        'object_id'    => $id
                    ])
                    ->first();

                foreach($postal_methods as $communication_method) {
                    ExportingTaskLine::create([
                        'exporting_task_id' => $exportingTask['id'],
                        'name'              => "{$settlement['name']} - {$communication_method}",
                        'controller'        => 'realestate_property_transfer_OwnershipTransferSettlement_export-correspondences',
                        'params'            => json_encode([
                            'id'                   => $id,
                            'communication_method' => $communication_method
                        ])
                    ]);
                }

                $exporting_task_id = $exportingTask['id'];
            }

            // #important #lifecycle - `write()` is required for these internally managed readonly fields.
            // `update()` would silently discard them; no lifecycle callback is expected here.
            self::id($id)->write([
                'correspondences_dispatch_started_at' => time(),
                'correspondences_exporting_task_id'   => $exporting_task_id
            ]);
        }
    }

    protected static function onbeforeValidate($self) {
    }

    protected static function onafterValidate($self) {
        foreach($self as $id => $settlement) {
            $wrappers = OwnershipTransferSettlementOperation::search([
                    ['settlement_id', '=', $id]
                ])
                ->read(['misc_operation_id']);

            foreach($wrappers as $wrapper) {
                MiscOperation::id($wrapper['misc_operation_id'])->transition('post');
            }

            // #important #lifecycle - `write()` is required for the workflow-managed readonly timestamp.
            // `update()` would silently discard it; no lifecycle callback is expected here.
            self::id($id)
                ->write(['validated_at' => time()])
                ->do('generate_correspondences');
        }
    }

    protected static function doGenerateOperations($self) {
        $self->read([
            'status',
            'condo_id',
            'seller_ownership_id',
            'buyer_ownership_id',
            'transfer_date',
            'accounting_date',
            'logs',
            'lines_ids' => [
                'source_type',
                'condo_fund_id'             => ['name'],
                'fund_request_execution_id' => ['name'],
                'expense_statement_id'      => ['name'],
                'property_lot_id'           => ['name'],
                'applied_amount'
            ]
        ]);

        foreach($self as $id => $settlement) {
            $groups = self::groupSettlementLines($id, $settlement['lines_ids']);
            $journal = Journal::search([
                    ['condo_id', '=', $settlement['condo_id']],
                    ['journal_type', '=', 'MISC']
                ])
                ->first();

            $created_count = 0;
            $reused_count = 0;
            $settlement_total = 0.0;

            foreach($groups as $group) {
                $group_amount = round($group['amount'], 2);
                $settlement_total += $group_amount;
                $wrapper = OwnershipTransferSettlementOperation::search([
                        ['settlement_id', '=', $id],
                        ['operation_key', '=', $group['operation_key']]
                    ])
                    ->read([
                        'amount',
                        'misc_operation_id' => [
                            'status',
                            'posting_date',
                            'misc_operation_lines_ids'
                        ]
                    ])
                    ->first();

                $source_values = [
                    'source_type' => $group['source_type'],
                    $group['source_field'] => $group['source_id']
                ];
                $is_new_operation = false;

                if($wrapper && $wrapper['misc_operation_id']) {
                    $miscOperation = $wrapper['misc_operation_id'];

                    if(in_array($miscOperation['status'], ['proforma', 'posted'], true)) {
                        if(
                            (int) $miscOperation['posting_date'] !== (int) $settlement['accounting_date']
                            || (
                                (
                                    $miscOperation['status'] === 'posted'
                                    || count($miscOperation['misc_operation_lines_ids'])
                                )
                                && abs((float) $wrapper['amount'] - $group_amount) >= 0.005
                            )
                        ) {
                            throw new \Exception('existing_operation_mismatch', EQ_ERROR_INVALID_CONFIG);
                        }

                        // #important - `write()` is required for these internally generated readonly fields.
                        // `update()` would silently discard them; no lifecycle callback is expected here.
                        OwnershipTransferSettlementOperation::id($wrapper['id'])->write(array_merge(
                            $source_values,
                            [
                                'amount' => $group_amount
                            ]
                        ));

                        // A proforma operation emptied by line regeneration is
                        // repopulated below instead of being treated as reusable.
                        if(
                            $miscOperation['status'] === 'posted'
                            || count($miscOperation['misc_operation_lines_ids'])
                        ) {
                            // #important #lifecycle - `write()` is required for the internally assigned readonly relation.
                            // `update()` would silently discard it; assign the whole group in a single write.
                            OwnershipTransferSettlementLine::ids(array_keys($group['lines']))
                                ->write(['operation_id' => $wrapper['id']]);

                            ++$reused_count;
                            continue;
                        }
                    }

                    elseif($miscOperation['status'] === 'cancelled') {
                        throw new \Exception('existing_operation_cancelled', EQ_ERROR_INVALID_CONFIG);
                    }
                    else {
                        throw new \Exception('existing_operation_not_posted', EQ_ERROR_INVALID_CONFIG);
                    }
                }

                if($wrapper && !$wrapper['misc_operation_id']) {
                    throw new \Exception('incomplete_existing_operation', EQ_ERROR_INVALID_CONFIG);
                }

                $seller_account_id = self::computeOwnershipAccountId(
                    $settlement['condo_id'],
                    $settlement['seller_ownership_id'],
                    $group['operation_assignment']
                );
                $buyer_account_id = self::computeOwnershipAccountId(
                    $settlement['condo_id'],
                    $settlement['buyer_ownership_id'],
                    $group['operation_assignment']
                );

                $description = sprintf(
                    'Régularisation de mutation (%s) - %s',
                    date('d/m/Y', $settlement['transfer_date']),
                    $group['source_name']
                );
                if(!$wrapper) {
                    $miscOperation = MiscOperation::create([
                            'condo_id'      => $settlement['condo_id'],
                            'description'   => $description,
                            'operation_type'=> 'transfer',
                            'posting_date'  => $settlement['accounting_date'],
                            'journal_id'    => $journal['id']
                        ])
                        ->first();

                    $wrapper = OwnershipTransferSettlementOperation::create(array_merge(
                            [
                                'settlement_id'    => $id,
                                'misc_operation_id'=> $miscOperation['id'],
                                'operation_key'    => $group['operation_key'],
                                'amount'           => $group_amount
                            ],
                            $source_values
                        ))
                        ->first();
                    $is_new_operation = true;
                }

                $misc_operation_id = $miscOperation['id'];

                foreach($group['lines'] as $line_id => $line) {
                    $amount = round(abs((float) $line['applied_amount']), 2);
                    $is_seller_credit = (float) $line['applied_amount'] > 0.0;
                    $line_description = sprintf('%s - lot %s', $description, $line['property_lot_name']);

                    MiscOperationLine::create([
                        'condo_id'          => $settlement['condo_id'],
                        'misc_operation_id' => $misc_operation_id,
                        'account_id'        => $seller_account_id,
                        'property_lot_id'   => $line['property_lot_id'],
                        'description'       => $line_description,
                        'debit'             => $is_seller_credit ? 0.0 : $amount,
                        'credit'            => $is_seller_credit ? $amount : 0.0
                    ]);
                    MiscOperationLine::create([
                        'condo_id'          => $settlement['condo_id'],
                        'misc_operation_id' => $misc_operation_id,
                        'account_id'        => $buyer_account_id,
                        'property_lot_id'   => $line['property_lot_id'],
                        'description'       => $line_description,
                        'debit'             => $is_seller_credit ? $amount : 0.0,
                        'credit'            => $is_seller_credit ? 0.0 : $amount
                    ]);
                }

                if($is_new_operation) {
                    MiscOperation::id($misc_operation_id)
                        ->transition('publish');

                    $proformaOperation = MiscOperation::id($misc_operation_id)
                        ->read(['status', 'posting_date', 'accounting_entry_id'])
                        ->first();

                    if($proformaOperation['status'] !== 'proforma' || $proformaOperation['accounting_entry_id']) {
                        throw new \Exception('misc_operation_not_proforma', EQ_ERROR_INVALID_CONFIG);
                    }
                }

                // #important #lifecycle - `write()` is required for the internally assigned readonly relation.
                // `update()` would silently discard it; assign the whole group in a single write.
                OwnershipTransferSettlementLine::ids(array_keys($group['lines']))
                    ->write(['operation_id' => $wrapper['id']]);

                if($is_new_operation) {
                    ++$created_count;
                }
                else {
                    ++$reused_count;
                }
            }

            $log = sprintf(
                '[%s] Accounting operations: %d created, %d reused.',
                date('c'),
                $created_count,
                $reused_count
            );
            $logs = trim(implode(PHP_EOL, array_filter([$settlement['logs'], $log])));

            // #important #lifecycle - `write()` is required for these internally generated readonly fields.
            // `update()` would silently discard them; no lifecycle callback is expected here.
            self::id($id)->write([
                'seller_net_amount' => round($settlement_total, 2),
                'buyer_net_amount'  => round($settlement_total, 2),
                'logs'              => $logs
            ]);
        }
    }

    protected static function onafterClose($self) {
        foreach($self as $id => $settlement) {
            $postalCorrespondences = OwnershipTransferSettlementCorrespondence::search([
                ['settlement_id', '=', $id],
                ['communication_method', '<>', 'email'],
                ['is_sent', '=', false]
            ]);

            if($postalCorrespondences->count()) {
                $postalCorrespondences
                    ->update(['sent_date' => time()])
                    ->update(['is_sent' => true]);
            }

            // #important #lifecycle - `write()` is required for the workflow-managed readonly timestamp.
            // `update()` would silently discard it; no lifecycle callback is expected here.
            self::id($id)->write(['closed_at' => time()]);
        }
    }

    /**
     * Group non-zero settlement lines by their accounted source.
     */
    private static function groupSettlementLines(int $settlement_id, $lines): array {
        $request_execution_ids = [];
        foreach($lines as $line) {
            if(
                $line['source_type'] === 'fund_request_execution'
                && $line['fund_request_execution_id']
            ) {
                $request_execution_ids[] = (int) ($line['fund_request_execution_id']['id'] ?? 0);
            }
        }

        $request_types = [];
        if(count($request_execution_ids)) {
            $requestExecutions = FundRequestExecution::ids(array_values(array_unique($request_execution_ids)))
                ->read(['fund_request_id' => ['request_type']])
                ->get(true);

            foreach($requestExecutions as $execution_id => $requestExecution) {
                $request_types[$execution_id] = $requestExecution['fund_request_id']['request_type'] ?? null;
            }
        }

        $groups = [];
        foreach($lines as $line_id => $line) {
            if(abs((float) $line['applied_amount']) < 0.005) {
                continue;
            }
            if(!$line['property_lot_id']) {
                throw new \Exception('missing_settlement_line_property_lot', EQ_ERROR_INVALID_CONFIG);
            }
            $property_lot_id = (int) ($line['property_lot_id']['id'] ?? 0);
            $property_lot_name = $line['property_lot_id']['name'] ?? '';

            switch($line['source_type']) {
                case 'working_fund':
                    $source_id = (int) ($line['condo_fund_id']['id'] ?? 0);
                    $source_name = $line['condo_fund_id']['name'] ?? '';
                    $source_field = 'condo_fund_id';
                    $operation_assignment = 'co_owners_owner_working_fund';
                    break;

                case 'fund_request_execution':
                    $source_id = (int) ($line['fund_request_execution_id']['id'] ?? 0);
                    $source_name = $line['fund_request_execution_id']['name'] ?? '';
                    $source_field = 'fund_request_execution_id';
                    if(!$source_id || !isset($request_types[$source_id])) {
                        throw new \Exception('missing_fund_request_execution_source', EQ_ERROR_INVALID_CONFIG);
                    }
                    $operation_assignment = FundRequestExecution::getDebitOperationAssignment($request_types[$source_id]);
                    break;

                case 'expense_statement':
                    $source_id = (int) ($line['expense_statement_id']['id'] ?? 0);
                    $source_name = $line['expense_statement_id']['name'] ?? '';
                    $source_field = 'expense_statement_id';
                    $operation_assignment = 'co_owners_owner_working_fund';
                    break;

                default:
                    throw new \Exception('invalid_settlement_line_source_type', EQ_ERROR_INVALID_CONFIG);
            }

            if(!$source_id) {
                throw new \Exception('missing_settlement_line_source', EQ_ERROR_INVALID_CONFIG);
            }

            $operation_key = sprintf('settlement:%d:%s:%d', $settlement_id, $line['source_type'], $source_id);
            if(!isset($groups[$operation_key])) {
                $groups[$operation_key] = [
                    'operation_key'        => $operation_key,
                    'source_type'          => $line['source_type'],
                    'source_field'         => $source_field,
                    'source_id'            => $source_id,
                    'source_name'          => $source_name,
                    'operation_assignment' => $operation_assignment,
                    'amount'               => 0.0,
                    'lines'                => []
                ];
            }

            $line['property_lot_id'] = $property_lot_id;
            $line['property_lot_name'] = $property_lot_name;
            $groups[$operation_key]['amount'] += (float) $line['applied_amount'];
            $groups[$operation_key]['lines'][$line_id] = $line;
        }

        ksort($groups, SORT_STRING);
        foreach($groups as &$group) {
            uksort($group['lines'], static function($first_line_id, $second_line_id) use ($group): int {
                $first_lot_id = (int) $group['lines'][$first_line_id]['property_lot_id'];
                $second_lot_id = (int) $group['lines'][$second_line_id]['property_lot_id'];

                return $first_lot_id <=> $second_lot_id ?: (int) $first_line_id <=> (int) $second_line_id;
            });
        }
        unset($group);

        return $groups;
    }

    private static function computeOwnershipAccountId(int $condo_id, int $ownership_id, string $operation_assignment): int {
        $account = Account::search([
                ['condo_id', '=', $condo_id],
                ['ownership_id', '=', $ownership_id],
                ['operation_assignment', '=', $operation_assignment]
            ])
            ->first();

        if(!$account) {
            throw new \Exception('missing_ownership_accounting_account', EQ_ERROR_INVALID_CONFIG);
        }

        return (int) $account['id'];
    }

    /**
     * Return inclusive total, seller, and buyer day counts for an economic period.
     */
    private static function computePeriodDayAllocation(int $period_from, int $period_to, int $transfer_date): array {
        if($period_to < $period_from) {
            throw new \Exception('invalid_period', EQ_ERROR_INVALID_CONFIG);
        }

        $total_days = (int) ((($period_to - $period_from) / 86400) + 1);
        if($period_to < $transfer_date) {
            return [$total_days, $total_days, 0];
        }
        if($period_from >= $transfer_date) {
            return [$total_days, 0, $total_days];
        }

        $seller_days = (int) (($transfer_date - $period_from) / 86400);

        return [$total_days, $seller_days, $total_days - $seller_days];
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['ownership_transfer_id']);

        foreach($self as $id => $settlement) {
            $result[$id] = sprintf('Ownership transfer settlement #%d', $settlement['ownership_transfer_id']);
        }

        return $result;
    }
}
