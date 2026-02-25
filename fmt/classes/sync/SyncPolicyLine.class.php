<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;

class SyncPolicyLine extends Model {

    public static function getColumns() {
        return [

            'sync_policy_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'fmt\sync\SyncPolicy',
                'description'       => 'Reference to the parent update request.',
                'required'          => true,
                'ondelete'          => 'cascade',
                'dependents'        => ['object_class', 'sync_direction']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['object_field'],
                'store'             => true
            ],

            'sync_direction' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['sync_policy_id' => 'sync_direction'],
                'store'             => true,
                'instant'           => true,
                'description'       => 'Direction of the synchronization.'
            ],

            'object_class' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['sync_policy_id' => 'object_class'],
                'store'             => true,
                'instant'           => true,
                'description'       => 'Targeted Entity.'
            ],

            'object_field' => [
                'type'              => 'string',
                'usage'             => 'text/plain:128',
                'description'       => 'Targeted Entity.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'scope' => [
                'type'              => 'string',
                'selection'         => [
                    // non-sensitive data, automatically synced (marked with `approval_reason = 'unsupervised'`)
                    'public',
                    // data requiring a supervision before being updated
                    'protected',
                    // data that must remain on current instance (depending on direction : `descending` = Global or `ascending` = Local)
                    'private'
                ],
                'required'          => true,
                'description'       => 'Field visibility level (relating to the sync direction).'
            ]

        ];
    }

    public function getUnique() {
        return [
            ['sync_policy_id', 'object_field']
        ];
    }

}
