<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\DocumentSubtype;
use documents\DocumentType;
use equal\http\HttpRequest;
use finance\bank\Bank;
use fmt\sync\SyncPolicy;
use identity\IdentityType;
use infra\server\Instance;
use purchase\supplier\SupplierType;
use realestate\property\NotaryOffice;

[$params, $providers] = eQual::announce([
    'description'   => 'Check that a given agency instance is correctly initialized.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'infra\server\Instance',
            'description'       => 'Identifier of the instance that needs to be checked.',
            'required'          => true
        ],
        'log_level' => [
            'type'              => 'string',
            'selection'         => [
                'warning',
                'error'
            ],
            'description'       => 'If warning: warnings and errors are logged. If error: only errors are logged.',
            'default'           => 'warning'
        ]
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_INTERNAL_TOKENS'],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$instance = Instance::id($params['id'])
    ->read(['name', 'url'])
    ->first();

if(!$instance) {
    throw new Exception('instance_not_found', EQ_ERROR_UNKNOWN_OBJECT);
}

$map_instances_tokens = constant('FMT_API_INTERNAL_TOKENS') ?? [];
if(empty($map_instances_tokens[$instance['name']])) {
    throw new Exception('missing_agency_api_token', EQ_ERROR_INVALID_CONFIG);
}

$request = new HttpRequest('GET ' . rtrim($instance['url'], '/') . '/?get=fmt_sync_init-data');

$request
    ->header('Content-Type', 'application/json')
    ->header('Authorization', 'Bearer ' . $map_instances_tokens[$instance['name']]);

/** @var \equal\http\HttpResponse $response */
$response = $request->send();

if($response->getStatusCode() !== 200) {
    throw new Exception('data_fetching_error', EQ_ERROR_CONFLICT_OBJECT);
}

$data = $response->body();

// #memo - Key 'error_fields' means that if value for that field is not the same then an error is added
// #memo - Key 'warning_fields' means that if value for that field is not the same then a warning is added

$check_config = [
    'packages' => [
        'core', 'identity', 'documents', 'finance', 'purchase', 'realestate',
        'tracking', 'communication', 'hr', 'sale', 'stats', 'infra', 'fmt'
    ],
    'entities' => [
        DocumentType::getType() => [
            'unique_field'   => 'code',
            'error_fields'   => [],
            'warning_fields' => ['name']
        ],
        DocumentSubtype::getType() => [
            'unique_field'   => 'code',
            'error_fields'   => [],
            'warning_fields' => ['name']
        ],
        SupplierType::getType() => [
            'unique_field'   => 'code',
            'error_fields'   => [],
            'warning_fields' => ['name']
        ],
        IdentityType::getType() => [
            'unique_field'   => 'code',
            'error_fields'   => [],
            'warning_fields' => ['name']
        ],
        Bank::getType() => [
            'unique_field'   => 'bic',
            'error_fields'   => ['bank_account_iban', 'bank_country'],
            'warning_fields' => ['name']
        ],
        NotaryOffice::getType() => [
            'unique_field'   => 'registration_number',
            'error_fields'   => [],
            'warning_fields' => ['name']
        ]
    ],
];

$logs = [];

$handle_warnings = $params['log_level'] === 'warning';

// 1) check packages

foreach($check_config['packages'] as $package) {
    if(!isset($data['packages'][$package])) {
        $logs[] = "ERR - The package '$package' isn't initialized.";
    }
}

// 2) check entities

foreach($check_config['entities'] as $entity => $entity_config) {
    $unique_field = $entity_config['unique_field'];

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

    $objects = $model::search($domain)->read(array_keys($schema))->get(true);
    foreach($objects as $object) {
        $is_object_initialized = false;
        foreach($data['entities'][$entity] as $agency_object) {
            if($agency_object[$unique_field] === $object[$unique_field]) {
                $is_object_initialized = true;

                if($handle_warnings) {
                    foreach($entity_config['warning_fields'] as $warning_field) {
                        if($agency_object[$warning_field] !== $object[$warning_field]) {
                            $logs[] = "WARN - The object '$entity' with unique field $unique_field = '$object[$unique_field]' has a different value for field '$warning_field' ('$agency_object[$warning_field]' != '$object[$warning_field])'.";
                        }
                    }
                }

                foreach($entity_config['error_fields'] as $warning_field) {
                    if($agency_object[$warning_field] !== $object[$warning_field]) {
                        $logs[] = "ERR - The object '$entity' with unique field $unique_field = '$object[$unique_field]' has a different field '$warning_field' ('$agency_object[$warning_field]' != '$object[$warning_field])'.";
                    }
                }

                break;
            }
        }

        if(!$is_object_initialized) {
            $logs[] = "ERR - The object '$entity' with unique field $unique_field = '$object[$unique_field]' does not exist.";
        }
    }
}

$result = [
    'warnings'  => count(array_filter($logs, fn($log) => str_starts_with($log, 'WARN'))),
    'errors'    => count(array_filter($logs, fn($log) => str_starts_with($log, 'ERR'))),
    'logs'      => $logs
];

if(!$handle_warnings) {
    unset($result['warnings']);
}

$context
    ->httpResponse()
    ->body($result)
    ->send();

