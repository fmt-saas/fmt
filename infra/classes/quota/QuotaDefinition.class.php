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

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of the quota definition.',
                'function'          => 'calcName',
                'store'             => true
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

            'metric_definition_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\metering\MetricDefinition',
                'description'       => 'Metric definition concerned by the quota.',
                'required'          => true
            ],

            'thresholds_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'infra\quota\QuotaThreshold',
                'foreign_field'     => 'quota_definition_id',
                'description'       => 'The thresholds who will trigger a controller when reached.'
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['metric_definition_id' => ['name']]);

        foreach($self as $id => $quota) {
            $result[$id] = "Quota - {$quota['metric_definition_id']['name']}";
        }

        return $result;
    }
}
