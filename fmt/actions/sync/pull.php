<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use equal\http\HttpRequest;
use fmt\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => '.',
    'params'        => [],
    'access' => [
        'visibility'        => 'private'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_URL_GLOBAL'],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $orm] = $providers;


// retrouve last_sync_timestamp
// appelle pull-private
// appelle pull-protected pour chaque entity protected


// #memo #config #sync - sync between controllers
$map_entities = [
    'identity\Identity'                     => 'protected',
    'identity\User'                         => 'protected',
    'purchase\supplier\Supplier'            => 'protected',
    'purchase\supplier\SupplierType'        => 'private',
    'finance\bank\Bank'                     => 'private',
    'realestate\property\NotaryOffice'      => 'protected',
    'realestate\management\ManagingAgent'   => 'private',
    'realestate\property\Condominium'       => 'private',
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


$timestamp = Setting::get_value('fmt', 'system', 'sync.last_sync_timestamp', 0);
$date_from = date('c', $timestamp);

// pass-1 fetch all private entities
try {
    $request = new HttpRequest('GET ' . rtrim(constant('FMT_API_URL_GLOBAL'), '/') . '/?get=fmt_sync_pull-private');
    /** @var HttpResponse */
    $response = $request->send();

    $data = $response->body();

    foreach($data as $descriptor) {
        $entity = $descriptor['name'];
        $objects = $descriptor['data'];
        $model = $orm->getModel($params['entity']);
        if(!$model) {
            continue;
            // throw new Exception("unknown_entity", EQ_ERROR_INVALID_PARAM);
        }

        // local fields
        $local_fields = ['id', 'created', 'creator', 'modified', 'modifier'];

        foreach($objects as $object) {
            try {
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
            catch(Exception $e) {
                ++$result['errors'];
                $result['logs'][] = "Error processing object of entity {$entity} with UUID {$object['uuid']}: " . $e->getMessage();
                continue;
            }
        }
    }
}
catch(Exception $e) {
    ++$result['errors'];
    $result['logs'][] = "Unable to fetch private entities from Global instance: " . $e->getMessage();
}

// pass-2 fetch protected entities
foreach($map_entities as $entity => $scope) {
    if($scope != 'protected') {
        continue;
    }
    try {
        $request = new HttpRequest('GET ' . rtrim(constant('FMT_API_URL_GLOBAL'), '/') . '/?get=fmt_sync_pull-protected&entity=' . urlencode($entity) . '&date_from=' . $date_from);
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


$context
    ->httpResponse()
    ->status(200)
    ->body(['result' => $result])
    ->send();
