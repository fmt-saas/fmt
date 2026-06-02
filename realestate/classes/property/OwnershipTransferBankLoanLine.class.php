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
                'dependents'        => ['property_lot_shares', 'total_shares', 'property_lot_amount'],
                'onupdate'          => 'onupdateApportionmentId'
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'description'       => "The Property Lot that the owner refers to.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'ondelete'          => 'cascade',
                'required'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'dependents'        => ['property_lot_shares', 'property_lot_amount'],
                'onupdate'          => 'onupdatePropertyLotId'
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
                'function'          => 'calcTotalShares',
                'description'       => "Total shares of the apportionment.",
                'store'             => true
            ],

            'total_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total shares of the apportionment.",
                'dependents'        => ['property_lot_amount'],
                'onupdate'          => 'onupdateTotalAmount'
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
            if(!$bankLoanLine['property_lot_id'] || !$bankLoanLine['apportionment_id']) {
                continue;
            }
            $result[$id] = self::computePropertyLotShares($bankLoanLine['property_lot_id'], $bankLoanLine['apportionment_id']);
        }

        return $result;
    }

    protected static function calcTotalShares($self) {
        $result = [];
        $self->read(['apportionment_id']);

        foreach($self as $id => $bankLoanLine) {
            if(!$bankLoanLine['apportionment_id']) {
                continue;
            }
            $result[$id] = self::computeTotalShares($bankLoanLine['apportionment_id']);
        }

        return $result;
    }

    protected static function onupdateApportionmentId($self) {
        $self->read(['property_lot_id', 'apportionment_id', 'total_amount']);

        foreach($self as $id => $bankLoanLine) {
            if(!$bankLoanLine['property_lot_id'] || !$bankLoanLine['apportionment_id']) {
                continue;
            }
            $property_lot_shares = self::computePropertyLotShares($bankLoanLine['property_lot_id'], $bankLoanLine['apportionment_id']);
            $total_shares = self::computeTotalShares($bankLoanLine['apportionment_id']);

            self::id($id)->update([
                'property_lot_shares'   => $property_lot_shares,
                'total_shares'          => $total_shares,
                'property_lot_amount'   => self::computePropertyLotAmount($bankLoanLine['total_amount'], $property_lot_shares, $total_shares)
            ]);
        }
    }

    protected static function onupdatePropertyLotId($self) {
        $self->read(['property_lot_id', 'apportionment_id', 'total_amount']);

        foreach($self as $id => $bankLoanLine) {
            if(!$bankLoanLine['property_lot_id'] || !$bankLoanLine['apportionment_id']) {
                continue;
            }
            $property_lot_shares = self::computePropertyLotShares($bankLoanLine['property_lot_id'], $bankLoanLine['apportionment_id']);
            $total_shares = self::computeTotalShares($bankLoanLine['apportionment_id']);

            self::id($id)->update([
                'property_lot_shares'   => $property_lot_shares,
                'property_lot_amount'   => self::computePropertyLotAmount($bankLoanLine['total_amount'], $property_lot_shares, $total_shares)
            ]);
        }
    }

    protected static function onupdateTotalAmount($self) {
        $self->read(['property_lot_id', 'apportionment_id', 'total_amount']);

        foreach($self as $id => $bankLoanLine) {
            if(!$bankLoanLine['property_lot_id'] || !$bankLoanLine['apportionment_id']) {
                continue;
            }
            $property_lot_shares = self::computePropertyLotShares($bankLoanLine['property_lot_id'], $bankLoanLine['apportionment_id']);
            $total_shares = self::computeTotalShares($bankLoanLine['apportionment_id']);

            self::id($id)->update([
                'property_lot_amount'   => self::computePropertyLotAmount($bankLoanLine['total_amount'], $property_lot_shares, $total_shares)
            ]);
        }
    }

    public static function onchange($event, $values) {
        $result = [];
        $allocation_fields = ['property_lot_id', 'apportionment_id', 'total_amount'];

        if(count(array_intersect(array_keys($event), $allocation_fields)) > 0) {
            $property_lot_id = array_key_exists('property_lot_id', $event) ? $event['property_lot_id'] : ($values['property_lot_id'] ?? null);
            $apportionment_id = array_key_exists('apportionment_id', $event) ? $event['apportionment_id'] : ($values['apportionment_id'] ?? null);
            $total_amount = array_key_exists('total_amount', $event) ? $event['total_amount'] : ($values['total_amount'] ?? null);

            $property_lot_shares = self::computePropertyLotShares($property_lot_id, $apportionment_id);
            $total_shares = self::computeTotalShares($apportionment_id);

            $result['property_lot_shares'] = $property_lot_shares;
            $result['total_shares'] = $total_shares;
            $result['property_lot_amount'] = self::computePropertyLotAmount($total_amount, $property_lot_shares, $total_shares);
        }

        return $result;
    }

    private static function computePropertyLotShares($property_lot_id, $apportionment_id) {
        if(!$property_lot_id || !$apportionment_id) {
            return 0;
        }

        $apportionmentShare = PropertyLotApportionmentShare::search([
                ['property_lot_id', '=', $property_lot_id],
                ['apportionment_id', '=', $apportionment_id]
            ])
            ->read(['property_lot_shares'])
            ->first();

        return $apportionmentShare['property_lot_shares'] ?? 0;
    }

    private static function computeTotalShares($apportionment_id) {
        if(!$apportionment_id) {
            return 0;
        }

        $apportionment = Apportionment::id($apportionment_id)
            ->read(['total_shares'])
            ->first();

        return $apportionment['total_shares'] ?? 0;
    }

    private static function computePropertyLotAmount($total_amount, $property_lot_shares, $total_shares) {
        if($total_shares <= 0) {
            return 0.0;
        }

        return round((float) $total_amount * (float) $property_lot_shares / (float) $total_shares, 2);
    }

}
