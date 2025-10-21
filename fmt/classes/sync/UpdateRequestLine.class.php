<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;

class UpdateRequestLine extends Model {

    public static function getColumns() {
        return [

            'update_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'fmt\sync\UpdateRequest',
                'description'       => 'Reference to the parent update request.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'object_field' => [
                'type'              => 'string',
                'description'       => 'Field name of the targeted object.',
                'required'          => true
            ],

            'object_id' => [
                'type'              => 'integer',
                'description'       => 'Identifier of the targeted object.',
                'required'          => true
            ],

            'new_value' => [
                'type'              => 'string',
                'description'       => 'JSON encoded new proposed value for the field.'
            ],

            'old_value' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Old (computed) JSON value currently stored.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'approved',
                    'rejected'
                ],
                'default'           => 'pending',
                'description'       => 'Current status of the update line.'
            ],

            'source_type' => [
                'type'              => 'string',
                'description'       => 'Type of source (local, global, etc.).'
            ],

            'source_origin' => [
                'type'              => 'string',
                'description'       => 'Origin of the data (system, user, integration, etc.).'
            ],

            'source_date' => [
                'type'              => 'datetime',
                'description'       => 'Date of the source data.'
            ],

            'approval_user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\user\User',
                'description'       => 'User who approved this update.',
                'visible'           => ['status', '=', 'approved']
            ],

            'approval_reason' => [
                'type'              => 'string',
                'selection'         => [
                    'unsupervised',
                    'verified'
                ],
                'description'       => 'Reason for approval.',
                'visible'           => ['status', '=', 'approved']
            ],

            'rejection_user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\user\User',
                'description'       => 'User who rejected this update.',
                'visible'           => ['status', '=', 'rejected']
            ],

            'rejection_reason' => [
                'type'              => 'string',
                'description'       => 'Reason for rejection.',
                'selection'         => [
                    'conflict',
                    'incorrect_data',
                    'outdated_data',
                    'duplicate_request'
                ],
                'visible'           => ['status', '=', 'rejected']
            ]

        ];
    }

}
