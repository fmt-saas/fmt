<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace infra\quota;

use equal\orm\Model;

class QuotaDefinition extends Model {

    public static function getColumns(): array {
        return [

            'metric_definition_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\metering\MetricDefinition',
                'description'       => 'Metric definition concerned by the quota.',
                'required'          => true,
                'dependents'        => ['name', 'code']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['metric_definition_id' => 'name'],
                'description'       => 'Display name of the quota definition.',
                'store'             => true,
                'instant'           => true
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/plain:128',
                'relation'          => ['metric_definition_id' => 'code'],
                'description'       => 'Unique technical code of the definition.',
                'store'             => true,
                'instant'           => true
            ],

            'quota_type' => [
                'type'              => 'string',
                'selection'         => [
                    'instant',
                    'period'
                ],
                'description'       => 'Is the quota based on an instantaneous value or on the accumulated amount over a given period?.',
                'default'           => 'instant'
            ],

            'period_duration' => [
                'type'              => 'string',
                'selection'         => [
                    'day',
                    'week',
                    'month',
                    'year'
                ],
                'description'       => 'The duration of the period for the quota.',
                'default'           => 'week',
                'visible'           => ['quota_type', '=', 'period']
            ]

        ];
    }

    public function getUnique(): array {
        return [
            ['code']
        ];
    }
}
