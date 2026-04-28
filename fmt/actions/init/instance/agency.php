<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => "Initializes an agency instance, and synchronizes it with global instance.",
    'params'        => [
        'instance_uuid' => [
            'type'          => 'string',
            'description'   => "The UUID of the agency instance, it is generated on global instance."
        ],
        'sync' => [
            'type'          => 'boolean',
            'description'   => "Must the new agency instance be synchronized with global instance ?",
            'help'          => "Default true because it'll mostly be used with synchronisation.",
            'default'       => true
        ],
        'global_access_token' => [
            'type'          => 'string',
            'description'   => "If sync, the token to access global instance's API."
        ],
        'global_instance_url' => [
            'type'          => 'string',
            'description'   => "If sync, the url of the global instance's API."
        ]
    ],
    'access' => [
        'visibility'    => 'private'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
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

    if(empty($params['global_access_token'])) {
        throw new Exception('global_access_token_needed_to_sync_with_global', EQ_ERROR_NOT_ALLOWED);
    }
    
    if(empty($params['global_instance_url'])) {
        throw new Exception('global_instance_url_must_be_provided_to_sync_with_global', EQ_ERROR_INVALID_PARAM);
    }
}

if(constant('FMT_INSTANCE_TYPE') !== 'agency') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$map_init_packages = [
    'core'          => false,
    'identity'      => false,
    'communication' => false,
    'fmt'           => false
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

// init core
eQual::run('do', 'init_package', [
    'package'           => 'core',
    'ignore_platform'   => true,
    'force'             => !$map_init_packages['core']
]);

// init identity
eQual::run('do', 'init_package', [
    'package'           => 'identity',
    'import'            => true,
    'import_cascade'    => false,
    'ignore_platform'   => true,
    'force'             => !$map_init_packages['identity']
]);

# init communication
eQual::run('do', 'init_package', [
    'package'           => 'communication',
    'import'            => true,
    'import_cascade'    => false,
    'ignore_platform'   => true,
    'force'             => !$map_init_packages['communication']
]);

// init fmt (with data)
eQual::run('do', 'init_package', [
    'package'           => 'fmt',
    'import'            => true,
    'import_cascade'    => false,
    'ignore_platform'   => true
]);

// add fmt specific Collection and AccessController classes if they're missing from configuration
$config_json = file_get_contents(EQ_BASEDIR.'/config/config.json');
$config = json_decode($config_json, true);
if(!isset($config['SERVICE_ORM_COLLECTION_CLASS'], $config['SERVICE_ACCESS_ACCESSCONTROLLER'])) {
    $config['SERVICE_ORM_COLLECTION_CLASS'] = "fmt\\orm\\Collection";
    $config['SERVICE_ACCESS_ACCESSCONTROLLER'] = "fmt\\access\\AccessController";

    $new_config_json = json_encode($config);
    file_put_contents(EQ_BASEDIR.'/config/config.json', $new_config_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

if(!empty($params['instance_uuid'])) {
    // set uuid to instance
    Instance::id(1)->update(['uuid' => $params['instance_uuid']]);

    if($params['sync']) {
        $global_instance_name = parse_url($params['global_instance_url'], PHP_URL_HOST);

        $global_instance = Instance::create([
            'server_id'     => 1,
            'instance_type' => 'global',
            'name'          => $global_instance_name,
            'url'           => $params['global_instance_url'],
            'access_token'  => $params['global_access_token']
        ])
            ->do('create_user')
            ->first();

        // fetch the sync policies from global and overwrite the existing ones
        eQual::run('do', 'fmt_sync_SyncPolicy_pull-from-global', ['reset' => true]);

        // pull data from global depending on the sync policies
        eQual::run('do', 'fmt_sync_pull-from-global', ['accept' => true]);
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->send();
