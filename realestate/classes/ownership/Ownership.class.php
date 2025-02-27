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

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
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
                'description'       => 'List of owners.'
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
}