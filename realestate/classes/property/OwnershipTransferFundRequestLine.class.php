<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class OwnershipTransferFundRequestLine extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'readonly'          => true
            ],

            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'description'       => 'Ownership Transfer the line relates to.',
                'required'          => true,
                'readonly'          => true
            ],

            'fund_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => "Fund request the line relates to.",
                'required'          => true,
                'readonly'          => true
            ],

            'condo_called_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Balance of the fund.',
                'help'              => 'This reflects the balance of the fund at the date of creation of the line.',
                'function'          => 'calcCondoCalledAmount'
            ],

            'condo_planned_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Balance of the fund.',
                'help'              => 'This reflects the balance of the fund at the date of creation of the line.',
                'function'          => 'calcCondoPlannedAmount',
            ],

            'property_lots_called_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcPropertyLotsCalledAmount',
            ],

            'property_lots_planned_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcPropertyLotsPlannedAmount',
            ]

        ];
    }

    protected static function calcCondoCalledAmount($self) {
        $result = [];
        $self->read(['condo_id', 'fund_request_id' => ['request_executions_ids' => ['@domain' => ['status', '=', 'posted'], 'called_amount']]]);
        foreach($self as $id => $fundRequestLine) {
            $result[$id] = 0.0;
            foreach($fundRequestLine['fund_request_id']['request_executions_ids'] ?? [] as $execution_id => $fundRequestExecution) {
                $result[$id] += $fundRequestExecution['called_amount'];
            }
        }
        return $result;
    }

    protected static function calcCondoPlannedAmount($self) {
        $result = [];
        $self->read(['condo_id', 'fund_request_id' => ['request_executions_ids' => ['@domain' => ['status', '=', 'proforma'], 'called_amount']]]);
        foreach($self as $id => $fundRequestLine) {
            $result[$id] = 0.0;
            foreach($fundRequestLine['fund_request_id']['request_executions_ids'] ?? [] as $execution_id => $fundRequestExecution) {
                $result[$id] += $fundRequestExecution['called_amount'];
            }
        }
        return $result;
    }

    protected static function calcPropertyLotsCalledAmount($self) {
        $result = [];
        $self->read(['condo_id', 'ownership_transfer_id' => ['property_lots_ids'], 'fund_request_id' => ['request_executions_ids' => ['@domain' => ['status', '=', 'posted'], 'execution_line_entries_ids' => ['property_lot_id', 'called_amount']]]]);
        foreach($self as $id => $fundRequestLine) {
            $result[$id] = 0.0;
            foreach($fundRequestLine['fund_request_id']['request_executions_ids'] ?? [] as $execution_id => $fundRequestExecution) {
                foreach($fundRequestExecution['execution_line_entries_ids'] ?? [] as $executionLineEntry) {
                    if(in_array($executionLineEntry['property_lot_id'], $fundRequestLine['ownership_transfer_id']['property_lots_ids'])) {
                        $result[$id] += $executionLineEntry['called_amount'];
                    }
                }
                $result[$id] += $fundRequestExecution['called_amount'];
            }
        }
        return $result;
    }


    protected static function calcPropertyLotsPlannedAmount($self) {
        $result = [];
        $self->read(['condo_id', 'ownership_transfer_id' => ['property_lots_ids'], 'fund_request_id' => ['request_executions_ids' => ['@domain' => ['status', '=', 'proforma'], 'execution_line_entries_ids' => ['property_lot_id', 'called_amount']]]]);
        foreach($self as $id => $fundRequestLine) {
            $result[$id] = 0.0;
            foreach($fundRequestLine['fund_request_id']['request_executions_ids'] ?? [] as $execution_id => $fundRequestExecution) {
                foreach($fundRequestExecution['execution_line_entries_ids'] ?? [] as $executionLineEntry) {
                    if(in_array($executionLineEntry['property_lot_id'], $fundRequestLine['ownership_transfer_id']['property_lots_ids'])) {
                        $result[$id] += $executionLineEntry['called_amount'];
                    }
                }
                $result[$id] += $fundRequestExecution['called_amount'];
            }
        }
        return $result;
    }

}
