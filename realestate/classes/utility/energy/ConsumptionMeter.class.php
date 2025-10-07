<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

class ConsumptionMeter extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the payment relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'meter_description' => [
                'type'              => 'string',
                'description'       => "The short description of the meter.",
                'required'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The name is composed of the meter type and description.',
                'function'          => 'calcName',
                'store'             => true,
                'readonly'          => true
            ],

            'parent_meter_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\utility\energy\ConsumptionMeter',
                'description'       => "The center to which the consumption meter to.",
                'visible'           => ['meter_scope', 'in', ['passage', 'unit']]
            ],

            'date_opening' => [
                'type'              => 'date',
                'description'       => 'The date of the consumption meter opening.',
                'default'           => time()
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => 'Mark the consumption meter as active.',
                'default'           => true
            ],

            'index_value' => [
                'type'              => 'integer',
                'description'       => 'The initial value of the consumption meter.'
            ],

            'coefficient' => [
                'type'              => 'float',
                'description'       => 'The coefficient established in the meter to calculate the actual consumption.',
                'default'           => 1
            ],

            'meter_type' => [
                'type'              => 'string',
                'selection'         => [
                    'water',
                    'gas',
                    'electricity',
                    'gas_tank',
                    'oil_tank'
                ],
                'description'       => 'The type of meter consumption.'
            ],

            'meter_scope' => [
                'type'        => 'string',
                'selection'   => [
                    'master',   // compteur global (pour l'ensemble de la copro)
                    'passage',  // compteur intermédiaire
                    'common',   // compteur lié aux parties communes seules
                    'unit'      // compteur associé à un lot / unité privative
                ],
                'description' => 'The functional scope of the meter within the property network.'
            ],

            'metering_mode' => [
                'type'        => 'string',
                'selection'   => [
                    'direct',   // direct measurement via physical sub-meters
                    'indirect'  // indirect allocation (calorimeters, repartition keys, estimates)
                ],
                'description' => 'The mode of consumption measurement or distribution (direct vs indirect).',
                'visible'     => ['meter_scope', '=', 'master']
            ],

            'has_ean' => [
                'type'              => 'boolean',
                'description'       => 'Mark the consumption meter as European Article Numbering.'
            ],

            'meter_number' => [
                'type'              => 'string',
                'description'       => 'The code identifying the factory of the consumption meter.',
                'visible'           => ['has_ean' , '=', false]
            ],

            'meter_ean' => [
                'type'              => 'string',
                'description'       => 'The code identifying  for the European Article Numbering of the consumption meter.',
                'visible'           => ['has_ean' , '=', true]
            ],

            'meter_unit' => [
                'type'              => 'string',
                'selection'         => [
                    'm3',
                    'kWh',
                    'L',
                    '%',
                    'cm'
                ],
                'description'       => 'The unit of the consumption Meter.'
            ],

            'consumptions_meters_readings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\utility\energy\ConsumptionMeterReading',
                'foreign_field'     => 'consumption_meter_id',
                'description'       => 'List of readings of the consumption meter.'
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => 'List of readings of the consumption meter.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'visible'           => ['meter_scope', '=', 'unit']
            ]

        ];
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['meter_type']) || isset($event['meter_description'])){
            $meter_type = isset($event['meter_type']) ? $event['meter_type'] : $values['meter_type'];
            $meter_description = isset($event['meter_description']) ? $event['meter_description'] : $values['meter_description'];
            $result['name'] = self::computeName($meter_type, $meter_description);
        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['meter_type' , 'meter_description']);

        foreach($self as $id => $meter) {
            $result[$id] = self::computeName($meter['meter_type'], $meter['meter_description']);
        }
        return $result;
    }

    private static function computeName($type, $description) {
        $meter_map = [
            "water"         => "Eau",
            "gas"           => "Gaz",
            "electricity"   => "Élec",
            "gas tank"      => "Gaz (cit.)",
            "oil tank"      => "Mazout"
        ];
        $result = '[' . ($meter_map[$type] ?? $type) . ']';
        if(strlen($description)) {
            $result .= ' - ' . $description;
        }
        return $result;
    }
}
