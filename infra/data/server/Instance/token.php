<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2024
    Licensed under GNU LGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\server\Instance;
use realestate\management\ManagingAgent;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Attempts to log a user in.",
    'help'	        =>	"This controller is meant to be used on the Global instance for providing secret API keys to `agency` instances.",
    'params' 		=>	[
        'id' => [
            'type'              => 'many2one',
            'description'       => "Managing agent id for which a token is requested.",
            'help'              => "The managing agent or 'Syndic', is in charge of the condominium, and can be a single person or an agency.",
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
    'providers'     => ['context', 'auth', 'orm'],
    'constants'     => ['BACKEND_URL', 'AUTH_ACCESS_TOKEN_VALIDITY', 'AUTH_TOKEN_HTTPS']
]);

/**
 * @var equal\php\Context                   $context
 * @var equal\orm\ObjectManager             $om
 * @var equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;


$instance = Instance::id($params['id'])->read(['user_id'])->first();

if(!$instance) {
    throw new Exception("unknown_instance", EQ_ERROR_UNKNOWN_OBJECT);
}

// generate a permanent JWT access token
$access_token = $auth->token($instance['id'], 0);

$context->httpResponse()
        ->body(['token' => $access_token])
        ->send();
