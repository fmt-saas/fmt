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
        'visibility'    => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
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

$uuid = null;

if(isset($params['values']['uuid']) && !empty($params['values']['uuid'])) {
    $uuid = $params['values']['uuid'];
}

$values = $params['values'];

// we're only interested in scalar fields and many2one relations

foreach($schema as $field => $def) {
    if(!isset($values[$field])) {
        continue;
    }
    // only global instance has prerogative on these fields
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

$localObject = null;
$is_empty = true;

$fields = array_keys($values);

// if we received a UUID: search for it; if exists, update, otherwise issue an error (UUIDs are issued by the master instance)
if($uuid) {
    $localObject = $entity::search(['uuid', '=', $uuid])
        ->read($fields)
        ->adapt('json')
        ->first(true);

    // #memo - if we received an uuid, it must be valid
    if(!$localObject) {
        throw new Exception('invalid_uuid', EQ_ERROR_INVALID_PARAM);
    }
}

if(!$localObject && isset($policy['field_unique']) && !empty($values[$policy['field_unique']])) {
    $fields_unique = explode(',', $policy['field_unique']);
    $domain = [];
    foreach($fields_unique as $field_unique) {
        $domain[] = [trim($field_unique), '=', $values[trim($field_unique)]];
    }

    $localObject = $entity::search($domain)
        ->read($fields)
        ->adapt('json')
        ->first(true);
}

if(!$localObject && $policy['object_class'] === 'identity\Identity' && !empty($values['slug_hash'])) {
    $localObject = $entity::search(['slug_hash', '=', $values['slug_hash']])
        ->read($fields)
        ->adapt('json')
        ->first(true);
}

$not_allowed_fields = ['id', 'creator', 'modifier', 'created', 'modified', 'state', 'deleted'];

// a match was found with an existing object
if($localObject) {
    $values_to_update = [];
    foreach($values as $field => $value) {
        // #memo - uuid is always set on global and cannot be changed by local instances
        if(in_array($field, $not_allowed_fields)) {
            continue;
        }
        // ignore unchanged fields (many2many)
        if(is_array($localObject[$field]) && is_array($value)) {
            if(empty(array_diff($localObject[$field], $value)) && empty(array_diff($value, $localObject[$field]))) {
                continue;
            }
        }
        // ignore unchanged fields
        elseif((string) $localObject[$field] === (string) $value) {
            continue;
        }

        $values_to_update[$field] = $value;
    }

    if(!empty($values_to_update)) {
        $updateRequest = UpdateRequest::create([
            'object_class'  => $policy['object_class'],
            'request_date'  => time(),
            'instance_id'   => $instance['id'],
            'source_type'   => 'local',
            'source_origin' => 'sync',
            'object_id'     => $localObject['id']
        ])
            ->first();

        foreach($values_to_update as $field => $value) {
            $old_value = null;
            if(is_array($localObject[$field])) {
                $old_value = json_encode($localObject[$field]);
            }
            elseif(is_null($localObject[$field])) {
                $old_value = 'NULL';
            }
            elseif(is_bool($localObject[$field])) {
                $old_value = $localObject[$field] ? '1' : '0';
            }
            else {
                $old_value = (string) $localObject[$field];
            }

            $new_value = null;
            if(is_array($value)) {
                $new_value = json_encode($value);
            }
            elseif(is_null($value)) {
                $new_value = 'NULL';
            }
            elseif(is_bool($value)) {
                $new_value = $value ? '1' : '0';
            }
            else {
                $new_value = (string) $value;
            }

            UpdateRequestLine::create([
                'update_request_id' => $updateRequest['id'],
                'object_field'      => $field,
                'old_value'         => $old_value,
                'new_value'         => $new_value
            ]);
        }
    }
}
// new object (existing object could not be retrieved), create a new one
else {
    $values_to_update = [];
    foreach($values as $field => $value) {
        // #memo - uuid is always set on global and cannot be changed by local instances
        if(in_array($field, $not_allowed_fields)) {
            continue;
        }

        $values_to_update[$field] = $value;
    }

    if(!empty($values_to_update)) {
        $updateRequest = UpdateRequest::create([
            'object_class'  => $policy['object_class'],
            'request_date'  => time(),
            'instance_id'   => $instance['id'],
            'source_type'   => 'local',
            'source_origin' => 'sync',
            'is_new'        => true
        ])
            ->first();

        foreach($values_to_update as $field => $value) {
            $new_value = null;
            if(is_array($value)) {
                $new_value = json_encode($value);
            }
            elseif(is_null($value)) {
                $new_value = 'NULL';
            }
            elseif(is_bool($value)) {
                $new_value = $value ? '1' : '0';
            }
            else {
                $new_value = (string) $value;
            }

            UpdateRequestLine::create([
                'update_request_id' => $updateRequest['id'],
                'object_field'      => $field,
                'new_value'         => $new_value
            ]);
        }
    }
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
