<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class PropertyLotApportionment extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => "Name of the apportionment."
            ],

            'apportionment_key_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\ApportionmentKey',
                'required'          => true
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'description'       => "The Property Lot that the owner refers to.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'required'          => true
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

    public static function calcName($self) {
        $result = [];
        $self->read(['property_lot_id' => ['name'], 'property_lot_shares']);
        foreach($self as $id => $apportionment) {
            $result[$id] = $apportionment['name'].' ('.$apportionment['property_lot_shares'].')';
        }
        return $result;
    }

}
