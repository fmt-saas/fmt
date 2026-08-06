<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace infra\quota;

use equal\orm\Model;

class QuotaThreshold extends Model {

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the threshold.',
                'required'          => true
            ],

            'quota_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\quota\Quota',
                'description'       => 'The quota that includes the threshold.',
                'required'          => true,
                'dependents'        => ['quota_type'],
            ],

            'quota_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'instant',
                    'period'
                ],
                'relation'          => ['quota_id' => 'quota_type'],
                'description'       => 'Is the quota based on an instantaneous value or on the accumulated amount over a given period?.',
                'store'             => true
            ],

            'threshold_type' => [
                'type'              => 'string',
                'selection'         => [
                    'blocking',
                    'non_blocking'
                ],
                'default'           => 'non_blocking'
            ],

            'trigger_policy' => [
                'type'              => 'string',
                'selection'         => [
                    'always',
                    'on_reach'
                ],
                'default'           => 'on_reach',
                'description'       => 'Determines whether the action is triggered every time the threshold matches or only when the quota becomes reached.'
            ],

            'value' => [
                'type'              => 'integer',
                'description'       => 'Threshold value that must be reached to trigger the controller.',
                'required'          => true
            ],

            'display_value' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Current usage value of a defined quota.',
                'function'          => 'calcDisplayValue',
                'store'             => false
            ],

            'max_value' => [
                'type'              => 'integer',
                'description'       => 'Optional value that will prevent the action to be triggered when the threshold value is reached.'
            ],

            'display_max_value' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Current usage value of a defined quota.',
                'function'          => 'calcDisplayMaxValue',
                'store'             => false
            ],

            'action' => [
                'type'              => 'string',
                'description'       => 'Optional controller to be triggered when the threshold is reached.',
            ]

        ];
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
        $self->read(['value', 'quota_id' => ['metric_definition_id' => ['unit']]]);
        foreach($self as $id => $quota) {
            $display_value = $quota['value'];
            if($quota['quota_id']['metric_definition_id']['unit'] === 'bytes') {
                $display_value = $formatBytes($quota['value']);
            }
            $result[$id] = $display_value;
        }

        return $result;
    }

    public static function calcDisplayMaxValue($self): array {
        $formatBytes = function($bytes, $decimals = 2) {
            $gb = 1024 ** 3;
            $mb = 1024 ** 2;

            if($bytes >= $gb) {
                return round($bytes / $gb, $decimals) . ' GB';
            }

            return round($bytes / $mb, $decimals) . ' MB';
        };

        $result = [];
        $self->read(['max_value', 'quota_id' => ['metric_definition_id' => ['unit']]]);
        foreach($self as $id => $quota) {
            if(is_null($quota['max_value'])) {
                continue;
            }

            $display_max_value = $quota['max_value'];
            if($quota['quota_id']['metric_definition_id']['unit'] === 'bytes') {
                $display_max_value = $formatBytes($quota['max_value']);
            }
            $result[$id] = $display_max_value;
        }

        return $result;
    }
}
