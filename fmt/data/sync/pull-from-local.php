<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use fmt\sync\SyncPolicy;
use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => 'Return an array of objects that have been updated since the given date (`date_from`).',
    'help'          => 'This controller is intended for GLOBAL instance and expected to generate a response to a `pull-from-global` request from a LOCAL instance.',
    'params'        => [
        'entity' => [
            'type'              => 'string',
            'required'          => true
        ],
        'date_from' => [
            'type'              => 'datetime',
            'required'          => true
        ],
        'instance_uuid' => [
            'type'              => 'string',
            'description'       => 'Instance for which the data are requested.',
            'required'          => true
        ]
    ],
    'access' => [
        // #memo - requests from instances are meant to be received with an Authorization token
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

// #memo - on local instances there is a single Instance object
$instance = Instance::search(['uuid', '=', $params['instance_uuid']])->first();

if(!$instance) {
    throw new Exception('unknown_instance_uuid', EQ_ERROR_UNKNOWN_OBJECT);
}

// retrieve SyncPolicy related to 'protected' & 'private' entities
$policy = SyncPolicy::search([
        ['object_class', '=', $params['entity']],
        ['scope', 'in', ['protected', 'private']],
        ['sync_direction', '=', 'descending']
    ])
    ->read([
        'scope',
        'object_class',
        'field_unique',
        'sync_policy_lines_ids' => ['object_field', 'scope']
    ])
    ->first();

if(!$policy) {
    throw new Exception('missing_policy', EQ_ERROR_INVALID_CONFIG);
}

$entity = $params['entity'];

// remember how to handle fields
$map_fields = [];
foreach($policy['sync_policy_lines_ids'] as $policy_line_id => $policyLine) {
    $map_fields[$policyLine['object_field']] = $policyLine['scope'];
}

// retrieve all fields of the requested entity
$schema = $orm->getModel($entity)->getSchema();

// we're only interested in scalar fields
$fields = ['uuid'];

$domain_data = [];
foreach($schema as $field => $def) {
    if($field === 'instance_id' && $policy['scope'] === 'protected') {
        $domain_data['instance_id'] = $instance['id'];
    }
    elseif($field === 'object_class') {
        $domain_data['object_class'] = $entity;
    }

    if(in_array($field, ['id', 'creator', 'modifier', 'created', 'modified', 'state', 'deleted'])) {
        continue;
    }
    elseif(
        (!isset($def['type']) || !in_array($def['type'], ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'many2one'])) &&
        (!isset($def['result_type']) || !in_array($def['result_type'], ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'many2one']))
    ) {
        continue;
    }
    elseif(!isset($map_fields[$field])) {
        continue;
    }
    else {
        $scope = $map_fields[$field];
        if($scope === 'private') {
            continue;
        }
    }

    $fields[] = $field;
}

$domain = new Domain();
if(isset($domain_data['instance_id'])) {
    $domain = new Domain([
        [
            ['instance_id', 'is', null]
        ],
        [
            ['instance_id', '<>', $domain_data['instance_id']]
        ],
        [
            ['instance_id', '=', $domain_data['instance_id']],
            ['created', '<>', 'object.modified'] // #todo - handle, because it is automatically removed from domain when ->toArray()
        ]
    ]);
}
if(isset($domain_data['object_class'])) {
    $domain->addCondition(new DomainCondition('object_class', '=', $domain_data['object_class']));
}

$domain->addCondition(new DomainCondition('modified', '>=', $params['date_from'] ?? 0));

$objects = $entity::search($domain->toArray())
    ->read($fields)
    ->adapt('json')
    ->get(true);

$context->httpResponse()
        ->body($objects)
        ->send();
