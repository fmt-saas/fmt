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

            'quota_definition_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\quota\QuotaDefinition',
                'description'       => 'The quota definition that includes the threshold.',
                'required'          => true
            ],

            'quota_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'instant',
                    'period'
                ],
                'relation'          => ['quota_definition_id' => 'name'],
                'description'       => 'Is the quota based on an instantaneous value or on the accumulated amount over a given period?.',
                'store'             => true
            ],

            'value_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/plain:32',
                'relation'          => ['quota_definition_id' => ['metric_definition_id' => 'value_type']],
                'selection'         => [
                    'integer',
                    'decimal',
                    'string'
                ],
                'description'       => 'Type of trigger value.',
                'store'             => true,
                'default'           => 'integer'
            ],

            'value' => [
                'type'              => 'string',
                'usage'             => 'text/plain:128',
                'description'       => 'Threshold value that must be reached to trigger the controller.',
                'required'          => true
            ],

            'max_value' => [
                'type'              => 'string',
                'usage'             => 'text/plain:128',
                'description'       => 'Optional value that will prevent the action to be triggered when the threshold value is reached.'
            ],

            'action' => [
                'type'              => 'string',
                'description'       => 'The controller that is triggered when the threshold is reached.',
                'required'          => true
            ]

        ];
    }
}
