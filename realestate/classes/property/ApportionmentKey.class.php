<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class ApportionmentKey extends \equal\orm\Model {
public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the common area.",
                'required'          => true
            ],

            'common_area_id' => [
                'type'              => 'many2one',
                'description'       => "The type of the common area.",
                'foreign_object'    => 'realestate\property\CommonAreaType'
            ],

            'apportionments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLotApportionment',
                'foreign_field'     => 'apportionment_key_id',
                'description'       => "The apportionment referring to the key."
            ],

            'property_lots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'apportionment_keys_ids',
                'rel_table'         => 'realestate_property_propertylotapportionment',
                'rel_foreign_key'   => 'property_lot_id',
                'rel_local_key'     => 'apportionment_key_id',
                'description'       => 'Property lots that are assigned to this key.'
            ],

            'count_property_lots' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcCountPropertyLots',
                'description'       => 'Total amount of property lots assigned to this nature.',
                'store'             => true
            ],

            'total_shares' => [
                'type'              => 'integer',
                'description'       => "The total number of shares considered for this key.",
                'default'           => 1000
            ]

        ];
    }

    public static function calcCountPropertyLots($self) {
        $result = [];
        $self->read(['property_lots_ids']);
        foreach($self as $id => $key) {

        }
        return $result;
    }
}