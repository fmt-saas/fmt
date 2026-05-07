<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\http\HttpRequest;
use fmt\sync\SyncPolicy;
use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => 'Push data from AGENCY instance to GLOBAL instance, depending on ascending sync policies.',
    'help'          => 'This action connects to the GLOBAL instance and push all changed data since last sync.',
    'params'        => [
        'level' => [
            'type'              => 'string',
            'description'       => "Synchronisation level of the policy.",
            'selection'         => [
                'required',
                'recommended',
                'optional',
                'demo'
            ],
            'default'           => 'recommended'
        ]
    ],
    'access' => [
        'visibility'    => 'private'
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

if(constant('FMT_INSTANCE_TYPE') !== 'agency') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$instance = Instance::search(['instance_type', '=', 'agency'])
    ->read(['uuid'])
    ->first();

if(!$instance) {
    throw new Exception('no_instance', EQ_ERROR_INVALID_CONFIG);
}

$global_instance = Instance::search(['instance_type', '=', 'global'])
    ->read(['url', 'access_token'])
    ->first();

if(!$global_instance) {
    throw new Exception('no_global_instance', EQ_ERROR_INVALID_CONFIG);
}

if(empty($global_instance['access_token'])) {
    throw new Exception('missing_access_token', EQ_ERROR_INVALID_CONFIG);
}

$map_sync_levels = [
    'required'      => ['required'],
    'recommended'   => ['required', 'recommended'],
    'optional'      => ['required', 'recommended', 'optional'],
    'demo'          => ['required', 'recommended', 'optional', 'demo']
];

// retrieve SyncPolicy related to 'protected' entities ('private' are descending only)
$policies = SyncPolicy::search([
        ['scope', '=', 'protected'],
        ['sync_direction', '=', 'ascending'],
        ['level', 'in', $map_sync_levels[$params['level']]]
    ])
    ->read([
        'object_class',
        'field_unique',
        'last_sync',
        'sync_policy_lines_ids' => ['object_field', 'scope']
    ]);

$result = [
    'requested' => 0,
    'ignored'   => 0,
    'errors'    => 0,
    'processed' => 0,
    'logs'      => []
];

$now = time();

foreach($policies as $id => $policy) {

    $entity = $policy['object_class'];

    // discard private fields
    $map_fields = [];
    foreach($policy['sync_policy_lines_ids'] as $policy_line_id => $policyLine) {
        $map_fields[$policyLine['object_field']] = $policyLine['scope'];
    }

    // retrieve all fields of the requested entity
    $schema = $orm->getModel($entity)->getSchema();

    // we're only interested in scalar fields and many2one relations
    foreach($schema as $field => $def) {
        if(
            (!isset($def['type']) || !in_array($def['type'], ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'many2one'])) &&
            (!isset($def['result_type']) || !in_array($def['result_type'], ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'many2one']))
        ) {
            unset($schema[$field]);
        }
        elseif(!isset($map_fields[$field])) {
            unset($schema[$field]);
        }
        else {
            $scope = $map_fields[$field];
            if($scope === 'private') {
                unset($schema[$field]);
            }
        }
    }

    $objects = $entity::search(['modified', '>=', $policy['last_sync']])
        ->read(array_keys($schema))
        ->adapt('json')
        ->get(true);

    foreach($objects as $object) {

        $fields_unique = explode(',', $policy['field_unique']);
        foreach($fields_unique as $field_unique) {
            // #memo - even if registration_number is not set, an Identity might have a slug_hash as secondary unique key
            if(!isset($object[$field_unique]) && $policy['object_class'] !== 'identity\Identity') {
                ++$result['ignored'];
                $result['logs'][] = "Ignored entity {$entity} object [{$object['id']}] with no value for unique key field `{$field_unique}`.";
                continue 2;
            }
        }

        // remove special fields
        foreach($object as $field => $value) {
            // #memo - if present, uuid is used on server side to match with existing object
            if(in_array($field, ['id', 'creator', 'modifier', 'created', 'modified', 'state', 'deleted'])) {
                unset($object[$field]);
            }
        }

        try {
            $request = new HttpRequest('POST ' . rtrim($global_instance['url'], '/') . '/?do=fmt_sync_push-from-local');

            $request
                ->body([
                    'entity'            => $entity,
                    'values'            => $object,
                    'instance_uuid'     => $instance['uuid']
                ])
                ->header('Content-Type', 'application/json')
                ->header('Authorization', 'Bearer ' . $global_instance['access_token']);

            /** @var \equal\http\HttpResponse $response */
            $response = $request->send();
            $data = $response->body();
            $status = $response->getStatusCode();

            if($status < 200 || $status > 299) {
                $out = str_replace('\n', '', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                throw new Exception("Error from GLOBAL instance: HTTP status $status: $out", EQ_ERROR_UNKNOWN);
            }
            else {
                switch($data) {
                    case 'ignored':
                        ++$result['ignored'];
                        break;
                    case 'requested':
                        ++$result['requested'];
                        break;
                }
            }
        }
        catch(Exception $e) {
            // force arbitrary update of `modified` field so that failing object is included in next sync loop
            $orm->update($entity, $object['id'], ['modified' => time()]);

            ++$result['errors'];
            $result['logs'][] = "Unable to push protected entity {$entity} ({$object['id']}) to Global instance: " . $e->getMessage();
        }
    }

    SyncPolicy::id($policy['id'])->update(['last_sync' => $now]);
}

if($result['errors'] > 0) {
    // #todo - send email to error reporter
}

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
