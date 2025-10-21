<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class OwnershipTransferAdjustmentLine extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true
            ],

            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership transfer the line relates to .",
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => 'Property Lot that is subject to the transfer.',
                'help'              => 'This serve as first lot for creating the transfer, but can be extended with more lots later on.',
                'required'          => true
            ],

            'fund_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => "Fund request the line relates to.",
                'dependents'        => ['request_account_id', 'request_type'],
            ],

            'request_execution_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'description'       => 'The fund request execution (sale invoice) the line relates to.',
            ],

            'condo_fund_id' => [
                'type'              => 'many2one',
                'description'       => "Funds allocated by the condominium.",
                'foreign_object'    => 'realestate\finance\accounting\CondoFund',
            ],

            'request_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'relation'          => ['fund_request_id' => 'request_account_id'],
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'store'             => true,
                'instant'           => true
            ],

            'request_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Type of fund request.',
                'relation'          => ['fund_request_id' => 'request_type'],
                'store'             => true,
                'instant'           => true
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the line.',
                'required'          => true
            ]

        ];
    }
}