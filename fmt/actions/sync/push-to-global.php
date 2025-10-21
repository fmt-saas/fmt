<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use equal\http\HttpRequest;
use fmt\setting\Setting;

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
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_URL_GLOBAL', 'FMT_INTERNAL_API_TOKEN'],
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

foreach($map_entities as $entity => $scope) {
    if($scope != 'protected') {
        continue;
    }

    // retrieve all fields of the requested entity
    $schema = $orm->getModel($entity)->getSchema();

    // we're only interested in scalar fields and many2one relations
    foreach($schema as $field => $def) {
        if(!in_array($def['type'], ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'many2one'])) {
            unset($schema[$field]);
        }
    }

    $objects = $entity::search(['modified', '>=', $timestamp])
        ->read(array_keys($schema))
        ->adapt('json')
        ->get(true);

    foreach($objects as $object) {
        try {
            $request = new HttpRequest('POST ' . rtrim(constant('FMT_API_URL_GLOBAL'), '/') . '/?do=fmt_sync_push-from-local');

            $request
                ->body([
                    'entity'    => $entity,
                    'values'    => $object
                ])
                ->header('Content-Type', 'application/json')
                ->header('Authorization', 'Bearer ' . constant('FMT_INTERNAL_API_TOKEN'));

            /** @var HttpResponse */
            $response = $request->send();
            $data = $response->body();
            $status = $response->getStatusCode();

            if($status != 200) {
                throw new Exception("unexpected error from GLOBAL instance: HTTP status $status", EQ_ERROR_UNKNOWN);
            }

            $res = $orm->update($entity, $object['id'], ['uuid' => $data['uuid']]);

            if($res <= 0) {
                throw new Exception("unable to set newly assigned UUID ({$data['uuid']}) to object ({$object['id']}) of class {$entity}", EQ_ERROR_UNKNOWN);
            }

        }
        catch(Exception $e) {
            ++$result['errors'];
            $result['logs'][] = "Unable to push protected entity {$entity} to Global instance: " . $e->getMessage();
        }
    }

}

// store last_sync_timestamp
Setting::set_value('fmt', 'system', 'sync.last_push_timestamp', $now);

$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
