<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\ownership;

class Ownership extends \equal\orm\Model {


    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name representing the ownership (one or more persons).",
                'function'          => 'calcName',
                'readonly'          => true,
                'store'             => true
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                // 'required'          => true
            ],

            'property_lots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'ownerships_ids',
                'rel_table'         => 'realestate_ownership_ownership_rel_property_lot',
                'rel_foreign_key'   => 'lot_id',
                'rel_local_key'     => 'ownership_id',
                'description'       => 'Property lots that are assigned to this ownership.'
            ],

            'owners_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\ownership\Owner',
                'foreign_field'     => 'ownership_id',
                'description'       => 'List of owners.',
                "domain"            => ['condo_id', '=', 'object.condo_id']
            ],

            'ownership_type' => [
                'type'              => 'string',
                'selection'         => [
                    'unique',
                    'joint'
                ],
                'description'       => "Type of ownership that applies to the owner.",
                'default'          => 'unique'
            ],

            'total_shares' => [
                'type'              => 'integer',
                'description'       => "The total number of shares of the ownership.",
                'default'           => 100,
                'visible'           => ['ownership_type' => 'joint'],
                'dependents'        => ['owners_ids' => 'ownership_percentage']
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "The date from which the ownership is valid.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "The date from which the ownership is valid.",
            ],

            'transfer_from_id' => [
                'type'              => 'many2one',
                'description'       => "The property purchase transfer file.",
                'foreign_object'    => 'realestate\property\OwnershipTransfer'
            ],

            'transfer_to_id' => [
                'type'              => 'many2one',
                'description'       => "The property sale transfer file.",
                'foreign_object'    => 'realestate\property\OwnershipTransfer'
            ],

            'representative_identity_id' => [
                'type'              => 'many2one',
                'description'       => "Person that represents the ownership.",
                'foreign_object'    => 'identity\Identity',
                'visible'           => ['has_representative', '=', true]
            ],

            'has_representative' => [
                'type'              => 'boolean',
                'description'       => "Flag indicating if the ownership has a representative.",
                'default'           => false
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['has_representative', 'representative_identity_id' => ['name'], 'owners_ids' => ['name']]);
        foreach($self as $id => $ownership) {
            if($ownership['has_representative']) {
                $result[$id] = $ownership['representative_identity_id'];
            }
            else {
                $names = [];
                foreach($ownership['owners_ids'] as $owner_id => $owner) {
                    $names[] = $owner['name'];
                }
                $name = implode(', ', $names);
                if(strlen($name) > 128) {
                    $name = substr($name, 0, 128).'...';
                }
                $result[$id] = $name;
            }
        }
        return $result;
    }

}