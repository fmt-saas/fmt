<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

use fmt\setting\Setting;
use realestate\ownership\Ownership;

class PropertyLot extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'readonly'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => "Name of the apportionment.",
                'store'             => true,
                'readonly'          => true,
                'multilang'         => true
            ],

            'property_lot_ref' => [
                'type'              => 'string',
                'description'       => "Reference used in the notary deed to identify the lot.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'property_lot_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPropertyLotCode',
                'store'             => true,
                'description'       => "Code of the property lot.",
                'help'              => "Code is assigned automatically and cannot be changed, and is intended to internal use.",
                'readonly'          => true
            ],

            'comments' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Comments about the property lot.'
            ],

            'cadastral_number' => [
                'type'              => 'string',
                'description'       => 'Number of the cadastral register of the property.',
            ],

            'lot_area' => [
                'type'              => 'float',
                'usage'             => 'number/float:3.2',
                'description'       => 'Total area of the property lot in surface units.',
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
                'foreign_field'     => 'primary_lot_id',
                'visible'           => ['is_primary', '=', true],
            ],

            'primary_lot_id' => [
                'type'              => 'many2one',
                'description'       => "Parent of the property lot.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'visible'           => ['is_primary', '=', false],
            ],

            'nature_id' => [
                'type'              => 'many2one',
                'description'       => "Rental Unit which current unit belongs to, if any.",
                'foreign_object'    => 'realestate\property\PropertyLotNature',
                'required'          => true,
                'dependents'        => ['name', 'nature_id' => 'count_property_lots']
            ],

            'has_tenancy' => [
                'type'              => 'boolean',
                'description'       => "Flag to mark the lot as being rented.",
                'default'           => false
            ],

            'active_tenancy_id' => [
                'type'              => 'many2one',
                'description'       => "Current tenancy, if applicable.",
                'foreign_object'    => 'realestate\property\Tenancy',
                'visible'           => ['has_tenancy', '=', true]
            ],

            'tenancies_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\Tenancy',
                'foreign_field'     => 'property_lot_id',
                'description'       => 'Ownerships to which this property lot is assigned.'
            ],

            // #todo
            'building_id' => [
                'type'              => 'many2one',
                'description'       => "Rental Unit which current unit belongs to, if any.",
                'foreign_object'    => 'realestate\RentalUnit',
            ],

            'active_ownership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'description'       => "Current ownership of the property lot.",
                'onupdate'          => 'onupdateActiveOwnershipId'
            ],

            'ownership_transfers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'foreign_field'     => 'property_lot_id',
                'description'       => "The property purchase transfer file.",
            ],

            'ownerships_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'foreign_field'     => 'property_lots_ids',
                'rel_table'         => 'realestate_ownership_ownership_rel_property_lot',
                'rel_foreign_key'   => 'ownership_id',
                'rel_local_key'     => 'lot_id',
                'description'       => 'Ownerships to which this property lot is assigned.'
            ],

            'apportionment_shares_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLotApportionmentShare',
                'foreign_field'     => 'property_lot_id',
                'description'       => 'Apportionment keys to which this property lot is assigned.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            // settings

            'has_grouped_statements' => [
                'type'              => 'boolean',
                'description'       => "Flag for grouping secondary-lots in the owner's statement.",
                'default'           => false
            ],

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['property_lot_ref', 'property_lot_code', 'nature_id' => ['name']]);
        foreach($self as $id => $propertyLot) {
            if(isset($propertyLot['property_lot_code'], $propertyLot['property_lot_ref'], $propertyLot['nature_id'])) {
                $result[$id] = $propertyLot['property_lot_code'] . ' - ' . $propertyLot['property_lot_ref'] . ' (' . $propertyLot['nature_id']['name'] . ')';
            }
        }
        return $result;
    }

    public static function calcPropertyLotCode($self) {
        $result = [];
        $self->read(['state', 'condo_id']);
        foreach($self as $id => $propertyLot) {
            if($propertyLot['state'] != 'instance') {
                continue;
            }
            $sequence = Setting::fetch_and_add(
                    'realestate',
                    'main',
                    "property_lot.sequence",
                    1,
                    [
                        'condo_id' => $propertyLot['condo_id']
                    ]
                );
            $result[$id] = sprintf("%05d", $sequence);
        }
        return $result;
    }

    public static function onupdateActiveOwnershipId($self) {
        $self->read(['active_ownership_id', 'ownerships_ids']);
        foreach($self as $id => $propertyLot) {
            // if assigned ownership_id is not in history: add it
            if(!in_array($propertyLot['active_ownership_id'], $propertyLot['ownerships_ids'])) {
                self::id($id)->update(['ownerships_ids' => '+' . $propertyLot['active_ownership_id']]);
            }
        }
    }

    public static function onbeforeupdate($self, $values) {
        // trigger update for count_property_lots of previously assigned nature
        if(isset($values['nature_id'])) {
            $natures_ids = array_map(function ($a) { return $a['nature_id'];}, $self->read(['nature_id'])->get(true));
            PropertyLotNature::ids($natures_ids)->update(['count_property_lots' => null]);
        }
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['active_ownership_id'])) {
            $ownership = Ownership::id($event['active_ownership_id'])->read(['condo_id' => ['id', 'name']])->first();
            if($ownership) {
                $result['condo_id'] = [
                        'id'   => $ownership['condo_id']['id'],
                        'name' => $ownership['condo_id']['name']
                    ];
            }
        }
        return $result;
    }

}
