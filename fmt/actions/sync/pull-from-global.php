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
    'params'        => [],
    'access' => [
        'visibility'        => 'private'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_INTERNAL_TOKEN', 'FMT_API_URL_GLOBAL'],
    'providers'     => ['context', 'orm', 'auth']
]);

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

$timestamp = Setting::get_value('fmt', 'system', 'sync.last_pull_timestamp', 0);
$date_from = date('c', $timestamp);

// #memo - on local instances there is a single Instance object
$instance = Instance::search()->read(['uuid'])->first();

if(!$instance || !isset($instance['uuid']) || empty($instance['uuid'])) {
    throw new Exception('unknown_instance_uuid', EQ_ERROR_UNKNOWN_OBJECT);
}

foreach($policies as $id => $policy) {
    $map_private_fields = [];

    foreach($policy['sync_policy_lines_ids'] as $policy_line_id => $policyLine) {
        if($policyLine['scope'] === 'private') {
            $map_private_fields[$policyLine['object_field']] = true;
        }
    }

    $entity = $policy['object_class'];

    try {
        $model = $orm->getModel($entity);
        if(!$model) {
            throw new Exception('unknown_entity', EQ_ERROR_INVALID_PARAM);
        }

        $schema = $model->getSchema();

        $request = new HttpRequest('GET ' . rtrim(constant('FMT_API_URL_GLOBAL'), '/') . '/?get=fmt_sync_pull-from-local' .
                '&entity=' . urlencode($policy['object_class']) .
                '&date_from=' . $date_from .
                '&instance_uuid=' . $instance['uuid']
            );

        $request
            ->header('Content-Type', 'application/json')
            ->header('Authorization', 'Bearer ' . constant('FMT_API_INTERNAL_TOKEN'));

        /** @var HttpResponse */
        $response = $request->send();
        $data = $response->body();

        foreach($data as $object) {
            // discard object that do not have a UUID (yet)
            if(!$object['uuid'] || empty($object['uuid'])) {
                continue;
            }

            $updateRequest = UpdateRequest::create([
                    'object_class'  => $policy['object_class'],
                    'request_date'  => time(),
                    'source_type'   => 'global',
                    'source_origin' => 'sync'
                ])
                ->first();

            // local search
            $localObject = $entity::search($object['uuid'])->first();
            if($localObject) {
                // update
                UpdateRequest::id($updateRequest['id'])
                    ->update(['object_id' => $localObject['id']]);

                foreach($object as $field => $value) {
                        UpdateRequestLine::create([
                            'update_request_id'         => $updateRequest['id'],
                            'object_field'              => $field,
                            'old_value'                 => $object[$field],
                            'new_value'                 => $value
                        ]);
                    }


                $result['logs'][] = "Requested update of object of entity {$entity} with id {$localObject['id']}: " . $e->getMessage();
                ++$result['updated'];
            }
            else {
                //create
                UpdateRequest::id($updateRequest['id'])
                    ->update(['is_new' => true]);

                foreach($object as $field => $value) {
                    UpdateRequestLine::create([
                        'update_request_id'         => $updateRequest['id'],
                        'object_field'              => $field,
                        'new_value'                 => $value
                    ]);
                    $is_empty = false;
                }

                $result['logs'][] = "Requested creation of new object of entity {$entity}: " . $e->getMessage();
                ++$result['created'];
            }
        }

    }
    catch(Exception $e) {
        ++$result['errors'];
        $result['logs'][] = "Unable to fetch protected entity {$entity} from Global instance: " . $e->getMessage();
    }
}

// store last_sync_timestamp
Setting::set_value('fmt', 'system', 'sync.last_sync_timestamp', $now);

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
