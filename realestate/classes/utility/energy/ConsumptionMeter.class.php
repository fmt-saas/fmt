<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

use finance\accounting\Account;

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
                'dependents'        => ['name']
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
                'description'       => "The parent consumption meter, if any.",
                'visible'           => ['meter_scope', 'in', ['passage', 'unit']],
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_active', '=', true], ['meter_scope', '=', 'master']]
            ],

            'children_meters_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\utility\energy\ConsumptionMeter',
                'foreign_field'     => 'parent_meter_id',
                'description'       => "Children consumption meters.",
                'visible'           => ['meter_scope', '=', 'master'],
                'ondetach'          => 'delete',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_active', '=', true]]
            ],

            'date_opening' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
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
                'default'           => 1.0
            ],

            'meter_type' => [
                'type'              => 'string',
                'selection'         => [
                    'virtual',
                    'water',
                    'hot_water',
                    'gas',
                    'electricity',
                    'gas_tank',
                    'oil_tank'
                ],
                'description'       => 'The type of meter consumption.',
                'dependents'        => ['name']
            ],

            'meter_scope' => [
                'type'        => 'string',
                'selection'   => [
                    'master',   // global meter
                    'passage',  // intermediary meter
                    'common',   // common parts meter
                    'unit'      // property lot / private unit meter
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
                'description'       => 'Factory or supplier code identifying the of the consumption meter.',
                'dependents'        => ['name']
            ],

            'meter_ean' => [
                'type'              => 'string',
                'description'       => 'The code identifying  for the European Article Numbering of the consumption meter.',
                'unique'            => true,
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

            // #todo - généraliser ceci pour avoir un PropertyLotSuppliershipReference
            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => 'Property lot the meter relates to.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'visible'           => ['meter_scope', '=', 'unit']
            ],

            'accounting_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'function'          => 'calcAccountingAccountId',
                'store'             => true,
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false]]
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_statutory', '=', false], ['is_active', '=', true], ['status', '=', 'validated']],
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'required'          => true
            ],

        ];
    }

    protected static function calcAccountingAccountId($self) {
        $result = [];
        $self->read(['condo_id']);
        foreach($self as $id => $consumptionMeter) {
            if(!$consumptionMeter['condo_id']) {
                continue;
            }

            $account = Account::search([
                    ['condo_id', '=', $consumptionMeter['condo_id']],
                    ['operation_assignment', '=', 'consumption_statement'],
                ])
                ->read(['id', 'name'])
                ->first();

            if($account) {
                $result[$id] = $account['id'];
            }
        }
        return $result;
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['parent_meter_id'])) {
            $parentMeter = self::id($event['parent_meter_id'])->read(['meter_type'])->first();
            if($parentMeter) {
                $result['meter_type'] = $parentMeter['meter_type'];
            }
        }
        if(isset($event['meter_type']) || isset($event['meter_number']) || isset($event['meter_description'])){
            $meter_type = isset($event['meter_type']) ? $event['meter_type'] : $values['meter_type'];
            $meter_number = isset($event['meter_number']) ? $event['meter_number'] : $values['meter_number'];
            $meter_description = isset($event['meter_description']) ? $event['meter_description'] : $values['meter_description'];
            $result['name'] = self::computeName($meter_type, $meter_number, $meter_description);
        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['meter_number', 'meter_type' , 'meter_description']);

        foreach($self as $id => $meter) {
            $result[$id] = self::computeName($meter['meter_type'], $meter['meter_number'], $meter['meter_description']);
        }
        return $result;
    }

    private static function computeName($type, $number, $description) {
        $meter_map = [
                "virtual"       => "(passage)",
                "water"         => "Eau",
                "hot_water"     => "Eau chaude",
                "gas"           => "Gaz",
                "electricity"   => "Élec",
                "gas_tank"      => "Gaz (cit.)",
                "oil_tank"      => "Mazout"
            ];

        $result = '';
        if($number && strlen($number) > 0) {
            $result = $number . ' ';
        }
        if($type && strlen($type)) {
            $result .= '[' . ($meter_map[$type] ?? $type) . ']';
        }
        if($description && strlen($description)) {
            $result .= ' - ' . $description;
        }
        return $result;
    }

    protected static function oncreate($self, $values) {
        if(isset($values['parent_meter_id'])) {
            $parentMeter = self::id($values['parent_meter_id'])->read(['meter_type'])->first();
            if($parentMeter) {
                $self->update([
                        'meter_scope'   => 'unit',
                        'meter_type'    => $parentMeter['meter_type']
                    ]);
            }
        }
    }

}
