<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Mailbox;

[$params, $providers] = eQual::announce([
    'description'	=>	"Refresh the access token of a given Mailbox.",
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
            'usage'         => 'text/plain:500',
            'required'      => true
        ],
        'refresh_token' => [
            'type'          => 'string',
            'usage'         => 'text/plain:500',
            'required'      => true
        ],
        'access_token_expiry' => [
            'type'          => 'integer',
            'required'      => true

        ],
        'refresh_token_expiry' => [
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'constants'     => ['BACKEND_URL'],
    'access'        => [
        'visibility' => 'public'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm'],
    'constants'     => ['BACKEND_URL', 'AUTH_ACCESS_TOKEN_VALIDITY', 'AUTH_TOKEN_HTTPS', 'FMT_INSTANCE_TYPE']
]);


/**
 * @var equal\php\Context                   $context
 * @var equal\orm\ObjectManager             $om
 * @var equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;

$map_providers = [
    'google'     => 'imap.gmail.com',
    'microsoft'  => 'outlook.office365.com'
];

// attempt to retrieve a matching Mailbox
$mailbox = Mailbox::search([
        ['email', '=', $email],
        ['auth_type', '=', 'oauth'],
        ['status', '=', 'pending']
    ])
    ->first();

// if found, update it and mark it as validated
if($mailbox) {
    Mailbox::id($mailbox['id'])
        ->update([
            'access_token'          => $params['access_token'],
            'refresh_token'         => $params['refresh_token'],
            'access_token_expiry'   => $params['access_token_expiry'],
            'refresh_token_expiry'  => $params['refresh_token_expiry'],
            'imap_server'           => $map_providers[$params['provider']],
            'status'                => 'validated'
        ]);
}

$context->httpResponse()
        ->status(204)
        ->send();
