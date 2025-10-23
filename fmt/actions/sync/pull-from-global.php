<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use equal\http\HttpRequest;
use fmt\setting\Setting;
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

// #memo #config #sync - sync between controllers
$map_entities = [
    'identity\Identity'                     => 'protected',
    'identity\User'                         => 'protected',
    'purchase\supplier\Supplier'            => 'protected',
    'purchase\supplier\SupplierType'        => 'private',
    'finance\bank\Bank'                     => 'protected',
    'realestate\property\NotaryOffice'      => 'protected',
    'realestate\management\ManagingAgent'   => 'protected',
    'realestate\property\Condominium'       => 'protected',
    'documents\DocumentType'                => 'private',
    'documents\DocumentSubtype'             => 'private'
];


// For some entities, import and synchronize everything that exists on the GLOBAL instance.
// For others, only synchronize what exists locally, without retrieving all data
$map_entities_scopes = [
    'identity\Identity'                     => 'local',
    'identity\User'                         => 'local',
    'purchase\supplier\Supplier'            => 'global',
    'purchase\supplier\SupplierType'        => 'global',
    'finance\bank\Bank'                     => 'global',
    'realestate\property\NotaryOffice'      => 'global',
    'realestate\management\ManagingAgent'   => 'local',
    'realestate\property\Condominium'       => 'local',
    'documents\DocumentType'                => 'global',
    'documents\DocumentSubtype'             => 'global'
];


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

if(!$instance) {
    throw new Exception('unknown_instance_uuid', EQ_ERROR_UNKNOWN_OBJECT);
}

foreach($map_entities as $entity => $scope) {
    try {
        $request = new HttpRequest('GET ' . rtrim(constant('FMT_API_URL_GLOBAL'), '/') . '/?get=fmt_sync_pull-from-local' .
                '&entity=' . urlencode($entity) .
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
            // remove local fields
            foreach($local_fields as $field) {
                if(isset($object[$field])) {
                    unset($object[$field]);
                }
            }
            // local search
            $localObject = $entity::search($object['uuid'])->first();
            if($localObject) {
                // update
                $entity::id($localObject['id'])->update($object);
                $result['logs'][] = "Update object of entity {$entity} with UUID {$localObject['id']}: " . $e->getMessage();
                ++$result['updated'];
            }
            else {
                //create
                $localObject = $entity::create($object)->first();
                $result['logs'][] = "Created object of entity {$entity} with UUID {$localObject['id']}: " . $e->getMessage();
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
