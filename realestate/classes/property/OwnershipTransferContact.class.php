<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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