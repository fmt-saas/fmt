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

            'availability_controller' => [
                'type'              => 'string',
                'usage'             => 'text/plain.short',
                'description'       => 'Checks candidate quota consumption availability.'
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

            'display_value' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Current usage value of a defined quota.',
                'function'          => 'calcDisplayValue',
                'store'             => false
            ],

            'is_reached' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether a blocking threshold is currently reached.',
                'default'           => false
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether this quota should block feature if exceeded.',
                'default'           => true
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

            'refresh-value' => [
                'description'   => 'Refresh the quota value using the definition inspect data controller.',
                'function'      => 'doRefreshValue'
            ],

            'check-thresholds' => [
                'description'   => 'Check the threshold and trigger action if defined threshold value reached.',
                'function'      => 'doCheckThreshold'
            ],

            'check-availability' => [
                'description'   => 'Check whether a candidate quota increment is allowed.',
                'function'      => 'doCheckAvailability'
            ]

        ];
    }

    protected static function doRefreshValue($self): void {
        $self->read(['quota_type', 'period_start', 'period_end', 'metric_definition_id' => ['collector']]);
        foreach($self as $id => $quota) {
            $params = [];
            if($quota['quota_type'] === 'period') {
                if(!is_null($quota['period_start'])) {
                    $params['period_start'] = $quota['period_start'];
                }
                if(!is_null($quota['period_end'])) {
                    $params['period_end'] = $quota['period_end'];
                }
            }

            $inspect_res = \eQual::run('get', $quota['metric_definition_id']['collector'], $params);
            self::id($id)->update(['value' => $inspect_res['value']]);
        }
    }

    protected static function doCheckThreshold($self): void {
        $self->read(['value', 'is_reached', 'thresholds_ids' => ['value', 'max_value', 'threshold_type', 'action']]);
        foreach($self as $id => $quota) {
            $is_reached = false;
            foreach($quota['thresholds_ids'] as $threshold) {
                if($quota['value'] >= $threshold['value']) {
                    if($threshold['threshold_type'] === 'blocking') {
                        $is_reached = true;
                    }
                    if(!empty($threshold['action']) && $quota['value'] <= ($threshold['max_value'] ?? PHP_INT_MAX)) {
                        \eQual::run('do', $threshold['action']);
                    }
                }
            }
            if($quota['is_reached'] !== $is_reached) {
                self::id($id)->update(['is_reached' => $is_reached]);
            }
        }
    }

    protected static function doCheckAvailability($self, $values): void {
        // first pass - check if already blocked
        $self->read(['is_active', 'is_reached']);

        foreach($self as $id => $quota) {
            if(!$quota['is_active']) {
                continue;
            }

            if($quota['is_reached']) {
                throw new \Exception('quota_unavailable', EQ_ERROR_NOT_ALLOWED);
            }
        }

        // second pass - invoke availability_controller
        $delta = intval($values['delta'] ?? 0);
        $self
            ->read([
                'code',
                'value',
                'is_active',
                'is_reached',
                'availability_controller'
            ]);

        foreach($self as $id => $quota) {
            if(!$quota['is_active']) {
                continue;
            }

            if(empty($quota['availability_controller'])) {
                continue;
            }

            $controller_params = array_merge($values, [
                'code'  => $quota['code'],
                'delta' => $delta
            ]);

            $availability = \eQual::run('get', $quota['availability_controller'], $controller_params);

            if(!is_array($availability) || !array_key_exists('allowed', $availability)) {
                throw new \Exception('invalid_quota_availability_response', EQ_ERROR_INVALID_CONFIG);
            }

            if(is_array($availability) && isset($availability['allowed']) && !$availability['allowed']) {
                throw new \Exception($availability['reason'] ?? 'quota_unavailable', EQ_ERROR_NOT_ALLOWED);
            }
        }

        $self
            ->do('refresh-value')
            ->do('check-thresholds');
    }

    public static function calcDisplayValue($self): array {
        $formatBytes = function($bytes, $decimals = 2) {
            $gb = 1024 ** 3;
            $mb = 1024 ** 2;

            if($bytes >= $gb) {
                return round($bytes / $gb, $decimals) . ' GB';
            }

            return round($bytes / $mb, $decimals) . ' MB';
        };

        $result = [];
        $self->read(['value', 'metric_definition_id' => ['unit']]);
        foreach($self as $id => $quota) {
            $display_value = $quota['value'];
            if($quota['metric_definition_id']['unit'] === 'bytes') {
                $display_value = $formatBytes($quota['value']);
            }
            $result[$id] = $display_value;
        }

        return $result;
    }
}
