<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\sync\SyncPolicy;

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
        ],
        'instance_uuid' => [
            'type'              => 'string',
            'description'       => 'Instance for which the data are requested.',
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
        ['object_class', '=', $entity]
    ])
    ->read([
        'object_class',
        'field_unique',
        'sync_policy_lines_ids' => ['sync_direction', 'object_field', 'scope']
    ])
    ->first();

// #todo - pouvoir fusionner deux élements protected
// créer des entités de synchronisation : 'en attente' -> valider avant synchro
// pouvoir définir les champs qui priment toujours sur la GLOBAL (pas de remontée depuis les LOCAL)


//  si elles sont en local, on ne les envoie pas
//  si elles sont en global, on ne les envoie pas
// en cas de transfert d'une copropriété vers un autre, on copie toutes les infos (sur validation du syndic sortant)

$map_private_fields = [];

foreach($policy['sync_policy_lines_ids'] as $policy_line_id => $policyLine) {
    if($policyLine['sync_direction'] !== 'ascending') {
        continue;
    }
    if($policyLine['scope'] === 'private') {
        $map_private_fields[$policyLine['object_field']] = true;
    }
}

$values = $params['values'];

// discard non relevant or private fields
foreach($schema as $field => $def) {
    if(!isset($values[$field])) {
        continue;
    }
    if(!in_array($def['type'], ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'many2one'])) {
        unset($values[$field]);
    }
    elseif(isset($map_private_fields[$field])) {
        unset($values[$field]);
    }
}

$uuid = null;

// if we have a UUID: search; if exists, update, otherwise error (UUIDs are issued by the master instance)
if(isset($params['values']['uuid'])) {
    $uuid = $params['values']['uuid'];
    $object = $entity::search(['uuid', '=', $uuid])->first();
    if(!$object) {
        throw new Exception('invalid_uuid', EQ_ERROR_INVALID_PARAM);
    }
    // #todo - create UpdateRequest for followup
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
    $key = $policy['field_unique'];

    if(!isset($values[$key])) {
        throw new Exception('missing_unique_field', EQ_ERROR_INVALID_PARAM);
    }

    $object = $entity::search([$key, '=', $values[$key]])
        ->read(['uuid'])
        ->first();

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
