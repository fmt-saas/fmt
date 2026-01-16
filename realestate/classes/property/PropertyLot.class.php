<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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
                'readonly'          => true,
                'dependents'        => ['statutory_shares']
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

            'code' => [
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

            'lot_floor' => [
                'type'              => 'string',
                'description'       => 'Level/floor at which the lot is located (if apartment).',
            ],

            'lot_column' => [
                'type'              => 'string',
                'description'       => 'Grid reference as given in notary deed, if any.',
            ],

            'lot_letterbox' => [
                'type'              => 'string',
                'description'       => 'Number or ref of the mailbox relating to the lot, if any.',
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
                'domain'            => [ ['condo_id', '=', 'object.condo_id'], ['is_primary', '=', true] ],
                'visible'           => ['is_primary', '=', false],
            ],

            'nature_id' => [
                'type'              => 'many2one',
                'description'       => "Nature of the property lot.",
                'foreign_object'    => 'realestate\property\PropertyLotNature',
                'required'          => true,
                'dependents'        => ['name', 'nature_id' => 'count_property_lots']
            ],

            'property_lot_nature' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Nature name (from nature_id).",
                'store'             => true,
                'relation'           => ['nature_id' => 'name']
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
                'dependents'        => ['name'],
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'ownership_transfers_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'foreign_field'     => 'property_lots_ids',
                'rel_table'         => 'realestate_propertylot_rel_transfer',
                'rel_foreign_key'   => 'transfer_id',
                'rel_local_key'     => 'lot_id',
                'description'       => 'Property Lots that are part of the ownership transfer.'
            ],

            /*
            'ownerships_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'foreign_field'     => 'property_lots_ids',
                'rel_table'         => 'realestate_ownership_ownership_rel_property_lot',
                'rel_foreign_key'   => 'ownership_id',
                'rel_local_key'     => 'property_lot_id',
                'description'       => 'Ownerships to which this property lot is assigned.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],
            */

            'property_lot_ownerships_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLotOwnership',
                'foreign_field'     => 'property_lot_id',
                'description'       => 'Property lots that are assigned to this ownership.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'apportionment_shares_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLotApportionmentShare',
                'foreign_field'     => 'property_lot_id',
                'description'       => 'Apportionment keys to which this property lot is assigned.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'statutory_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Statutory shares of the property lot in the condominium.",
                'function'          => 'calcStatutoryShares',
                'store'             => true,
                'readonly'          => true
            ],

            // settings

            'has_grouped_statements' => [
                'type'              => 'boolean',
                'description'       => "Flag for grouping secondary-lots in the owner's statement.",
                'default'           => false
            ],

            'property_entrance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyEntrance',
                'description'       => "Specific entrance used by the property lot, if any.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ]

        ];
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['property_lot_ref', 'code', 'nature_id' => ['name'], 'active_ownership_id' => ['name']]);
        foreach($self as $id => $propertyLot) {
            if(isset($propertyLot['code'], $propertyLot['property_lot_ref'], $propertyLot['nature_id'])) {
                $parts = [];
                $parts[] = $propertyLot['code'];
                $parts[] = $propertyLot['property_lot_ref'];
                $parts[] = '(' . $propertyLot['nature_id']['name'] . ')';
                if($propertyLot['active_ownership_id']['name'] ?? null) {
                    $parts[] = $propertyLot['active_ownership_id']['name'];
                }
                $result[$id] = implode(' - ', $parts);
            }
        }
        return $result;
    }

    protected static function calcStatutoryShares($self) {
        $result = [];
        $self->read(['state', 'condo_id']);
        foreach($self as $id => $propertyLot) {
            if($propertyLot['state'] === 'draft') {
                continue;
            }
            $propertyLotApportionmentShare = PropertyLotApportionmentShare::search([
                    ['condo_id', '=', $propertyLot['condo_id']],
                    ['is_statutory', '=', true],
                    ['property_lot_id','=', $id]
                ])
                ->read(['property_lot_shares'])
                ->first();

            if($propertyLotApportionmentShare) {
                $result[$id] = $propertyLotApportionmentShare['property_lot_shares'];
            }
        }
        return $result;
    }

    protected static function calcPropertyLotCode($self) {
        $result = [];
        $self->read(['state', 'condo_id']);
        foreach($self as $id => $propertyLot) {
            if($propertyLot['state'] != 'instance') {
                continue;
            }

            $sequence = Setting::fetch_and_add(
                    'realestate',
                    'organization',
                    "property_lot.sequence",
                    1,
                    [
                        'condo_id' => $propertyLot['condo_id']
                    ]
                );

            if($sequence) {
                $result[$id] = sprintf("%05d", $sequence);
            }
        }
        return $result;
    }

    protected static function onbeforeupdate($self, $values) {
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
