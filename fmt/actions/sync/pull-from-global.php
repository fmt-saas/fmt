<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\http\HttpRequest;
use fmt\setting\Setting;
use fmt\sync\SyncPolicy;
use fmt\sync\UpdateRequest;
use fmt\sync\UpdateRequestLine;
use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => 'Request a pull of changed data from GLOBAL instance to local FMT instance.',
    'help'          => 'This action connects to the GLOBAL instance and pulls all changed data since last sync.',
    'params'        => [
        'accept' => [
            'type'              => 'boolean',
            'description'       => "Automatically accept all update requests.",
            'default'           => false
        ]
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_INTERNAL_TOKEN', 'FMT_API_URL_GLOBAL'],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'agency') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

// retrieve SyncPolicy related to 'protected' & 'private' entities
$policies = SyncPolicy::search([
        ['scope', 'in', ['protected', 'private']],
        ['sync_direction', '=', 'descending']
    ])
    ->read([
        'name',
        'scope',
        'object_class',
        'field_unique',
        'last_sync',
        'sync_policy_lines_ids' => ['object_field', 'scope']
    ]);

$result = [
    'created'   => 0,
    'updated'   => 0,
    'requested' => 0,
    'ignored'   => 0,
    'errors'    => 0,
    'processed' => 0,
    'logs'      => []
];

// #memo - on local instances there is a single Instance object
$instance = Instance::search()->read(['uuid'])->first();

if(!$instance || empty($instance['uuid'])) {
    throw new Exception('unknown_instance_uuid', EQ_ERROR_UNKNOWN_OBJECT);
}

foreach($policies as $id => $policy) {

    $now = time();

    $entity = $policy['object_class'];

    $map_fields = [];
    foreach($policy['sync_policy_lines_ids'] as $policy_line_id => $policyLine) {
        $map_fields[$policyLine['object_field']] = $policyLine['scope'];
    }

    try {
        $model = $orm->getModel($entity);
        if(!$model) {
            throw new Exception('unknown_entity', EQ_ERROR_INVALID_PARAM);
        }

        $schema = $model->getSchema();

        $date_from = date('c', $policy['last_sync']);

        $request = new HttpRequest('GET ' . rtrim(constant('FMT_API_URL_GLOBAL'), '/') . '/?get=fmt_sync_pull-from-local' .
                '&entity=' . urlencode($policy['object_class']) .
                '&date_from=' . urlencode($date_from) .
                '&instance_uuid=' . $instance['uuid']
            );

        $request
            ->header('Content-Type', 'application/json')
            ->header('Authorization', 'Bearer ' . constant('FMT_API_INTERNAL_TOKEN'));

        /** @var \equal\http\HttpResponse $response */
        $response = $request->send();

        if($response->getStatusCode() !== 200) {
            throw new Exception('data_fetching_error', EQ_ERROR_CONFLICT_OBJECT);
        }

        $data = $response->body();

        foreach($data as $values) {
            // local search
            $localObject = null;
            $is_empty = true;

            $fields = array_keys($values);

            if(!empty($values['uuid'])) {
                $localObject = $entity::search(['uuid', '=', $values['uuid']])
                    ->read($fields)
                    ->first();
            }

            if(!$localObject && isset($values[$policy['field_unique']]) && !empty($values[$policy['field_unique']])) {
                $fields_unique = explode(',', $policy['field_unique']);
                $domain = [];
                foreach($fields_unique as $field_unique) {
                    $domain[] = [trim($field_unique), '=', $values[trim($field_unique)]];
                }

                $localObject = $entity::search($domain)
                    ->read($fields)
                    ->first();
            }

            // special case for identities
            if(!$localObject && $policy['object_class'] === 'identity\Identity' && isset($values['slug_hash']) && !empty($values['slug_hash'])) {
                $localObject = $entity::search(['slug_hash', '=', $values['slug_hash']])
                    ->read($fields)
                    ->first();
            }

            $not_allowed_fields = ['id', 'creator', 'modifier', 'created', 'modified', 'state', 'deleted'];
            if($policy['scope'] === 'private') {
                // #memo - allow "id" field for "private" scope, because entity cannot be created/modified on agencies' instances
                unset($not_allowed_fields[0]);
            }

            // a match was found with an existing object
            if($localObject) {
                $has_protected_field = false;
                $values_to_update = [];
                foreach($values as $field => $value) {
                    // #memo - if uuid has been received we need it to be part of the update
                    if(in_array($field, $not_allowed_fields)) {
                        continue;
                    }
                    // ignore empty fields
                    if($value === null || $value === '') {
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

                    $scope = $map_fields[$field] ?? 'private';
                    if($scope === 'private') {
                        continue;
                    }
                    elseif($scope === 'protected') {
                        $has_protected_field = true;
                    }

                    $values_to_update[$field] = $value;
                }

                if(!empty($values_to_update)) {
                    $updateRequest = UpdateRequest::create([
                        'object_class'  => $policy['object_class'],
                        'request_date'  => time(),
                        'source_type'   => 'global',
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

                    if($policy['scope'] === 'private' || $params['accept'] || !$has_protected_field) {
                        $approval_reason = !$has_protected_field ? 'unsupervised' : 'forced';

                        // automatically accept
                        UpdateRequest::id($updateRequest['id'])->do('accept', ['reason' => $approval_reason]);

                        $result['logs'][] = "Updated object of entity {$entity} with id {$localObject['id']}";
                        ++$result['updated'];
                    }
                    else {
                        $result['logs'][] = "Requested update of object of entity {$entity} with id {$localObject['id']}";
                        ++$result['requested'];
                    }
                }
            }
            // new object (existing object could not be retrieved), create a new one
            else {
                $has_protected_field = false;
                $values_to_update = [];
                foreach($values as $field => $value) {
                    if(in_array($field, $not_allowed_fields)) {
                        continue;
                    }
                    // ignore empty fields
                    if($value === null || $value === '') {
                        continue;
                    }

                    $scope = $map_fields[$field] ?? 'private';
                    if($scope === 'private') {
                        continue;
                    }
                    elseif($scope === 'protected') {
                        $has_protected_field = true;
                    }

                    $values_to_update[$field] = $value;
                }

                if(!empty($values_to_update)) {
                    $updateRequest = UpdateRequest::create([
                        'object_class'  => $policy['object_class'],
                        'request_date'  => time(),
                        'source_type'   => 'global',
                        'source_origin' => 'sync',
                        'is_new'        => true
                    ])
                        ->first();

                    foreach($values_to_update as $field => $value) {
                        $new_value = null;
                        if(is_array($value)) {
                            $new_value = json_encode($value);
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

                    if($policy['scope'] === 'private' || $params['accept'] || !$has_protected_field) {
                        $approval_reason = !$has_protected_field ? 'unsupervised' : 'forced';

                        // automatically accept private policy
                        UpdateRequest::id($updateRequest['id'])->do('accept', ['reason' => $approval_reason]);

                        $result['logs'][] = "Created new object of entity {$entity}";
                        ++$result['created'];
                    }
                    else {
                        $result['logs'][] = "Requested creation of new object of entity {$entity}";
                        ++$result['requested'];
                    }
                }
            }
        }

        SyncPolicy::id($policy['id'])->update(['last_sync' => $now]);
    }
    catch(Exception $e) {
        ++$result['errors'];
        $result['logs'][] = "Unable to fetch entity {$entity} from Global instance: " . $e->getMessage();
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
