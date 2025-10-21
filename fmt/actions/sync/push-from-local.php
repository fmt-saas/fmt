<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

[$params, $providers] = eQual::announce([
    'description'   => 'Request an update (or creation) of protected entities created on a LOCAL instance to GLOBAL instance.',
    'help'          => 'This action is expected to be called remotely from a LOCAL instance to the GLOBAL instance.',
    'params'        => [
        'entity' => [
            'type'              => 'string',
            'required'          => true
        ],
        'values' => [
            'type'              => 'array',
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

// #todo - depending on the entities, only certain fields may be allowed to be modified
// except in the case of a new object: but then it must be flagged for verification
$protected_fields = [
        // system fields
        'id', 'created', 'modified', 'creator', 'modifier', 'state', 'deleted',
        // fields related to meta about the source of the data
        'source', 'source_type', 'source_origin', 'source_date', 'source_verification_status'
    ];

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

$map_entities_keys = [
    // #memo - for convenience, citizen_identification (individuals only) is copied into registration_number
    'identity\Identity'                     => 'registration_number',
    'identity\User'                         => 'login',
    'purchase\supplier\Supplier'            => 'vat_number',
    'finance\bank\Bank'                     => 'bic',
    'realestate\property\NotaryOffice'      => 'registry_ref'
];

// #TODO - pouvoir fusionner deux élements protected
// créer des entités de synchronisation : 'en attente' -> valider avant synchro
// pouvoir définir les champs qui priment toujours sur la GLOBAL (pas de remontée depuis les LOCAL)


// pouvoir définir, pour chaque entité, les données accessibles au public et celles qui sont "protégées" / "non publiques"
//  entité FieldAccess, object_class, object_field, scope, reason (gdpr, ...)
// script pour envoyer un champ protégé vers une instance spécifique (pour l'entité concernée)

//  si elles sont en local, on ne les envoie pas
//  si elles sont en global, on ne les envoie pas
// en cas de transfert d'une copropriété vers un autre, on copie toutes les infos (sur validation du syndic sortant)


$entity = $params['entity'];

// retrieve all fields of the requested entity
$model = $orm->getModel($entity);
if(!$model) {
    throw new Exception('unknown_entity', EQ_ERROR_INVALID_PARAM);
}

// remove protected fields if present
foreach($values as $field) {
    if(in_array($field, $protected_fields)) {
        unset($values[$field]);
    }
}

$schema = $model->getSchema();

$uuid = null;


// if we have a UUID: search; if exists, update, otherwise error (UUIDs are issued by the master instance)
if(isset($params['values']['uuid'])) {
    $uuid = $params['values']['uuid'];
    $object = $entity::search(['uuid', '=', $uuid])->first();
    if(!$object) {
        throw new Exception('invalid_uuid', EQ_ERROR_INVALID_PARAM);
    }

    // update target object
    $entity::id($object['id'])->update(array_merge($values, [
            'source_verification_status' => 'pending'
        ]));
}
// if we don't have a UUID: in this case, check by other means whether the object already exists (depending on the class)
// if it exists, update it
// if it doesn't exist, create it
// in both cases, retrieve the UUID
else {
    $key = $map_entities_keys[$entity];
    $object = $entity::search([$key, '=', $values[$key]])
        ->read(['uuid'])
        ->first();

    if(!$object && $entity === 'identity\Identity') {

    }

    // manual verification will be required by default
    if(!$object) {
        // add values for sourcing / verification
        $object = $entity::create(array_merge($values, [
                'source_origin'              => 'instance',
                'source_verification_status' => 'pending',
                'source_date'                => time()
            ]))
            ->read(['uuid'])
            ->first();
    }
    else {
        $entity::id($object['id'])->update(array_merge($values, [
                'source_verification_status' => 'pending'
            ]));
    }
    $uuid = $object['uuid'];
}


$context
    ->httpResponse()
    ->status(200)
    ->body(['uuid' => $uuid])
    ->send();
