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

            'owners_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\role\RoleAssignment',
                'foreign_field'     => 'ownership_id',
                'description'       => 'List of employees assigned to the management of the condominium.',
                'required'          => true
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

            'identity_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'identity\Identity',
                'required'          => true
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