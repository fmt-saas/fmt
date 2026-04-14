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
                'onupdate'          => 'onupdateObjectId',
                'visible'           => ['is_new', '=', false]
            ],

            'object_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcObjectName',
                'store'             => true,
                'description'       => 'Display name of the target object.',
                'help'              => 'Based on update request lines and `name` field, if available.'
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

            'update_request_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'fmt\sync\UpdateRequestLine',
                'foreign_field'     => 'update_request_id',
                'description'       => 'Lines of the update request.'
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
                    'unsupervised',  // Approved directly because only 'public' fields modified.
                    'verified',      // Modification on at least one 'protected' field was manually accepted by a user.
                    'forced'         // Approved directly because the modifications were forced, even if protected fields were modified. (forced: if 'accept' flag was used on sync action or if the sync policy scope is 'private')
                ],
                'description'       => 'Reason for approval.',
                'default'           => 'verified',
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
                'default'           => 'conflict',
                'visible'           => ['status', '=', 'rejected']
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
            ]

        ];
    }

    public static function getActions() {
        return [
            'accept' => [
                'description'   => 'Apply changes and mark the request as accepted.',
                'policies'      => [],
                'function'      => 'doAccept'
            ],
            'reject' => [
                'description'   => 'Ignore changes and mark the request as rejected.',
                'policies'      => [],
                'function'      => 'doReject'
            ]
        ];
    }

    protected static function onupdateObjectId($self) {
        $self->read(['object_id']);
        foreach($self as $id => $updateRequest) {
            if($updateRequest['object_id']) {
                self::id($id)->update(['is_new' => false]);
            }
        }
    }

    protected static function calcObjectName($self) {
        $result = [];
        $self->read(['is_new', 'object_class', 'object_id', 'update_request_lines_ids' => ['object_field', 'new_value']]);
        foreach($self as $id => $updateRequest) {
            if($updateRequest['is_new']) {
                $map_fields = [];
                foreach($updateRequest['update_request_lines_ids'] as $line) {
                    if(is_string($line['new_value'])) {
                        $map_fields[$line['object_field']] = $line['new_value'];
                    }
                }

                // #memo - try to find the best name to make the handling of the update request easier
                $name = '(new object)';
                if(!empty($map_fields['name'])) {
                    // most object
                    $name = $map_fields['name'];
                }
                elseif(!empty($map_fields['legal_name'])) {
                    // identity company
                    $name = $map_fields['legal_name'];
                }
                elseif(!empty($map_fields['firstname']) && !empty($map_fields['lastname'])) {
                    // identity person
                    $name = $map_fields['firstname'] . ' ' . strtoupper($map_fields['lastname']);
                }
                elseif(!empty($map_fields['short_name'])) {
                    // other possibility
                    $name = $map_fields['short_name'];
                }
                elseif(!empty($map_fields['description'])) {
                    // last possibility
                    $name = $map_fields['description'];
                }

                $result[$id] = $name;
            }
            elseif(class_exists($updateRequest['object_class'])) {
                $object = $updateRequest['object_class']::id($updateRequest['object_id'])->read(['name'])->first();
                $result[$id] = $object['name'];
            }
        }
        return $result;
    }

    protected static function doAccept($self, $auth, $orm, $values) {
        $self->read(['status', 'object_class', 'object_id', 'is_new', 'update_request_lines_ids']);
        $user_id = $auth->userId();

        foreach($self as $id => $updateRequest) {
            try {
                if(!$updateRequest['object_class']) {
                    continue;
                }

                if(!class_exists($updateRequest['object_class'])) {
                    continue;
                }

                if(!count($updateRequest['update_request_lines_ids'])) {
                    continue;
                }

                $model = $orm->getModel($updateRequest['object_class']);
                $schema = $model->getSchema();

                $data = [];

                foreach($updateRequest['update_request_lines_ids'] as $line_id) {
                    // read the line (support several possible field names)
                    $line = UpdateRequestLine::id($line_id)
                        ->read(['object_field', 'new_value', 'old_value'])
                        ->first();

                    if(!$line) {
                        continue;
                    }

                    $field_descriptor = $schema[$line['object_field']] ?? null;
                    if(!$field_descriptor) {
                        continue;
                    }

                    $type = $field_descriptor['result_type'] ?? ($field_descriptor['type'] ?? '');

                    switch($type) {
                        case 'integer':
                        case 'date':
                        case 'datetime':
                        case 'many2one':
                            $val = (int) $line['new_value'];
                            break;
                        case 'float':
                            $val = (float) $line['new_value'];
                            break;
                        case 'boolean':
                            $val = (bool) $line['new_value'];
                            break;
                        case 'many2many':
                            $val = json_decode($line['new_value'], true);
                            break;
                        case 'string':
                        default:
                            $val = (string) $line['new_value'];
                    }

                    $data[$line['object_field']] = $val;
                }

                if(empty($data)) {
                    continue;
                }

                // create or update target object
                if($updateRequest['is_new']) {
                    $object = $updateRequest['object_class']::create($data)
                        ->first();
                }
                else {
                    $object = $updateRequest['object_class']::id($updateRequest['object_id'])
                        ->update($data)
                        ->first();
                }

                $approval_reason = $values['reason'] ?? '';

                $values = [
                    'status'            => 'approved',
                    'approval_user_id'  => $user_id
                ];

                if(!empty($approval_reason)) {
                    $values['approval_reason'] = $approval_reason;
                }

                self::id($id)->update($values);

                if(method_exists($model, 'getActions')) {
                    $actions = array_keys($updateRequest['object_class']::getActions());
                    if(in_array('sync_uuid_links', $actions)) {
                        $updateRequest['object_class']::id($object['id'])->do('sync_uuid_links');
                    }
                }
            }
            catch(\Exception $e) {
                trigger_error("PHP::unable to apply update request: " . $e->getMessage(), EQ_REPORT_ERROR);
            }
        }
    }

    protected static function doReject($self, $auth, $values) {
        $self->read(['status']);
        $user_id = $auth->userId();

        foreach($self as $id => $updateRequest) {
            $reason = $values['rejection_reason'] ?? null;

            $values = [
                'status'            => 'rejected',
                'rejection_user_id' => $user_id
            ];

            if(!is_null($reason)) {
                $values['rejection_reason'] = $reason;
            }

            self::id($id)->update($values);
        }
    }
}
