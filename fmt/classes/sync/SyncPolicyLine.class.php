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
                'dependents'        => ['object_class']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['object_field'],
                'store'             => true
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

            'object_class' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['sync_policy_id' => 'object_class'],
                'store'             => true,
                'description'       => 'Targeted Entity.'
            ],

            'object_field' => [
                'type'              => 'string',
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
                    // data the must remain on current instance (Global or Local)
                    'private'
                ],
                'required'          => true,
                'description'       => 'Field visibility level (relating to the sync direction).'
            ]

        ];
    }

    public function getUnique() {
        return [
            ['sync_policy_id', 'object_field', 'sync_direction']
        ];
    }

}
