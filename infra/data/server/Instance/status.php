<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\http\HttpRequest;
use infra\server\Instance;

list($params, $providers) = eQual::announce([
    'description'   => 'Fetches instant status of a given instance.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted instance',
            'type'              => 'many2one',
            'foreign_object'    => 'infra\server\Instance',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$instance = Instance::id($params['id'])
    ->read(['name', 'server_id' => ['b2_api_url', 'b2_api_password']])
    ->first();

if(empty($instance['server_id']['b2_api_url'])) {
    throw new Exception('missing_api_url', EQ_ERROR_INVALID_PARAM);
}

$request = new HttpRequest("GET {$instance['server_id']['b2_api_url']}/instance/status?scope=instant&instance={$instance['name']}");

$request->setHeader('Authorization', "Basic {$instance['server_id']['b2_api_password']}");

$response = $request->send();

$context
    ->httpResponse()
    ->body($response->body())
    ->send();
