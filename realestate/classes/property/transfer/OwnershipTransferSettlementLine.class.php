<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property\transfer;

class OwnershipTransferSettlementLine extends \equal\orm\Model {

    public function getTable() {
        return 'realestate_property_transfer_settlement_line';
    }

    public static function getColumns() {
        return [
            'settlement_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlement',
                'description'    => 'Settlement to which the calculated line belongs.',
                'required'       => true,
                'readonly'       => true,
                'ondelete'       => 'cascade',
                'dependents'     => ['condo_id', 'seller_ownership_id', 'buyer_ownership_id']
            ],

            'condo_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'realestate\property\Condominium',
                'relation'       => ['settlement_id' => 'condo_id'],
                'description'    => 'Condominium concerned by the settlement line.',
                'store'          => true,
                'instant'        => true,
                'readonly'       => true
            ],

            'seller_ownership_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'realestate\ownership\Ownership',
                'relation'       => ['settlement_id' => 'seller_ownership_id'],
                'description'    => 'Seller ownership concerned by the economic movement.',
                'store'          => true,
                'instant'        => true,
                'readonly'       => true
            ],

            'buyer_ownership_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'realestate\ownership\Ownership',
                'relation'       => ['settlement_id' => 'buyer_ownership_id'],
                'description'    => 'Buyer ownership concerned by the economic movement.',
                'store'          => true,
                'instant'        => true,
                'readonly'       => true
            ],

            'correction_type' => [
                'type'        => 'string',
                'selection'   => [
                    'working_fund_transfer',
                    'current_period_provision',
                    'post_transfer_call',
                    'expense_statement_adjustment'
                ],
                'description' => 'Business reason for the ownership transfer correction.',
                'required'    => true,
                'readonly'    => true
            ],

            'source_type' => [
                'type'        => 'string',
                'selection'   => ['working_fund', 'fund_request_execution', 'expense_statement'],
                'description' => 'Type of accounted source used for the correction.',
                'required'    => true,
                'readonly'    => true
            ],

            'condo_fund_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\finance\accounting\CondoFund',
                'description'    => 'Working fund used as the source of the correction.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'visible'        => ['source_type', '=', 'working_fund'],
                'readonly'       => true
            ],

            'fund_request_execution_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\funding\FundRequestExecution',
                'description'    => 'Posted fund request execution used for the correction.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'visible'        => ['source_type', '=', 'fund_request_execution'],
                'readonly'       => true
            ],

            'expense_statement_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\funding\ExpenseStatement',
                'description'    => 'Posted expense statement used as the source of the correction.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'visible'        => ['source_type', '=', 'expense_statement'],
                'readonly'       => true
            ],

            'property_lot_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\PropertyLot',
                'description'    => 'Transferred property lot concerned by the correction.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'required'       => true,
                'readonly'       => true
            ],

            'period_from' => [
                'type'        => 'date',
                'description' => 'First economic day covered by the source.',
                'readonly'    => true
            ],

            'period_to' => [
                'type'        => 'date',
                'description' => 'Last economic day covered by the source.',
                'readonly'    => true
            ],

            'total_days' => [
                'type'        => 'integer',
                'usage'       => 'amount/natural',
                'description' => 'Inclusive number of days covered by the source.',
                'default'     => 0,
                'readonly'    => true
            ],

            'seller_days' => [
                'type'        => 'integer',
                'usage'       => 'amount/natural',
                'description' => 'Number of source days economically attributable to the seller.',
                'default'     => 0,
                'readonly'    => true
            ],

            'buyer_days' => [
                'type'        => 'integer',
                'usage'       => 'amount/natural',
                'description' => 'Number of source days economically attributable to the buyer.',
                'default'     => 0,
                'readonly'    => true
            ],

            'actual_seller_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:4',
                'description' => 'Amount actually accounted to the seller.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'actual_buyer_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:4',
                'description' => 'Amount actually accounted to the buyer.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'theoretical_seller_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:4',
                'description' => 'Amount attributable to the seller after the transfer.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'theoretical_buyer_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:4',
                'description' => 'Amount attributable to the buyer after the transfer.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'calculated_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:4',
                'description' => 'Calculated transfer amount before any manual decision.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'applied_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Final rounded transfer amount retained for accounting.',
                'default'     => 0.0
            ],

            'operation_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementOperation',
                'description'    => 'Settlement operation generated from the retained line.',
                'readonly'       => true
            ]
        ];
    }

    public static function canupdate($self, $values): array {
        $self->read(['settlement_id' => ['status']]);
        foreach($self as $line) {
            if($line['settlement_id']['status'] !== 'pending') {
                return ['operation_id' => ['settlement_already_validated' => 'A settlement line cannot be modified once settlement has been validated.']];
            }
        }

        return parent::canupdate($self, $values);
    }

    public static function candelete($self) {
        $self->read(['settlement_id' => ['status']]);
        foreach($self as $line) {
            if($line['settlement_id']['status'] !== 'pending') {
                return ['operation_id' => ['settlement_already_validated' => 'A settlement line cannot be deleted once settlement has been validated.']];
            }
        }

        return parent::candelete($self);
    }

}
