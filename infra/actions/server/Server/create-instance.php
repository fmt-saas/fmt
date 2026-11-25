<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\data\DataGenerator;
use equal\http\HttpRequest;
use identity\User;
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
        'create_dns_record' => [
            'type'              => 'boolean',
            'description'       => "Ensure that a managed OVH DNS record exists for a client instance.",
            'default'           => true
        ],
        'sync' => [
            'type'              => 'boolean',
            'description'       => "Synchronize the instance with platform.",
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
            'default'           => 'required'
        ],
        'init' => [
            'type'              => 'boolean',
            'description'       => "Initialize the instance after creation.",
            'help'              => "The instance will take some minutes for the instance to be fully initialized.",
            'default'           => false
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
    'constants'         => [
        'BACKEND_URL',
        'FMT_INSTANCE_TYPE',
        'GOOGLE_SERVICE_ACCOUNT_PRIVATE_KEY',
        'GOOGLE_SERVICE_ACCOUNT_CLIENT_EMAIL',
        'GOOGLE_PROJECT_NUMBER',
        'GOOGLE_DOCUMENT_AI_PROCESSOR_ID',
        'GOOGLE_OAUTH_CLIENT_ID',
        'GOOGLE_OAUTH_CLIENT_SECRET',
        'MS_TENANT_ID',
        'MS_OUTLOOK_CLIENT_ID',
        'MS_OUTLOOK_CLIENT_SECRET'
    ],
    'providers'         => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\auth\AuthenticationManager $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

if(
    empty($params['instance_name'])
    || !filter_var($params['instance_name'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
) {
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

if(Instance::search(['name', '=', $params['instance_name']])->count() > 0) {
    throw new Exception('instance_already_exists', EQ_ERROR_INVALID_PARAM);
}

do {
    $instance_uuid = DataGenerator::uuid();
} while(Instance::search(['uuid', '=', $instance_uuid])->count() > 0);

$provisioning_user = null;

$cleanup_provisioning_user = function() use (&$provisioning_user): void {
    if(!$provisioning_user) {
        return;
    }

    try {
        User::id($provisioning_user['id'])->delete(true);
        $provisioning_user = null;
    }
    catch(\Throwable $e) {
        trigger_error(
            'APP::infra_server_Server_create-instance : unable to remove provisioning user: ' . $e->getMessage(),
            EQ_REPORT_ERROR
        );
    }
};

$create_params = [
    'USERNAME'              => $params['instance_name'],
    'PASSWORD'              => substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 14),
    'CIPHER_KEY'            => bin2hex(random_bytes(16)),
    'INSTANCE_TYPE'         => 'fmt',
    'INSTANCE_SUBTYPE'      => 'agency',
    'INSTANCE_UUID'         => $instance_uuid,
    'INIT'                  => $params['init'],
    'SYNC'                  => $params['sync'],
    'AUTH_SECRET_KEY'       => substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 20),
    'SECRETS'               => base64_encode(json_encode([
        'GOOGLE_SERVICE_ACCOUNT_PRIVATE_KEY'    => constant('GOOGLE_SERVICE_ACCOUNT_PRIVATE_KEY'),
        'GOOGLE_SERVICE_ACCOUNT_CLIENT_EMAIL'   => constant('GOOGLE_SERVICE_ACCOUNT_CLIENT_EMAIL'),
        'GOOGLE_PROJECT_NUMBER'                 => constant('GOOGLE_PROJECT_NUMBER'),
        'GOOGLE_DOCUMENT_AI_PROCESSOR_ID'       => constant('GOOGLE_DOCUMENT_AI_PROCESSOR_ID'),
        'GOOGLE_OAUTH_CLIENT_ID'                => constant('GOOGLE_OAUTH_CLIENT_ID'),
        'GOOGLE_OAUTH_CLIENT_SECRET'            => constant('GOOGLE_OAUTH_CLIENT_SECRET'),
        'MS_TENANT_ID'                          => constant('MS_TENANT_ID'),
        'MS_OUTLOOK_CLIENT_ID'                  => constant('MS_OUTLOOK_CLIENT_ID'),
        'MS_OUTLOOK_CLIENT_SECRET'              => constant('MS_OUTLOOK_CLIENT_SECRET')
    ]))
];

if($params['sync']) {
    try {
        $domain = parse_url(constant('BACKEND_URL'), PHP_URL_HOST);

        if(!$domain) {
            throw new \Exception('invalid_backend_url');
        }

        $current_user_id = $auth->userId();
        $auth->su();
        try {
            $provisioning_user = User::create([
                    'login'         => $params['instance_name'] . '@' . $domain,
                    'allow_auth'    => false,
                    'validated'     => true,
                    'is_system'     => true
                ])
                ->first();
        }
        finally {
            $auth->su($current_user_id);
        }

        if(!$provisioning_user) {
            throw new \Exception('unable_to_create_provisioning_user');
        }

        $access_token = $auth->createAccessToken($provisioning_user['id']);

        if(!$access_token) {
            throw new \Exception('unable_to_create_access_token');
        }
    }
    catch(\Throwable $e) {
        trigger_error(
            'APP::infra_server_Server_create-instance : unable to prepare synchronization credentials: ' . $e->getMessage(),
            EQ_REPORT_ERROR
        );
        $cleanup_provisioning_user();
        throw new Exception('unable_to_prepare_instance_sync', EQ_ERROR_UNKNOWN);
    }

    $create_params['SYNC_LEVEL'] = $params['sync_level'];
    $create_params['GLOBAL_ACCESS_TOKEN'] = $access_token;
    $create_params['GLOBAL_URL'] = constant('BACKEND_URL');
}

$request = new HttpRequest("POST {$server['b2_api_url']}/instance/create", [], json_encode($create_params));

$credentials = base64_encode("root:{$server['b2_api_password']}");

$request
    ->header('Content-Type', 'application/json')
    ->header('Authorization', "Basic $credentials");

try {
    $response = $request->send();
}
catch(\Throwable $e) {
    trigger_error(
        'APP::infra_server_Server_create-instance : b2 instance creation request failed: ' . $e->getMessage(),
        EQ_REPORT_ERROR
    );
    $cleanup_provisioning_user();
    throw new Exception('unable_to_create_b2_instance', EQ_ERROR_UNKNOWN);
}

$status = $response->getStatusCode();

if($status < 200 || $status > 299) {
    trigger_error(
        "APP::infra_server_Server_create-instance : b2 instance creation failed with HTTP status {$status}.",
        EQ_REPORT_ERROR
    );
    $cleanup_provisioning_user();
    throw new Exception('unable_to_create_b2_instance', EQ_ERROR_UNKNOWN);
}

try {
    $instance_values = [
        'server_id'     => $server['id'],
        'instance_type' => 'agency',
        'name'          => $params['instance_name'],
        'uuid'          => $instance_uuid,
        'url'           => "https://{$params['instance_name']}"
    ];

    if($provisioning_user) {
        $instance_values['user_id'] = $provisioning_user['id'];
    }

    $instance = Instance::create($instance_values)->first();

    if(!$instance) {
        throw new \Exception('unable_to_persist_instance');
    }
}
catch(\Throwable $e) {
    trigger_error(
        'APP::infra_server_Server_create-instance : unable to persist created b2 instance: ' . $e->getMessage(),
        EQ_REPORT_ERROR
    );

    throw new Exception('unable_to_persist_instance', EQ_ERROR_UNKNOWN);
}

if($params['create_dns_record']) {
    try {
        eQual::run('do', 'infra_server_Instance_create-dns-record', [
            'id' => $instance['id']
        ]);
    }
    catch(\Throwable $e) {
        trigger_error(
            'APP::infra_server_Server_create-instance : unable to create DNS record: ' . $e->getMessage(),
            EQ_REPORT_ERROR
        );
        throw new Exception('unable_to_create_dns_record', EQ_ERROR_UNKNOWN);
    }
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
