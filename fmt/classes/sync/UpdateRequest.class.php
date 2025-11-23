<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;

class UpdateRequest extends Model {

    public static function getColumns() {
        return [

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Field name of the targeted object.',
                'required'          => true
            ],

            'request_date' => [
                'type'              => 'datetime',
                'description'       => 'Date at which the request was made.',
                'required'          => true
            ],

            'object_id' => [
                'type'              => 'integer',
                'description'       => 'Identifier of the targeted object.',
                'required'          => true,
                'visible'           => ['is_new', '=', false]
            ],

            'is_new' => [
                'type'              => 'boolean',
                'description'       => 'JSON encoded new proposed value for the field.',
                'default'           => false
            ],

            'instance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\server\Instance',
                'description'       => "The instance the request originates from.",
                'dependents'        => ['managing_agent_id']
            ],

            'managing_agent_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'relation'          => ['instance_id' => 'managing_agent_id'],
                'store'             => true,
                'instant'           => true,
                'foreign_object'    => 'realestate\management\ManagingAgent',
                'description'       => "The Managing agent the requests originates from.",
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'approved',
                    'rejected'
                ],
                'default'           => 'pending',
                'description'       => 'Current status of the update request.'
            ],

            'source_type' => [
                'type'              => 'string',
                'description'       => 'Type of source (local, global, etc.).'
            ],

            'source_origin' => [
                'type'              => 'string',
                'description'       => 'Origin of the data (system, user, integration, etc.).'
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
