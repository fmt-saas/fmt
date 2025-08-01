<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace sale\autosale;
use equal\orm\Model;

class Condition extends Model {

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string'
            ],

            'operand' => [
                'type'              => 'string',
                'selection'         => [
                    'nb_pers',
                    'nb_nights',
                    'count_booking_12'
                ],
                'required'          => true
            ],

            'operator' => [
                'type'              => 'string',
                'required'          => true
            ],

            'value' => [
                'type'              => 'string',
                'required'          => true
            ],

            'autosale_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\autosale\AutosaleLine',
                'description'       => 'The autosale line the condition belongs to.',
                'required'          => true
            ]

        ];
    }

}