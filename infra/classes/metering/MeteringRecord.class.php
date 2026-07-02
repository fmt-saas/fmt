<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace infra\metering;

use equal\orm\Model;

class MeteringRecord extends Model {

    public static function getDescription(): string {
        return "Records an action/event that was triggered. It's used to compute a reading line's value.";
    }

    public static function getColumns(): array {
        return [

            'metric_definition_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\metering\MetricDefinition',
                'description'       => 'Metric definition measured by this line.',
                'required'          => true
            ],

            'record_time' => [
                'type'              => 'datetime',
                'description'       => 'The moment when the action/event occurred.',
                'default'           => fn() => time()
            ],

            'value' => [
                'type'              => 'integer',
                'description'       => 'Measured value.',
                'default'           => 1
            ]

        ];
    }
}
