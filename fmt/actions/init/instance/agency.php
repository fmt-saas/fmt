<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => "Init a global instance.",
    'params'        => [
        'instance_uuid' => [
            'type'          => 'string',
            'description'   => "The UUID of the agency instance, it is generated on global instance."
        ],
        'sync' => [
            'type'          => 'boolean',
            'description'   => "Must the new agency instance be synchronized with global instance ?",
            'default'       => false
        ]
    ],
    'access' => [
        'visibility'    => 'private'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_INTERNAL_TOKEN'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

if($params['sync']) {
    if(empty($params['instance_uuid'])) {
        throw new Exception('uuid_must_be_provided_to_sync_with_global', EQ_ERROR_INVALID_PARAM);
    }

    if(empty(constant('FMT_API_INTERNAL_TOKEN'))) {
        throw new Exception('fmt_api_internal_token_config_needed_to_sync_with_global', EQ_ERROR_NOT_ALLOWED);
    }
}

if(constant('FMT_INSTANCE_TYPE') !== 'agency') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}
// test that api conf is set

$map_init_packages = [
    'core'      => false,
    'identity'  => false,
    'fmt'       => false
];
if(file_exists(EQ_BASEDIR."/log/packages.json")) {
    $json = file_get_contents(EQ_BASEDIR."/log/packages.json");
    $packages = json_decode($json, true);
    foreach($map_init_packages as $package => $value) {
        $map_init_packages[$package] = isset($packages[$package]);
    }
}

if($map_init_packages['fmt']) {
    throw new Exception('fmt_already_initialized', EQ_ERROR_NOT_ALLOWED);
}

# init core
eQual::run('do', 'init_package', [
    'package'           => 'core',
    'ignore_platform'   => true,
    'force'             => !$map_init_packages['core']
]);

# init identity
eQual::run('do', 'init_package', [
    'package'           => 'identity',
    'import'            => true,
    'import_cascade'    => false,
    'ignore_platform'   => true,
    'force'             => !$map_init_packages['identity']
]);

# init fmt (with data)
eQual::run('do', 'init_package', [
    'package'           => 'fmt',
    'import'            => true,
    'import_cascade'    => false,
    'ignore_platform'   => true
]);

if(!empty($params['instance_uuid'])) {
    Instance::id(1)->update(['uuid' => $params['instance_uuid']]);

    if($params['sync']) {
        eQual::run('do', 'fmt_sync_SyncPolicy_pull-from-global');
        eQual::run('do', 'fmt_sync_pull-from-global', ['accept' => true]);
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->send();
