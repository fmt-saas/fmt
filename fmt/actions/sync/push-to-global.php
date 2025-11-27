<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use equal\http\HttpRequest;
use fmt\setting\Setting;
use fmt\sync\SyncPolicy;
use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => 'Request a pull of changed data from GLOBAL instance to local FMT instance.',
    'help'          => 'This action connects to the GLOBAL instance and pulls all changed data since last sync.',
    'params'        => [],
    'access' => [
        'visibility'        => 'protected'
        // #todo #temp - reverse to private after tests
        // 'visibility'        => 'private'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_URL_GLOBAL', 'FMT_API_INTERNAL_TOKEN'],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $orm] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'agency') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

// #memo - on local instances there is a single Instance object
$instance = Instance::search()->read(['uuid'])->first();

if(!$instance) {
    throw new Exception('no_instance_id', EQ_ERROR_INVALID_CONFIG);
}

// retrieve SyncPolicy related to 'protected' entities
$policies = SyncPolicy::search([
        ['scope', '=', 'protected'],
        ['sync_direction', '=', 'ascending']
    ])
    ->read([
        'object_class',
        'field_unique',
        'sync_policy_lines_ids' => ['object_field', 'scope']
    ]);

$result = [
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'errors'    => 0,
    'processed' => 0,
    'logs'      => []
];

$now = time();

$timestamp = Setting::get_value('fmt', 'system', 'sync.last_push_timestamp', 0);

foreach($policies as $id => $policy) {

    $entity = $policy['object_class'];

    // discard private fields
    $map_fields = [];

    foreach($policy['sync_policy_lines_ids'] as $policy_line_id => $policyLine) {
        $map_fields[$policyLine['object_field']] = $policyLine['object_field'];
    }

    // retrieve all fields of the requested entity
    $schema = $orm->getModel($entity)->getSchema();

    // we're only interested in scalar fields and many2one relations
    // #memo - if present, uuid is used on server side to match with existing object
    foreach($schema as $field => $def) {
        if(in_array($field, ['id', 'creator', 'modifier', 'created', 'modified', 'state', 'deleted'])) {
            unset($schema[$field]);
        }
        elseif(
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

    $objects = $entity::search(['modified', '>=', $timestamp])
        ->read(array_keys($schema))
        ->adapt('json')
        ->get(true);

    foreach($objects as $object) {

        if(!isset($object[$policy['field_unique']])) {
            ++$result['ignored'];
            $result['logs'][] = "Ignored entity {$entity} object [{$object['id']}] with no value for unique key field `{$policy['field_unique']}`.";
            continue;
        }

        try {
            $request = new HttpRequest('POST ' . rtrim(constant('FMT_API_URL_GLOBAL'), '/') . '/?do=fmt_sync_push-from-local');

            $request
                ->body([
                    'entity'            => $entity,
                    'values'            => $object,
                    'instance_uuid'     => $instance['uuid']
                ])
                ->header('Content-Type', 'application/json')
                ->header('Authorization', 'Bearer ' . constant('FMT_API_INTERNAL_TOKEN'));

            /** @var HttpResponse */
            $response = $request->send();
            $data = $response->body();
            $status = $response->getStatusCode();

            if($status != 200) {
                $out = str_replace('\n', '', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                throw new Exception("Error from GLOBAL instance: HTTP status $status: $out", EQ_ERROR_UNKNOWN);
            }

        }
        catch(Exception $e) {
            // force arbitrary update of `modified` field so that failing object is included in next sync loop
            $orm->update($entity, $object['id'], ['modified' => time()]);
            ++$result['errors'];
            $result['logs'][] = "Unable to push protected entity {$entity} to Global instance: " . $e->getMessage();
        }
    }

}

if($result['errors'] > 0) {
    throw new Exception(serialize($result), EQ_ERROR_UNKNOWN);
}

// store last_sync_timestamp
Setting::set_value('fmt', 'system', 'sync.last_push_timestamp', $now);

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
