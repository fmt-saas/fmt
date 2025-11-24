<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;

class SyncPolicy extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['object_class'],
                'store'             => true
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Targeted Entity.',
                'unique'            => true,
                'required'          => true,
                'dependents'        => ['name']
            ],

            'field_unique' => [
                'type'              => 'string',
                'description'       => "Field of the entity to use in order to check if object is unique (UUID assignment).",
                'required'          => true
            ],

            'sync_direction' => [
                'type'              => 'string',
                'selection'         => [
                    // Local > Global
                    'ascending',
                    // Global > Local
                    'descending'
                ],
                'required'          => true,
                'description'       => 'Direction of the synchronization.'
            ],

            'sync_policy_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'fmt\sync\SyncPolicyLine',
                'foreign_field'     => 'sync_policy_id',
                'description'       => 'Lines of the Update Policy.',
            ],

            'scope' => [
                'type'              => 'string',
                'selection'         => [
                    // #memo - public is not relevant here (not synced)
                    // management on Local & Global
                    'protected',
                    // management on origin only (based sync direction : `ascending` = Local, `descending` = Global)
                    'private'
                ],
                'required'          => true,
                'description'       => 'Entity Control level - which instance has management.'
            ]

        ];
    }


    public function getUnique() {
        return [
            ['object_class', 'sync_direction']
        ];
    }

}
