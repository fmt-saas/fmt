<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

class ConsumptionMeterReading extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the payment relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'consumption_meter_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\ConsumptionMeter',
                'description'       => 'The meter ID relates to the consumption meter reading in the booking.',
                'required'          => true
            ],

            'date_reading' => [
                'type'              => 'date',
                'description'       => 'The day the meter reading is taken.',
                'default'           => time()
            ],

            'previous_index_value' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'The index value of the consumption meter reading.',
                'help'              => 'To prevent rounding issued, indexes are stored as integer: the last 3 digits being the decimal part. Index values must therefore be divided by 1000 for further computations.',
                'function'          => 'calcPreviousIndexValue',
                'store'             => true
            ],

            'index_value' => [
                'type'              => 'integer',
                'description'       => 'The index value of the consumption meter reading.',
                'help'              => 'Indexes are stored as integer: the last 3 digits being the decimal part.
                    Index values must be divided by 1000 for further computations.',
                'required'          => true
            ],

            'display_value'=> [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'number/real',
                'description'       => 'The index value is formatted with a comma starting from the third digit',
                'function'          => 'calcDisplayValue',
                'store'             => true
            ],

        ];
    }

    protected static function calcPreviousIndexValue($self) {
        $result = [];
        $self->read(['consumption_meter_id', 'date_reading']);
        foreach($self as $id => $consumptionMeter) {
            $previousMeter = self::search([
                        ['consumption_meter_id', '=', $consumptionMeter['consumption_meter_id']],
                        ['date_reading', '<', $consumptionMeter['date_reading']]
                    ],
                    ['sort' => ['date_reading' => 'desc'], 'limit' => 1]
                )
                ->read(['index_value'])
                ->first();
            if($previousMeter) {
                $result[$id] = $previousMeter['index_value'];
            }
        }
        return $result;
    }

    protected static function calcDisplayValue($self) {
        $result = [];
        $self->read(['index_value']);
        foreach($self as $id => $meter) {
            $result[$id] = $meter['index_value']/1000;
        }
        return $result;
    }

    public static function onchange($event, $values) {
        $result = [];


        return $result;
    }


}
