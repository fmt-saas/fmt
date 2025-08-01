<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace sale\contract;
use equal\orm\Model;

class ContractType extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'required'          => true
            ],

            'description' => [
                'type'              => 'string'
            ],

            'is_active' => [
                'type'              => 'boolean',
                'default'           => true
            ]

        ];
    }

}
