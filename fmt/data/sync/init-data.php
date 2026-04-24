<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\DocumentSubtype;
use documents\DocumentType;
use finance\bank\Bank;
use fmt\sync\SyncPolicy;
use identity\IdentityType;
use purchase\supplier\SupplierType;
use realestate\property\NotaryOffice;

[$params, $providers] = eQual::announce([
    'description'   => 'Return initialization data, for global to verify if the agency instance is correctly initialized.',
    'params'        => [
    ],
    'access' => [
        // #memo - requests from instances are meant to be received with an Authorization token
        'visibility'    => 'protected'
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

$entities_config = [
    SyncPolicy::getType() => [
        'relations' => [
            'sync_policy_lines_ids',
            'sync_policy_conditions_ids'
        ]
    ],
    DocumentType::getType() => [
        'relations' => [
            'document_subtypes_ids'
        ]
    ],
    SupplierType::getType() => [],
    IdentityType::getType() => [],
    Bank::getType() => [],
    NotaryOffice::getType() => []
];

$result = [
    'packages' => [],
    'entities' => []
];

$packages = file_get_contents(EQ_LOG_STORAGE_DIR.'/packages.json');
if($packages) {
    $result['packages'] = json_decode($packages, true);
}

foreach($entities_config as $entity => $config) {
    $policy = SyncPolicy::search([
        ['object_class', '=', $entity],
        ['sync_direction', '=', 'descending']
    ])
        ->read(['sync_policy_conditions_ids' => ['operand', 'operator', 'value']])
        ->first();

    $domain = [];
    if(!empty($policy['sync_policy_conditions_ids'])) {
        foreach($policy['sync_policy_conditions_ids'] as $condition) {
            $domain[] = [$condition['operand'], $condition['operator'], $condition['value']];
        }
    }

    $model = $orm->getModel($entity);
    $schema = $model->getSchema();

    $fields = array_keys($schema);
    foreach($config['relations'] ?? [] as $relation) {
        $relation_model = $orm->getModel($schema[$relation]['foreign_object']);
        $relation_schema = $relation_model->getSchema();

        $fields[$relation] = array_keys($relation_schema);
    }

    $result['entities'][$entity] = $model::search($domain)->read($fields)->adapt('json')->get(true);
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
