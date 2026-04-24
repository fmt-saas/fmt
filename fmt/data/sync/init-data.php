<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\DocumentSubtype;
use documents\DocumentType;
use finance\bank\Bank;
use fmt\setting\Setting;
use fmt\sync\SyncPolicy;
use hr\role\RoleAssignment;
use identity\Identity;
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
    'entities' => [],
    'main_identity' => [],
    'role_assignments' => [],
    'settings' => []
];

// packages

$packages = file_get_contents(EQ_LOG_STORAGE_DIR.'/packages.json');
if($packages) {
    $result['packages'] = json_decode($packages, true);
}

// entities

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

// identity

$main_identity = Identity::id(1)
    ->read([
        'legal_name',
        'short_name',
        'registration_number',
        'has_parent',
        'address_street',
        'vat_number',
        'is_active',
        'type_id',
        'type',
        'organisation_id',
        'managing_agent_id'
    ])
    ->adapt('json')
    ->first(true);

if($main_identity) {
    $result['main_identity']  = $main_identity;
}

// role assignments

$role_assignments = RoleAssignment::search(['condo_id', '=', null])
    ->read(['condo_id', 'role_code', 'employee_id' => ['name']])
    ->adapt('json')
    ->get(true);

if(!empty($role_assignments)) {
    $result['role_assignments'] = $role_assignments;
}

// settings

$settings = Setting::search()
    ->read(['name', 'code', 'package', 'section', 'is_sequence', 'type', 'string'])
    ->adapt('json')
    ->get(true);

// get settings not specific to condo
$settings = array_values(
    array_filter($settings, fn($setting) => !preg_match('/\d/', $setting['name']))
);

if(!empty($settings)) {
    $result['settings'] = $settings;
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
