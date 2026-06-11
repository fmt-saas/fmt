<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\http\HttpRequest;
use infra\server\Server;

list($params, $providers) = eQual::announce([
    'description'   => 'Fetches instant status of a given server.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted server',
            'type'              => 'many2one',
            'foreign_object'    => 'infra\server\Server',
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

$server = Server::id($params['id'])
    ->read(['b2_api_url'])
    ->first();

if(empty($server['b2_api_url'])) {
    throw new Exception('missing_api_url', EQ_ERROR_INVALID_PARAM);
}

$response = (new HttpRequest("GET {$server['b2_api_url']}/status?scope=instant"))->send();

$context
    ->httpResponse()
    ->body($response->body())
    ->send();
