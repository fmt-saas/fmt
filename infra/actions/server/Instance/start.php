<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\User;
use equal\http\HttpRequest;
use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'       => "Start instance using the b2 API.",
    'params'            => [
        'id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'infra\server\Instance',
            'description'       => "Identifier of the targeted instance.",
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
    'constants'         => ['BACKEND_URL', 'FMT_INSTANCE_TYPE'],
    'providers'         => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$instance = Instance::id($params['id'])
    ->read(['name', 'server_id' => ['b2_api_url', 'b2_api_password']])
    ->first();

if(!$instance) {
    throw new Exception('unknown_instance', EQ_ERROR_INVALID_PARAM);
}

if(empty($instance['server_id']['b2_api_url']) || empty($instance['server_id']['b2_api_password'])) {
    throw new Exception('invalid_b2_conf', EQ_ERROR_INVALID_CONFIG);
}

$request = new HttpRequest("POST {$instance['server_id']['b2_api_url']}/instance/start", [], json_encode(['instance' => $instance['name']]));

$credentials = base64_encode("root:{$instance['server_id']['b2_api_password']}");

$request
    ->header('Content-Type', 'application/json')
    ->header('Authorization', "Basic $credentials");

$response = $request->send();

$status = $response->getStatusCode();

if($status < 200 || $status > 299) {
    throw new Exception('unable_to_start_b2_instance', EQ_ERROR_UNKNOWN);
}

Instance::id($instance['id'])->update(['up' => true, 'synced' => time()]);

$context
    ->httpResponse()
    ->status(204)
    ->send();
