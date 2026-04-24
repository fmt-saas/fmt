<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\DocumentType;
use equal\http\HttpRequest;
use finance\bank\Bank;
use fmt\setting\Setting;
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

/**
 * @param string $entity
 * @param array $entity_config
 * @param array $objects
 * @param array $agency_objects
 * @return array
 */
$check_entity = function($entity, $entity_config, $objects, $agency_objects) use(&$check_entity, $orm) {
    $logs = [];

    $unique_fields = $entity_config['unique_fields'];

    foreach($objects as $object) {
        $unique_fields_values = [];
        foreach($unique_fields as $unique_field) {
            $unique_fields_values[] = $object[$unique_field];
        }
        $unique_fields_values = implode(', ', $unique_fields_values);

        $is_object_initialized = false;
        foreach($agency_objects as $agency_object) {
            $unique_fields_matches = 0;
            foreach($unique_fields as $unique_field) {
                if($agency_object[$unique_field] === $object[$unique_field]) {
                    $unique_fields_matches++;
                }
            }

            $is_object_initialized = $unique_fields_matches === count($unique_fields);

            if($is_object_initialized) {
                foreach($entity_config['warning_fields'] as $warning_field) {
                    if($agency_object[$warning_field] !== $object[$warning_field]) {
                        $logs[] = "WARN - The object '$entity' with unique fields ".implode(', ', $unique_fields)." = '$unique_fields_values' has a different value for field '$warning_field' ('$agency_object[$warning_field]' != '$object[$warning_field])'.";
                    }
                }

                foreach($entity_config['error_fields'] as $warning_field) {
                    if($agency_object[$warning_field] !== $object[$warning_field]) {
                        $logs[] = "ERR - The object '$entity' with unique field ".implode(', ', $unique_fields)." = '$unique_fields_values' has a different field '$warning_field' ('$agency_object[$warning_field]' != '$object[$warning_field])'.";
                    }
                }

                if(!empty($entity_config['relations'])) {
                    $model = $orm->getModel($entity);
                    $schema = $model->getSchema();

                    foreach($entity_config['relations'] as $relation_field => $relation_entity_config) {
                        $logs = array_merge(
                            $logs,
                            $check_entity($schema[$relation_field]['foreign_object'], $relation_entity_config, $object[$relation_field], $agency_object[$relation_field])
                        );
                    }
                }

                break;
            }
        }

        if(!$is_object_initialized) {
            $logs[] = "ERR - The object '$entity' with unique field " . implode(', ', $unique_fields) . " = '$unique_fields_values' does not exist.";
        }
    }

    return $logs;
};

$check_config = file_get_contents(EQ_BASEDIR.'/packages/fmt/actions/sync/check-init-config.json');
if(!$check_config) {
    throw new Exception('check_config_file_missing', EQ_ERROR_INVALID_CONFIG);
}
$check_config = json_decode($check_config, true);
if(!is_array($check_config)) {
    throw new Exception('check_config_invalid', EQ_ERROR_INVALID_CONFIG);
}

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
    $domain = [];
    if($entity !== SyncPolicy::getType()) {
        $policy = SyncPolicy::search([
            ['object_class', '=', $entity],
            ['sync_direction', '=', 'descending']
        ])
            ->read(['sync_policy_conditions_ids' => ['operand', 'operator', 'value']])
            ->first();

        if(!empty($policy['sync_policy_conditions_ids'])) {
            foreach($policy['sync_policy_conditions_ids'] as $condition) {
                $domain[] = [$condition['operand'], $condition['operator'], $condition['value']];
            }
        }
    }

    $model = $orm->getModel($entity);
    $schema = $model->getSchema();

    $fields = array_keys($schema);
    foreach($entity_config['relations'] ?? [] as $relation_field => $relation_entity_config) {
        $relation_model = $orm->getModel($schema[$relation_field]['foreign_object']);
        $relation_schema = $relation_model->getSchema();

        $fields[$relation_field] = array_keys($relation_schema);
    }

    $objects = $model::search($domain)
        ->read($fields)
        ->adapt('json')
        ->get(true);

    $agency_objects = $data['entities'][$entity];

    $logs = array_merge(
        $logs,
        $check_entity($entity, $entity_config, $objects, $agency_objects)
    );
}

// 3) check main identity

if(!$data['main_identity']) {
    $logs[] = "ERR - Missing main 'identity\\Identity'.";
}
else {
    foreach($check_config['main_identity']['mandatory_values'] as $key => $value) {
        if($data['main_identity'][$key] !== $value) {
            $logs[] = "ERR - The main 'identity\\Identity' type is '{$data['main_identity']['type']}' instead of '$value'.";
        }
    }

    foreach($check_config['main_identity']['default_values'] as $field => $value) {
        if($data['main_identity'][$field] === $value) {
            $logs[] = "WARN - The main 'identity\\Identity' field '$field' still has the default value '$value'.";
        }
    }

    foreach($check_config['main_identity']['mandatory_relations'] as $field) {
        if(!$data['main_identity'][$field]) {
            $logs[] = "ERR - Missing main '$field'.";
        }
    }
}

// 4) role assignments

$mandatory_roles = $check_config['role_assignments'];
foreach($mandatory_roles as $mandatory_role) {
    $agency_ra = null;
    foreach($data['role_assignments'] as $ra) {
        if(is_null($ra['condo_id']) && $ra['role_code'] === $mandatory_role && $ra['employee_id']) {
            $agency_ra = $ra;
        }
    }

    if(!$agency_ra) {
        $logs[] = "ERR - Missing 'hr\\role\\RoleAssignment' for code '$mandatory_role'.";
    }
}

// 5) check settings

$settings = Setting::search()
    ->read(['name', 'code', 'package', 'section', 'is_sequence', 'type'])
    ->adapt('json')
    ->get(true);

// get settings not specific to condo
$settings = array_values(
    array_filter($settings, fn($setting) => !preg_match('/\d/', $setting['name']))
);

foreach($settings as $setting) {
    $agency_setting = null;
    foreach($data['settings'] as $set) {
        if($set['name'] === $setting['name'] && $set['code'] === $setting['code'] && $set['package'] === $setting['package'] && $set['section'] === $setting['section']) {
            $agency_setting = $set;
        }
    }

    if(!$agency_setting) {
        $logs[] = "ERR - Missing 'fmt\\setting\\Setting' {$setting['name']} (package: {$setting['package']}, section: {$setting['section']}, code: {$setting['code']}).";
    }
    else {
        if($agency_setting['is_sequence'] !== $setting['is_sequence']) {
            if($setting['is_sequence']) {
                $logs[] = "ERR - The object 'fmt\\setting\\Setting' {$setting['name']} should be a sequence.";
            }
            else {
                $logs[] = "ERR - The object 'fmt\\setting\\Setting' {$setting['name']} shouldn't be a sequence.";
            }
        }
        if($agency_setting['type'] !== $setting['type']) {
            $logs[] = "ERR - The object 'fmt\\setting\\Setting' {$setting['name']} should have the type {$setting['type']} (current type = {$agency_setting['type']}).";
        }
    }
}

if(!$handle_warnings) {
    $logs = array_filter($logs, fn($log) => !str_starts_with($log, 'WARN'));
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

