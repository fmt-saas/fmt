<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class PropertyLot extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'List of employees assigned to the management of the condominium.',
                'multilang'         => true
            ],

            'comments' => [
                'type'              => 'string',
                'description'       => 'List of employees assigned to the management of the condominium.'
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'cadastral_number' => [
                'type'              => 'string',
                'description'       => 'Number of the cadastral register of the property.',
            ],

            'lot_area' => [
                'type'              => 'float',
                'description'       => 'Area of the property lot in surface units.',
            ],

            'is_primary' => [
                'type'              => 'boolean',
                'description'       => 'Flag to mark the unit as having sub-units.',
                'default'           => true
            ],

            'secondary_lots_ids' => [
                'type'              => 'one2many',
                'description'       => "The list of rental units the current unit can be divided into, if any (i.e. a dorm might be rent as individual beds).",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'parent_id',
                'visible'           => ['is_primary', '=', true],
            ],

            'primary_lot_id' => [
                'type'              => 'many2one',
                'description'       => "Parent of the property lot.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'visible'           => ['is_primary', '=', false],
            ],

            'tenant_id' => [
                'type'              => 'many2one',
                'description'       => "Rental Unit which current unit belongs to, if any.",
                'foreign_object'    => 'realestate\RentalUnit',

            ],

            'building_id' => [
                'type'              => 'many2one',
                'description'       => "Rental Unit which current unit belongs to, if any.",
                'foreign_object'    => 'realestate\RentalUnit',
            ],

            'ownership_transfers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'foreign_field'     => 'property_lot_id',
                'description'       => "The property purchase transfer file.",
            ]
        ];
    }
}