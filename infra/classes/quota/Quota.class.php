<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace infra\quota;

use equal\orm\Model;

class Quota extends Model {

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
            ],

            'period_start' => [
                'type'              => 'datetime',
                'description'       => 'Start of period needed if the definition type is "period".',
                'visible'           => ['quota_type', '=', 'period']
            ],

            'period_end' => [
                'type'              => 'datetime',
                'description'       => 'End of period needed if the definition type is "period".',
                'visible'           => ['quota_type', '=', 'period']
            ],

            'value' => [
                'type'              => 'integer',
                'description'       => 'Current usage value of a defined quota.',
                'default'           => 0
            ],

            'is_reached' => [
                'type'              => 'boolean',
                'description'       => 'Is the quota exceeded, in most cases the feature must be blocked.',
                'default'           => false
            ],

            'thresholds_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'infra\quota\QuotaThreshold',
                'foreign_field'     => 'quota_id',
                'description'       => 'The thresholds that handle the is_reached value or alerts trigger.'
            ]

        ];
    }

    public function getUnique(): array {
        return [
            ['code']
        ];
    }

    public static function getActions(): array {
        return [

            'update-value' => [
                'description'   => 'Update the quota usage value using the definition inspect data controller.',
                'function'      => 'doUpdateValue'
            ],

            'check-thresholds' => [
                'description'   => 'Check the threshold and trigger action if defined threshold value reached.',
                'function'      => 'doCheckThreshold'
            ]

        ];
    }

    protected static function doUpdateValue($self): void {
        $self->read(['definition_id' => ['metric_definition_id' => ['collector']]]);
        foreach ($self as $id => $quota_usage) {
            $inspect_res = \eQual::run('get', $quota_usage['definition_id']['metric_definition_id']['collector']);

            self::id($id)->update(['value' => $inspect_res['value']]);
        }
    }

    protected static function doCheckThreshold($self): void {
        $self->read(['value', 'thresholds_ids' => ['value', 'max_value']]);
        foreach($self as $quota_usage) {
            foreach($quota_usage['thresholds_ids'] as $threshold) {
                if($quota_usage['value'] >= $threshold['value'] && (!$threshold['max_value'] || $quota_usage['value'] < $threshold['max_value'])) {
                    \eQual::run('do', $threshold['action']);
                }
            }
        }
    }
}
