<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

use fmt\setting\Setting;

class PropertyEntrance extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the entrance belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'readonly'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the Entrance."
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPropertyEntranceCode',
                'store'             => true,
                'description'       => "Code of the supplier for the Condominium.",
                'help'              => "Code is assigned automatically, cannot be changed, and is intended to internal use.",
                'readonly'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Optional description about the property entrance.'
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.',
                'help'              => "It is assumed that zip and city remain the same as the Condominium address. This field is deduced from parent Condominium but can be manually edited.",
            ],

            'address_city' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['condo_id' => 'address_city'],
                'store'             => true,
                'description'       => 'Street and number.',
                'help'              => "It is assumed that zip and city remain the same as the Condominium address. This field is deduced from parent Condominium but can be manually edited.",
            ],

            'address_zip' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['condo_id' => 'address_zip'],
                'store'             => true,
                'description'       => 'Postal code of the entrance (fall back to Condominium address).'
            ],

            'address_country' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['condo_id' => 'address_country'],
                'store'             => true,
                'description'       => 'Country of the entrance (fall back to Condominium address).'
            ],

            'property_lots_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'property_entrance_id',
                'description'       => "Property lots that use the entrance.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ]

        ];


    }

    protected static function calcPropertyEntranceCode($self) {
        $result = [];
        $self->read(['state', 'condo_id']);
        foreach($self as $id => $propertyEntrance) {
            if($propertyEntrance['state'] != 'instance') {
                continue;
            }

            $sequence = Setting::fetch_and_add(
                    'realestate',
                    'organization',
                    "property_entrance.sequence",
                    1,
                    [
                        'condo_id' => $propertyEntrance['condo_id']
                    ]
                );
            if($sequence) {
                $result[$id] = sprintf("%04d", $sequence);
            }
        }
        return $result;
    }


}