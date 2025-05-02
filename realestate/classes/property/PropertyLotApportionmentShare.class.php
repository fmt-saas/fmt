<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class PropertyLotApportionmentShare extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => "Name of the apportionment."
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'ondelete'          => 'cascade',
                'required'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'is_statutory' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "The apportionment describes the statutory quotas.",
                'help'              => "Apportionment describes the rights on the condominium's common areas as defined in the notary deed.",
                'relation'          => ['apportionment_id' => 'is_statutory']
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'description'       => "The Property Lot that the owner refers to.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'ondelete'          => 'cascade',
                'required'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'property_lot_shares' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Amount of shares the owner has on the ownership",
                'help'              => "The amount of shares the targeted property lot has for the apportionment.",
                'default'           => 0,
                'dependents'        => ['name']
            ]

        ];
    }

    public static function canupdate($self) {
        $self->read(['apportionment_id' => ['status']]);
        foreach($self as $id => $apportionmentShare) {
            if($apportionmentShare['apportionment_id']['status'] != 'draft') {
                return ['status' => ['invalid' => 'Published apportionment cannot be updated.']];
            }
        }
        return parent::canupdate($self);
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['property_lot_id' => ['code', 'property_lot_ref'], 'property_lot_shares']);
        foreach($self as $id => $apportionment) {
            $result[$id] = $apportionment['property_lot_id']['code']. ' - '. $apportionment['property_lot_id']['property_lot_ref'] .' (Q. '.$apportionment['property_lot_shares'].')';
        }
        return $result;
    }

    public function getUnique() {
        return [
            ['property_lot_id', 'apportionment_id', 'condo_id']
        ];
    }
}
