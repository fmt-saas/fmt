<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\sync\SyncPolicy;
use fmt\sync\UpdateRequest;
use fmt\sync\UpdateRequestLine;
use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => 'Request an update (or creation) of protected entities created on a LOCAL instance to GLOBAL instance.',
    'help'          => 'This action is meant for the GLOBAL instance and is expected to be called remotely from a LOCAL instance.',
    'params'        => [
        'entity' => [
            'type'              => 'string',
            'required'          => true
        ],
        'values' => [
            'type'              => 'array',
            'required'          => true
        ],
        'instance_uuid' => [
            'type'              => 'string',
            'description'       => 'Instance for which the data are requested.',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $orm] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$instance = Instance::search([['uuid', '=', $params['instance_uuid']]])->first();

if(!$instance) {
    throw new Exception('unknown_instance', EQ_ERROR_INVALID_PARAM);
}

$entity = $params['entity'];

// retrieve all fields of the requested entity
$model = $orm->getModel($entity);
if(!$model) {
    throw new Exception('unknown_entity', EQ_ERROR_INVALID_PARAM);
}

$schema = $model->getSchema();

// #memo - 'public' entities are local-only and are not synchronized

// retrieve SyncPolicy related to 'protected' entities
// #memo - we expect SyncPolicies to remain identical across all instances
$policy = SyncPolicy::search([
        ['scope', '=', 'protected'],
        ['object_class', '=', $entity],
        ['sync_direction', '=', 'ascending']
    ])
    ->read([
        'object_class',
        'field_unique',
        'sync_policy_lines_ids' => ['object_field', 'scope']
    ])
    ->first();

// #todo - pouvoir fusionner deux éléments protected
// #todo - in case of transferring a condominium to another, copy all information (upon validation by the outgoing property manager)


// identify 'private' fields (for which GLOBAL value always take precedence, and which can never be updated via sync)
$map_fields = [];

foreach($policy['sync_policy_lines_ids'] as $policy_line_id => $policyLine) {
    $map_fields[$policyLine['object_field']] = $policyLine['scope'];
}

$$uid = null;

if(isset($params['values']['uuid']) && !empty($params['values']['uuid'])) {
    $uuid = $params['values']['uuid'];
}

$values = $params['values'];

// we're only interested in scalar fields and many2one relations

foreach($schema as $field => $def) {
    if(!isset($values[$field])) {
        continue;
    }
    if(in_array($field, ['id', 'uuid', 'creator', 'modifier', 'created', 'modified', 'state', 'deleted'])) {
        unset($values[$field]);
    }
    // discard non-scalar fields
    elseif(
        (!isset($def['type']) || !in_array($def['type'], ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'many2one'])) &&
        (!isset($def['result_type']) || !in_array($def['result_type'], ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'many2one']))
    ) {
        unset($values[$field]);
    }
    elseif(!isset($map_fields[$field])) {
        unset($values[$field]);
    }
    else {
        $scope = $map_fields[$field];
        if($scope === 'private') {
            unset($values[$field]);
        }
    }
}

// create a new update request (will be removed is empty)
$updateRequest = UpdateRequest::create([
        'object_class'  => $policy['object_class'],
        'request_date'  => time(),
        'instance_id'   => $instance['id'],
        'source_type'   => 'local',
        'source_origin' => 'sync'
    ])
    ->first();

$is_empty = true;

// if we received a UUID: search for it; if exists, update, otherwise issue an error (UUIDs are issued by the master instance)
if($uuid) {
    $fields = array_keys($values);

    $object = $entity::search(['uuid', '=', $uuid])->read($fields)->first();

    if(!$object) {
        throw new Exception('invalid_uuid', EQ_ERROR_INVALID_PARAM);
    }

    UpdateRequest::id($updateRequest['id'])->update(['object_id' => $object['id']]);

    foreach($values as $field => $value) {
        UpdateRequestLine::create([
            'update_request_id'         => $updateRequest['id'],
            'object_field'              => $field,
            'old_value'                 => $object[$field],
            'new_value'                 => $value
        ]);
        $is_empty = false;
    }
}
// we don't have a UUID: in this case, check by other means whether the object already exists (depending on the class)
// if it exists, issue a request to update it
// if it doesn't exist, issue a request to create it
else {
    $key = $policy['field_unique'];

    if(!isset($values[$key]) || empty($values[$key])) {
        throw new Exception('missing_unique_field', EQ_ERROR_INVALID_PARAM);
    }

    try {
        $object = $entity::search([$key, '=', $values[$key]])
            ->read(array_merge(['uuid'], array_keys($values)))
            ->first();

        // manual verification will be required by default
        if($object) {
            UpdateRequest::id($updateRequest['id'])
                ->update(['object_id' => $object['id']]);

            foreach($params['values'] as $field => $value) {
                UpdateRequestLine::create([
                    'update_request_id'         => $updateRequest['id'],
                    'object_field'              => $field,
                    'old_value'                 => $object[$field],
                    'new_value'                 => $value
                ]);
                $is_empty = false;
            }
        }
        else {
            UpdateRequest::id($updateRequest['id'])
                ->update(['is_new' => true]);

            foreach($values as $field => $value) {
                UpdateRequestLine::create([
                    'update_request_id'         => $updateRequest['id'],
                    'object_field'              => $field,
                    'new_value'                 => $value
                ]);
                $is_empty = false;
            }
        }
    }
    catch(Exception $e) {
        trigger_error("APP::error while creating or updating object: " . $e->getMessage(), EQ_REPORT_ERROR);
        throw new Exception('unable_to_create_object', EQ_ERROR_UNKNOWN);
    }

}

// remove update request if empty
if($is_empty) {
    UpdateRequest::id($updateRequest['id'])->delete(true);
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
