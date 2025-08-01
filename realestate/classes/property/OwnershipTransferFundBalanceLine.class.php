<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class OwnershipTransferFundBalanceLine extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'description'       => 'Ownership Transfer the line relates to.',
                'required'          => true
            ],

            'condo_fund_id' => [
                'type'              => 'many2one',
                'description'       => "Funds allocated by the condominium.",
                'foreign_object'    => 'realestate\finance\accounting\CondoFund',
                'required'          => true
            ],

            'condo_fund_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Balance of the fund.',
                'help'              => 'This reflects the balance of the fund at the date of creation of the line.',
                'required'          => true
            ],

            'condo_fund_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'relation'          => ['condo_fund_id' => 'total_shares']
            ],

            'property_lots_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The condominium the property lot belongs to.",
                'function'          => 'calcPropertyLotsShares',
            ],

            'property_lots_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcPropertyLotsAmount',
            ],

        ];
    }

    protected static function calcPropertyLotsShares($self) {
        $result = [];
        $self->read(['ownership_transfer_id' => ['property_lots_ids'], 'condo_fund_id' => ['apportionment_id']]);
        foreach($self as $id => $balanceLine) {
            $apportionment_id = $balanceLine['condo_fund_id']['apportionment_id'];
            $shares = 0;

            $apportionmentShares = PropertyLotApportionmentShare::search([
                    ['property_lot_id', 'in', $balanceLine['ownership_transfer_id']['property_lots_ids']],
                    ['apportionment_id', '=', $apportionment_id]
                ])
                ->read(['property_lot_shares']);

            foreach($apportionmentShares as $apportionmentShare) {
                $shares += $apportionmentShare['property_lot_shares'];
            }

            $result[$id] = $shares;
        }
        return $result;
    }

    protected static function calcPropertyLotsAmount($self) {
        $result = [];
        $self->read(['property_lots_shares', 'condo_fund_shares', 'condo_fund_balance']);
        foreach($self as $id => $balanceLine) {
            $result[$id] = $balanceLine['condo_fund_balance'] * $balanceLine['property_lots_shares'] / $balanceLine['condo_fund_shares'];
        }
        return $result;
    }
}