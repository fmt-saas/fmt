<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\ownership;

class Owner extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'owner_type' => [
                'type'              => 'string',
                'selection'         => [
                    'full',
                    'bare',
                    'usufruct'
                ],
                'description'       => "Type of ownership that applies to the owner.",
                'default'          => 'full'
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'identity\Identity',
                'required'          => true
            ]

        ];
    }
}