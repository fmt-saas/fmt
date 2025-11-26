<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Mailbox;
use equal\http\HttpRequest;

[$params, $providers] = eQual::announce([
    'description'	=>	"Refresh the Outlook access token of a given Mailbox.",
    'params' 		=>	[
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'communication\email\Mailbox',
            'required'         => true
        ]
    ],
    'constants'     => [
        'BACKEND_URL',
        'MS_OUTLOOK_CLIENT_ID',
        'MS_OUTLOOK_CLIENT_SECRET'
    ],
    'access'        => [
        'visibility' => 'public'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth', 'orm']
]);


/**
 * @var equal\php\Context                   $context
 * @var equal\orm\ObjectManager             $om
 * @var equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;


/* ---------------------------------------------------------
    VALIDATION
--------------------------------------------------------- */

if (!isset($params['id'])) {
    throw new Exception("missing_id", EQ_ERROR_INVALID_PARAM);
}

$mailbox = Mailbox::id($params['id'])
    ->read(['status', 'auth_type', 'refresh_token', 'refresh_token_expiry'])
    ->first();

if (!$mailbox) {
    throw new Exception("unknown_mailbox", EQ_ERROR_INVALID_PARAM);
}

if ($mailbox['status'] !== 'validated') {
    throw new Exception("non_validated_mailbox", EQ_ERROR_INVALID_PARAM);
}

if ($mailbox['auth_type'] !== 'oauth') {
    throw new Exception("non_oauth_mailbox", EQ_ERROR_INVALID_PARAM);
}

if ($mailbox['refresh_token_expiry'] < time()) {
    throw new Exception("expired_refresh_token", EQ_ERROR_INVALID_PARAM);
}


/* ---------------------------------------------------------
    REFRESH TOKEN REQUEST (Microsoft Graph OAuth2)
--------------------------------------------------------- */

/**
 * IMPORTANT :
 * The refresh token MUST be sent to the SAME endpoint
 * it was issued from. Since your token was issued via
 * `/common/oauth2/v2.0/token`, we MUST also refresh via /common.
 */
$tokenUrl = "https://login.microsoftonline.com/common/oauth2/v2.0/token";

$body = [
    'grant_type'    => 'refresh_token',
    'client_id'     => constant('MS_OUTLOOK_CLIENT_ID'),
    'client_secret' => constant('MS_OUTLOOK_CLIENT_SECRET'),
    'refresh_token' => $mailbox['refresh_token'],

    // Microsoft requires scope even for refresh
    'scope'         => 'openid profile email offline_access User.Read Mail.ReadWrite IMAP.AccessAsUser.All'
];

$oauthRequest = new HttpRequest("POST $tokenUrl");

$response = $oauthRequest
    ->header('Content-Type', 'application/x-www-form-urlencoded')
    ->setBody($body)
    ->send();

$data = $response->body();
$status = $response->getStatusCode();

if ($status < 200 || $status >= 300) {
    trigger_error("Outlook OAuth refresh failed: " . json_encode($data), EQ_REPORT_ERROR);
    throw new Exception("refresh_token_failed", EQ_ERROR_INVALID_PARAM);
}


/* ---------------------------------------------------------
    UPDATE TOKENS IN DATABASE
--------------------------------------------------------- */

$updates = [
    'access_token'        => $data['access_token'],
    'access_token_expiry' => time() + $data['expires_in'],
];

/**
 * Microsoft returns a new refresh_token *only sometimes*.
 * When it does, we MUST update it.
 */
if(isset($data['refresh_token'])) {
    $updates['refresh_token'] = $data['refresh_token'];
    $updates['refresh_token_expiry'] = time() + (3600 * 24 * 90); // ~90 days
}

Mailbox::id($mailbox['id'])->update($updates);


/* ---------------------------------------------------------
    RESPONSE
--------------------------------------------------------- */

$context->httpResponse()
    ->status(204)
    ->send();
