<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2024
    Licensed under GNU LGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'	=> "Generates an API token for the user linked to the given instance.",
    'help'	        => "This controller allows to provide secret API keys to `agency` instances if on `global`, to `global` instance if on `agency`.",
    'params' 		=> [
        'id' => [
            'type'              => 'many2one',
            'description'       => "Instance id for which a token is requested.",
            'foreign_object'    => 'infra\server\Instance',
            'required'          => true
        ],

        'save_in_description' => [
            'type'              => 'boolean',
            'description'       => "Save the access token in the instance's description for creation from interface. This allows the copy/paste of the access token and then its removal from the description.",
            'default'           => false
        ]
    ],
    'access'      => [
        'visibility'    => 'protected',
        'groups'        => ['admins']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
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

try {
    // #memo - creates a stored access token with no expiry
    $access_token = $auth->createAccessToken($instance['user_id']);
}
catch(Exception $e) {
    throw new Exception("Error while generating token: ".$e->getMessage(), EQ_ERROR_UNKNOWN);
}

if(isset($access_token) && $params['save_in_description']) {
    $instance = Instance::id($params['id'])
        ->read(['description'])
        ->first();

    $description = !empty($instance['description']) ? $instance['description'].PHP_EOL.PHP_EOL : '';
    $description .= 'Access token (TO REMOVE AFTER COPY): '.$access_token;

    Instance::id($params['id'])->update(['description' => $description]);
}

$context
    ->httpResponse()
    ->body(['token' => $access_token])
    ->send();
