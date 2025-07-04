<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;


class OwnershipTransferContact extends \equal\orm\Model {

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Optional name of the contact.",
            ],

            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership transfer associated with the property.",
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'required'          => true
            ],

            'email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'description'       => "The email address of the contact.",
                'required'          => true
            ]

        ];
    }
}