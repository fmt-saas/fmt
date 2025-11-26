<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Mailbox;
use equal\auth\JWT;
use equal\http\HttpRequest;
use infra\server\Instance;

// announce script and fetch parameters values
[$params, $providers] = eQual::announce([
    'description'   => "Callback for receiving confirmation from Microsoft Outlook OAuth.",
    'help'          => "Script called after OAuth redirect from Microsoft.",
    'params'        => [
        'code' => [
            'type'          => 'string',
            'usage'         => 'text/plain:3000',
            'required'      => true
        ],
        'state' => [
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'constants'     => [
        'BACKEND_URL',
        'MS_TENANT_ID',
        'MS_OUTLOOK_CLIENT_ID',
        'MS_OUTLOOK_CLIENT_SECRET',
        'AUTH_ACCESS_TOKEN_VALIDITY'
    ],
    'access'        => [
        'visibility' => 'public'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm']
]);

['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;


/* --------------------------------------------------------------------------
   1) Exchange authorization code for tokens (Microsoft OAuth)
   -------------------------------------------------------------------------- */

$tenant = constant('MS_TENANT_ID');
$tokenUrl = "https://login.microsoftonline.com/$tenant/oauth2/v2.0/token";

$oauthRequest = new HttpRequest('POST ' . $tokenUrl);

$response = $oauthRequest
    ->header('Content-Type', 'application/x-www-form-urlencoded')
    ->setBody([
        'grant_type'    => 'authorization_code',
        'code'          => $params['code'],
        'redirect_uri'  => constant('BACKEND_URL') . '/oauth/outlook',
        'client_id'     => constant('MS_OUTLOOK_CLIENT_ID'),
        'client_secret' => constant('MS_OUTLOOK_CLIENT_SECRET'),
        // mandatory for MS token exchange
        'scope'         => 'openid profile offline_access https://graph.microsoft.com/User.Read https://graph.microsoft.com/IMAP.AccessAsUser.All'
    ])
    ->send();

$data = $response->body();
$status = $response->getStatusCode();

if ($status < 200 || $status > 299) {
    $context->httpResponse()
        ->status($status)
        ->body($data)
        ->send();
    exit;
}


/* --------------------------------------------------------------------------
   2) Retrieve user email
   -------------------------------------------------------------------------- */

$email = null;

// If id_token is present → decode email (openid flow)
if(!empty($data['id_token'])) {
    $identity_jwt = JWT::decode($data['id_token']);
    $email = $identity_jwt['payload']['preferred_username'] ?? null;
}

// Otherwise call Graph API /me
if(!$email) {
    $meRequest = new HttpRequest('GET https://graph.microsoft.com/v1.0/me');
    $meResponse = $meRequest
        ->header('Authorization', 'Bearer ' . $data['access_token'])
        ->send();
    $me = $meResponse->body();
    $email = $me['mail'] ?? $me['userPrincipalName'] ?? null;
}

if (!$email) {
    $context->httpResponse()
        ->status(400)
        ->body(['error' => 'Unable to determine user email.'])
        ->send();
}


/* --------------------------------------------------------------------------
   3) Identify instance to validate mailbox
   -------------------------------------------------------------------------- */

$origin_url = $params['state'];
$domain = parse_url($origin_url, PHP_URL_HOST);

$instance = Instance::search(['name', '=', $domain])->first();

if ($instance) {
    $data['email'] = $email;
    $data['provider'] = 'outlook';
    $data['access_token_expiry'] = time() + $data['expires_in'];
    // Microsoft exposes refresh token validity differently; fallback 90 days
    $data['refresh_token_expiry'] = time() + (constant('AUTH_ACCESS_TOKEN_VALIDITY') * 5);

    $validationRequest = new HttpRequest('POST https://' . $domain . '/?do=communication_email_Mailbox_validate');
    $response = $validationRequest
        ->header('Content-Type', 'application/json')
        ->setBody($data)
        ->send();
}


/* --------------------------------------------------------------------------
   4) Update Mailbox in eQual if exists
   -------------------------------------------------------------------------- */

$mailbox = Mailbox::search([
        ['email', '=', $email],
        ['auth_type', '=', 'oauth'],
        ['status', '=', 'pending']
    ])
    ->first();

if ($mailbox) {
    Mailbox::id($mailbox['id'])->update([
        'access_token'          => $data['access_token'],
        'refresh_token'         => $data['refresh_token'],
        'access_token_expiry'   => time() + $data['expires_in'],
        'refresh_token_expiry'  => time() + (constant('AUTH_ACCESS_TOKEN_VALIDITY') * 5),
        'imap_server'           => 'imap.outlook.com',
        'status'                => 'validated'
    ]);
}

$context->httpResponse()
    ->status(204)
    ->send();
