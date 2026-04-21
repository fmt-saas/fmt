<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Mailbox;

[$params, $providers] = eQual::announce([
    'description'	=>	"Validate a given Mailbox.",
    'params' 		=>	[
        'email' => [
            'type'          => 'string',
            'required'      => true
        ],
        'provider' => [
            'type'          => 'string',
            'required'      => true
        ],
        'access_token' => [
            'type'          => 'string',
            'usage'         => 'text/plain:2000',
            'required'      => true
        ],
        'refresh_token' => [
            'type'          => 'string',
            'usage'         => 'text/plain:2000',
            'required'      => true
        ],
        'access_token_expiry' => [
            'type'          => 'integer',
            'usage'         => 'number/integer:10',
            'required'      => true
        ],
        'refresh_token_expiry' => [
            'type'          => 'integer',
            'usage'         => 'number/integer:10',
            'required'      => true
        ]
    ],
    'access'        => [
        'visibility' => 'public'
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

$map_providers = [
    'google'     => 'imap.gmail.com',
    'microsoft'  => 'imap.outlook.com'
];

// attempt to retrieve a matching Mailbox
$mailbox = Mailbox::search([
        ['email', '=', $params['email']],
        ['auth_type', '=', 'oauth'],
        ['status', '=', 'pending']
    ])
    ->first();

if(!$mailbox) {
    throw new Exception('unknown_mailbox', EQ_ERROR_INVALID_PARAM);
}

// update Mailbox and mark it as validated
Mailbox::id($mailbox['id'])
    ->update([
        'access_token'          => $params['access_token'],
        'refresh_token'         => $params['refresh_token'],
        'access_token_expiry'   => $params['access_token_expiry'],
        'refresh_token_expiry'  => $params['refresh_token_expiry'],
        'imap_server'           => $map_providers[$params['provider']],
        'status'                => 'validated'
    ]);

$context->httpResponse()
        ->status(204)
        ->send();
