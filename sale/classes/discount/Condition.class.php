<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\discount;

use equal\orm\Model;

class Condition extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcDisplayName',
                'store'             => true,
                'description'       => 'Resulting display name of the condition.'
            ],

            'operand' => [
                'type'              => 'string',
                'selection'         => [
                    'season',
                    'nb_pers',
                    'duration',
                    'count_booking_24'
                ],
                'required'          => true
            ],

            'operator' => [
                'type'              => 'string',
                'selection'         => ['=', '>', '>=', '<', '<='],
                'required'          => true
            ],

            'value' => [
                'type'              => 'string',
                'required'          => true
            ],

            'discount_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\discount\Discount',
                'description'       => 'The discount list the discount belongs to.',
                'required'          => true
            ]

        ];
    }

    protected static function calcDisplayName($self) {
        $result = [];
        $self->read(['operand', 'operator', 'value']);
        foreach($self as $id => $condition) {
            $result[$id] = "{$condition['operand']} {$condition['operator']} {$condition['value']}";
        }

        return $result;
    }
}
