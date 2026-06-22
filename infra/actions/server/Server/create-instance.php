<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\http\HttpRequest;
use infra\server\Instance;
use infra\server\Server;

[$params, $providers] = eQual::announce([
    'description'       => "Create a new agency instance using the b2 API. This script is expected to be run on the global instance.",
    'params'            => [
        'id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'infra\server\Server',
            'description'       => "Identifier of the targeted server",
            'required'          => true
        ],
        'instance_name' => [
            'type'              => 'string',
            'description'       => "Name of the new instance to create.",
            'required'          => true
        ],
        'sync' => [
            'type'              => 'boolean',
            'description'       => "Synchronize the instance with platform.",
            'required'          => true,
            'default'           => false
        ],
        'sync_level' => [
            'type'              => 'string',
            'description'       => "Synchronization level.",
            'selection'         => [
                'required',
                'recommended',
                'optional',
                'demo'
            ],
            'required'          => true,
            'default'           => 'required'
        ],
        'init' => [
            'type'              => 'boolean',
            'description'       => "Initialize the instance after creation.",
            'help'              => "The instance will take some minutes for the instance to be fully initialized.",
            'default'           => false,
            'required'          => true
        ]
    ],
    'access'            => [
        'visibility'        => 'protected'
    ],
    'response'          => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'constants'         => ['BACKEND_URL', 'FMT_INSTANCE_TYPE', 'GOOGLE_DOCAI_PRIVATE_KEY', 'GOOGLE_DOCAI_CLIENT_EMAIL', 'GOOGLE_DOCAI_PROJECT_ID', 'GOOGLE_DOCAI_PROCESSOR_ID', 'GOOGLE_GMAIL_CLIENT_ID', 'GOOGLE_GMAIL_CLIENT_SECRET', 'MS_TENANT_ID', 'MS_OUTLOOK_CLIENT_ID', 'MS_OUTLOOK_CLIENT_SECRET'],
    'providers'         => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

if(empty($params['instance_name'])) {
    throw new Exception('invalid_name', EQ_ERROR_INVALID_PARAM);
}

$server = Server::id($params['id'])
    ->read(['b2_api_url', 'b2_api_password'])
    ->first();

if(!$server) {
    throw new Exception('unknown_server', EQ_ERROR_INVALID_PARAM);
}

if(empty($server['b2_api_url']) || empty($server['b2_api_password'])) {
    throw new Exception('invalid_b2_conf', EQ_ERROR_INVALID_CONFIG);
}

$instance = Instance::create([
    'server_id'     => $server['id'],
    'instance_type' => 'agency',
    'name'          => $params['instance_name'],
    'url'           => "https://{$params['instance_name']}"
])
    ->read(['uuid'])
    ->first();

$create_params = [
    'USERNAME'              => $params['instance_name'],
    'PASSWORD'              => substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 14),
    'CIPHER_KEY'            => bin2hex(random_bytes(16)),
    'INSTANCE_TYPE'         => 'fmt',
    'INSTANCE_SUBTYPE'      => 'agency',
    'INSTANCE_UUID'         => $instance['uuid'],
    'INIT'                  => $params['init'],
    'SYNC'                  => $params['sync'],
    'AUTH_SECRET_KEY'       => substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 20),
    'SECRETS'               => base64_encode(json_encode([
        'GOOGLE_DOCAI_PRIVATE_KEY'  => constant('GOOGLE_DOCAI_PRIVATE_KEY'),
        'GOOGLE_DOCAI_CLIENT_EMAIL' => constant('GOOGLE_DOCAI_CLIENT_EMAIL'),
        'GOOGLE_DOCAI_PROJECT_ID'   => constant('GOOGLE_DOCAI_PROJECT_ID'),
        'GOOGLE_DOCAI_PROCESSOR_ID' => constant('GOOGLE_DOCAI_PROCESSOR_ID'),
        'GOOGLE_GMAIL_CLIENT_ID'    => constant('GOOGLE_GMAIL_CLIENT_ID'),
        'GOOGLE_GMAIL_CLIENT_SECRET'=> constant('GOOGLE_GMAIL_CLIENT_SECRET'),
        'MS_TENANT_ID'              => constant('MS_TENANT_ID'),
        'MS_OUTLOOK_CLIENT_ID'      => constant('MS_OUTLOOK_CLIENT_ID'),
        'MS_OUTLOOK_CLIENT_SECRET'  => constant('MS_OUTLOOK_CLIENT_SECRET')
    ]))
];

if($params['sync']) {
    $token_res = eQual::run('do', 'infra_server_Instance_token', [
        'id' => $instance['id']
    ]);

    $create_params['SYNC_LEVEL'] = $params['sync_level'];
    $create_params['GLOBAL_ACCESS_TOKEN'] = $token_res['token'];
    $create_params['GLOBAL_URL'] = constant('BACKEND_URL');
}

$request = new HttpRequest("POST {$server['b2_api_url']}/instance/create", [], json_encode($create_params));

$credentials = base64_encode("root:{$server['b2_api_password']}");

$request
    ->header('Content-Type', 'application/json')
    ->header('Authorization', "Basic $credentials");

$response = $request->send();

$context
    ->httpResponse()
    ->status(204)
    ->send();
