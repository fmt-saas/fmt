<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2024
    Licensed under GNU LGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'	=>	"Generates an API token for the user linked to the given instance.",
    'help'	        =>	"This controller allows to provide secret API keys to `agency` instances if on `global`, to `global` instance if on `agency`.",
    'params' 		=>	[
        'id' => [
            'type'              => 'many2one',
            'description'       => "Instance id for which a token is requested.",
            'foreign_object'    => 'infra\server\Instance',
            'required'          => true
        ]
    ],
    'access'      => [
        'visibility' => 'private'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context', 'auth']
]);

/**
 * @var equal\php\Context                   $context
 * @var equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$instance = Instance::id($params['id'])
    ->read(['instance_type', 'user_id'])
    ->first();

if(!$instance) {
    throw new Exception('unknown_instance', EQ_ERROR_UNKNOWN_OBJECT);
}

// do not allow the creation of a token for an instance of the same type as the current one
if($instance['instance_type'] === constant('FMT_INSTANCE_TYPE')) {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

// generate a permanent JWT access token
$access_token = $auth->token($instance['user_id'], 0);

$context->httpResponse()
        ->body(['token' => $access_token])
        ->send();
