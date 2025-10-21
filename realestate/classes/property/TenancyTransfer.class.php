<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class TenancyTransfer extends \equal\orm\Model {
    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'date' => [
                'type'              => 'date',
                'description'       => "Date at which the tenancy transfer took place.",
                'required'          => true
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => "The Property Lot the transfer file relates to.",
            ],

            'tenancy_from_id' => [
                'type'              => 'many2one',
                'description'       => "The previous tenancy.",
                'foreign_object'    => 'realestate\property\Tenancy'
            ],

            'tenancy_to_id' => [
                'type'              => 'many2one',
                'description'       => "The new tenancy.",
                'foreign_object'    => 'realestate\property\Tenancy'
            ]

        ];
    }
}