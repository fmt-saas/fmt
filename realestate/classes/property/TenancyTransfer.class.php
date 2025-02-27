<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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