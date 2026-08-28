<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property\transfer;

use finance\accounting\AccountBalanceChange;
use realestate\finance\accounting\CondoFund;
use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestExecutionLineEntry;
use realestate\property\OwnershipTransfer;
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

            'calculation_version' => [
                'type'        => 'string',
                'description' => 'Version of the calculation rules used for the settlement.',
                'default'     => '1',
                'required'    => true,
                'readonly'    => true
            ],

            'calculation_hash' => [
                'type'        => 'string',
                'description' => 'Fingerprint of the complete calculation input and result.',
                'readonly'    => true
            ],

            'recalculated_at' => [
                'type'        => 'datetime',
                'description' => 'Date and time of the latest draft recalculation.',
                'readonly'    => true
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

            'operations_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementOperation',
                'foreign_field'  => 'settlement_id',
                'description'    => 'Miscellaneous accounting operations generated by the settlement.'
            ],

            'correspondences_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementCorrespondence',
                'foreign_field'  => 'settlement_id',
                'description'    => 'Seller and buyer correspondences generated for the settlement.'
            ],

            'logs' => [
                'type'        => 'string',
                'usage'       => 'text/plain',
                'description' => 'Technical log of settlement processing.',
                'readonly'    => true
            ],

            'status' => [
                'type'        => 'string',
                'selection'   => ['draft', 'validated', 'closed'],
                'description' => 'Current lifecycle status of the settlement.',
                'default'     => 'draft',
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

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'generate_lines' => [
                'description' => 'Generate settlement lines from the working fund balance and posted fund requests.',
                'policies'    => ['can_generate_lines'],
                'function'    => 'doGenerateLines'
            ],
            'link_ownership_transfer' => [
                'description' => 'Set the settlement link on the related ownership transfer.',
                'policies'    => [],
                'function'    => 'doLinkOwnershipTransfer'
            ]
        ]);
    }

    protected static function oncreate($self) {
        $self->do('link_ownership_transfer');
    }

    protected static function doLinkOwnershipTransfer($self) {
        $settlements = $self->read(['ownership_transfer_id'])->get(true);

        foreach($settlements as $id => $settlement) {
            if(!$settlement['ownership_transfer_id']) {
                continue;
            }

            OwnershipTransfer::id($settlement['ownership_transfer_id'])
                ->update(['ownership_transfer_settlement_id' => $id]);
        }
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_generate_lines' => [
                'description' => 'Checks that the settlement is a complete draft and that working fund apportionments are configured.',
                'function'    => 'policyCanGenerateLines'
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
            'snapshot_at'
        ]);

        foreach($self as $id => $settlement) {
            if($settlement['status'] !== 'draft') {
                $result[$id] = ['settlement_not_draft' => 'Settlement lines can only be generated while the settlement is a draft.'];
                continue;
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

    protected static function doGenerateLines($self) {
        // Read raw values: eQual exposes dates and datetimes as integer timestamps.
        $self->read([
            'ownership_transfer_id' => ['property_lots_ids'],
            'condo_id',
            'seller_ownership_id',
            'buyer_ownership_id',
            'transfer_date',
            'snapshot_at'
        ]);

        foreach($self as $id => $settlement) {
            $property_lots_ids = array_values($settlement['ownership_transfer_id']['property_lots_ids']);
            sort($property_lots_ids, SORT_NUMERIC);
            $transfer_date = (int) $settlement['transfer_date'];
            $snapshot_at = (int) $settlement['snapshot_at'];
            // The buyer owns the lots from the transfer date, so the seller's
            // working fund position is taken at the end of the previous day.
            $balance_date = $transfer_date - 86400;
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
                if($period_to < $transfer_date) {
                    continue;
                }

                // Period bounds are inclusive and the transfer day belongs to the buyer.
                $total_days = (int) ((($period_to - $period_from) / 86400) + 1);
                $seller_days = 0;
                $correction_type = 'post_transfer_call';
                if($period_from < $transfer_date) {
                    $seller_days = (int) (($transfer_date - $period_from) / 86400);
                    $correction_type = 'current_period_provision';
                }
                $buyer_days = $total_days - $seller_days;

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

            // 3. Round each source independently. The last lot absorbs the cent
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

            // 4. Replace the draft lines only after every source was calculated.
            // Manual changes are intentionally discarded when generation is rerun.
            $total = 0.0;
            $line_count = 0;
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

            // Store the generated totals and a concise calculation summary.
            $source_count = count($line_groups);
            self::id($id)->update([
                'calculation_hash'      => hash('sha256', json_encode($lines, JSON_PRESERVE_ZERO_FRACTION)),
                'recalculated_at'       => time(),
                'seller_net_amount'     => round($total, 2),
                'buyer_net_amount'      => round($total, 2),
                'has_accounted_sources' => $source_count > 0,
                'alert_summary'         => sprintf('%d settlement line(s) generated from %d accounted source(s).', $line_count, $source_count)
            ]);
        }
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
