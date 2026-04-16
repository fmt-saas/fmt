<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

[$params, $providers] = eQual::announce([
    'description'   => "Init a global instance.",
    'params'        => [
        'demo' => [
            'type'          => 'boolean',
            'description'   => "Initialize FMT with demo data.",
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
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

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

if($params['demo']) {
    # init fmt demo data (optional)
    eQual::run('do', 'init_package', [
        'package'           => 'fmt',
        'import'            => false,
        'import_cascade'    => false,
        'demo'              => true,
        'ignore_platform'   => true,
        'force'             => true
    ]);
}

$context
    ->httpResponse()
    ->status(200)
    ->send();
