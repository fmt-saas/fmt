<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property\transfer;

class OwnershipTransferSettlementOperation extends \equal\orm\Model {

    public function getTable() {
        return 'realestate_property_transfer_settlement_operation';
    }

    public static function getColumns() {
        return [
            'settlement_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlement',
                'description'    => 'Settlement that generated the miscellaneous operation.',
                'required'       => true,
                'readonly'       => true,
                'ondelete'       => 'cascade',
                'dependents'     => ['condo_id']
            ],

            'condo_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'realestate\property\Condominium',
                'relation'       => ['settlement_id' => 'condo_id'],
                'description'    => 'Condominium concerned by the miscellaneous operation.',
                'store'          => true,
                'instant'        => true,
                'readonly'       => true
            ],

            'operation_key' => [
                'type'        => 'string',
                'usage'       => 'text/plain:160',
                'description' => 'Stable idempotency key of the generated operation.',
                'required'    => true,
                'readonly'    => true
            ],

            'source_type' => [
                'type'        => 'string',
                'selection'   => ['working_fund', 'fund_request_execution', 'expense_statement'],
                'description' => 'Type of source grouped in the miscellaneous operation.',
                'required'    => true,
                'readonly'    => true
            ],

            'condo_fund_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\finance\accounting\CondoFund',
                'description'    => 'Working fund corrected by the operation.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'visible'        => ['source_type', '=', 'working_fund'],
                'readonly'       => true
            ],

            'fund_request_execution_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\funding\FundRequestExecution',
                'description'    => 'Fund request execution corrected by the operation.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'visible'        => ['source_type', '=', 'fund_request_execution'],
                'readonly'       => true
            ],

            'expense_statement_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\funding\ExpenseStatement',
                'description'    => 'Expense statement corrected by the operation.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'visible'        => ['source_type', '=', 'expense_statement'],
                'readonly'       => true
            ],

            'misc_operation_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'finance\accounting\MiscOperation',
                'description'    => 'Miscellaneous operation generated for the correction.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'required'       => true,
                'ondelete'       => 'cascade',
                'readonly'       => true
            ],

            'accounting_entry_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'finance\accounting\AccountingEntry',
                'description'    => 'Balanced accounting entry created for the correction.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'readonly'       => true
            ],

            'accounting_date' => [
                'type'        => 'date',
                'description' => 'Date on which the miscellaneous operation is accounted.',
                'required'    => true,
                'readonly'    => true
            ],

            'amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Net transfer amount represented by the operation.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'lines_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementLine',
                'foreign_field'  => 'operation_id',
                'description'    => 'Settlement lines grouped into this operation.'
            ]
        ];
    }

    public function getUnique() {
        return [
            ['settlement_id', 'operation_key'],
            ['settlement_id', 'misc_operation_id']
        ];
    }
}
