<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;

class UpdatePolicyLine extends Model {

    public static function getColumns() {
        return [

            'update_policy_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'fmt\sync\UpdateRequest',
                'description'       => 'Reference to the parent update request.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'name' => [
                'type'              => 'alias',
                'result_type'       => 'string',
                'relation'          => ['object_field'],
                'store'             => true
            ],

            'object_field' => [
                'type'              => 'string',
                'description'       => 'Targeted Entity.',
                'unique'            => true,
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

}
