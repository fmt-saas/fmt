<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\customer;
use equal\orm\Model;

class AgeRange extends Model {

    public static function getName() {
        return "Age Range";
    }

    public static function getDescription() {
        return "Age ranges allow to assign consumptions relating to a booking according to the hosts composition of this booking.";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'required'          => true,
                'description'       => 'Name of age range.',
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the age range.",
                'multilang'         => true
            ],

            'age_from' => [
                'type'              => 'integer',
                'description'       => "Age for the lower bound (included).",
                'required'           => true
            ],

            'age_to' => [
                'type'              => 'integer',
                'description'       => "Age for the upper bound (excluded).",
                'required'           => true
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Can the age range be used in bookings?",
                'default'           => true
            ]

        ];
    }


    public static function getConstraints() {
        return [
            'age_from' =>  [
                'out_of_range' => [
                    'message'       => 'Age must be an integer between 0 and 99.',
                    'function'      => function ($age_from, $values) {
                        return ($age_from >= 0 && $age_from <= 99);
                    }
                ]
            ],
            'age_to' =>  [
                'out_of_range' => [
                    'message'       => 'Age must be an integer between 0 and 99.',
                    'function'      => function ($age_to, $values) {
                        return ($age_to >= 0 && $age_to <= 99);
                    }
                ]
            ]
        ];
    }

    public function getUnique() {
        return [
            ['age_from', 'age_to']
        ];
    }
}