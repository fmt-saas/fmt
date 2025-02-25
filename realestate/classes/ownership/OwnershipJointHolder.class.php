<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\ownership;

class OwnershipJointHolder extends \equal\orm\Model {

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
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Ownership',
                'required'          => true
            ],

            'ownership_share' => [
                'type'              => 'float',
                'usage'             => 'amount/percent',
                'description'       => "Share of the ownership, in percent (holders' shares sum must be 100%).",
                'default'           => 1.0
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'description'       => "The Identity of the shareholder.",
                'foreign_object'    => 'identity\Identity',
                'required'          => true
            ]

        ];

    }
}