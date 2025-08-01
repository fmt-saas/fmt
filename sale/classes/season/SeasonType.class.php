<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace sale\season;
use equal\orm\Model;

class SeasonType extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Short code of the type."
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Explanation on when to use the type and its specifics.",
                'default'           => '',
                'multilang'         => true
            ]

        ];
    }

}