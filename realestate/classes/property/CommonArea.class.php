<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class CommonArea extends \equal\orm\Model {

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
                'description'       => "Name of the common area.",
                'required'          => true
            ],

            'area_type_id' => [
                'type'              => 'many2one',
                'description'       => "The type of the common area.",
                'foreign_object'    => 'realestate\property\CommonAreaType',
                'required'          => true
            ],

            'total_shares' => [
                'type'              => 'float',
                'usage'             => 'number/real:8.6',
                'description'       => "The total number of shares of the Area.",
                'default'           => 100
            ],

        ];
    }
}