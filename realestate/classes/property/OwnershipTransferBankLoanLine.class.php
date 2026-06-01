<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\property;

class OwnershipTransferBankLoanLine extends \equal\orm\Model {

    public static function getName() {
        return 'Condominium Bank Loan';
    }

    public static function getDescription() {
        return "A bank loan line records a condominium loan and its allocation to a transferred property lot.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => "Short description of the request, based on fiscal year and period.",
                'instant'           => true,
                'store'             => true,
                'readonly'          => true,
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the request, based on fiscal year and period.",
                'dependents'        => ['name'],
                'multilang'         => true
            ],

            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership transfer the line relates to .",
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Apportionment',
                'description'       => "Default apportionment to use when creating accounting entries on this account.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['status', '=', 'validated']],
                'dependents'        => ['property_lot_shares', 'total_shares']
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'description'       => "The Property Lot that the owner refers to.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'ondelete'          => 'cascade',
                'required'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'dependents'        => ['property_lot_shares']
            ],

            'property_lot_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcShares',
                'description'       => "Total shares of the apportionment.",
                'store'             => true
            ],

            'total_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'relation'          => ['apportionment_id' => 'total_shares'],
                'description'       => "Total shares of the apportionment.",
                'store'             => true
            ],

            'total_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total shares of the apportionment.",
            ],

            'property_lot_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total shares of the apportionment."
            ]

        ];
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['description']);

        foreach($self as $id => $condoFund) {

            $result[$id] = $condoFund['description'];
        }

        return $result;
    }

    protected static function calcShares($self) {
        $result = [];
        $self->read(['property_lot_id', 'apportionment_id']);

        foreach($self as $id => $bankLoanLine) {
            $result[$id] = 0;

            if(!$bankLoanLine['property_lot_id'] || !$bankLoanLine['apportionment_id']) {
                continue;
            }

            $apportionmentShare = PropertyLotApportionmentShare::search([
                    ['property_lot_id', '=', $bankLoanLine['property_lot_id']],
                    ['apportionment_id', '=', $bankLoanLine['apportionment_id']]
                ])
                ->read(['property_lot_shares'])
                ->first();

            if($apportionmentShare) {
                $result[$id] = $apportionmentShare['property_lot_shares'];
            }
        }

        return $result;
    }

}
