<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

[$params, $providers] = eQual::announce([
    'description'   => 'Return all objects relating to private entities modified since given date.',
    'params'        => [
        'date_from' => [
            'type'          => 'datetime',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_URL_EDMS', 'DEFAULT_LANG'],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $orm] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

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

$result = [];

foreach($map_entities as $entity => $scope) {
    if($scope !== 'private') {
        continue;
    }

    // retrieve all fields of the requested entity
    $model = $orm->getModel($entity);
    if(!$model) {
        throw new Exception('unknown_entity', EQ_ERROR_INVALID_PARAM);
    }

    $schema = $model->getSchema();

    // we're only interested in scalar fields and many2one relations
    foreach($schema as $field => $def) {
        if(!in_array($def['type'], ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'many2one'])) {
            unset($schema[$field]);
        }
    }

    $domain = [];

    if(isset($params['date_from'])) {
        $domain[] = ['modified', '>=', $params['date_from']];
    }

    $objects = $entity::search($domain)
        ->read(array_keys($schema))
        ->adapt('json')
        ->get(true);

    if(count($objects) > 0) {
        $item = (object) [
            'name' => $entity,
            'lang' => constant('DEFAULT_LANG'),
            'data' => $objects
        ];
    }

    $result[] = $item;
}

$context->httpResponse()
        ->body($result)
        ->send();
