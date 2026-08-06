<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\auth\JWT;
use equal\http\HttpRequest;

[$params, $providers] = eQual::announce([
    'description'   => "Returns a Google Translation API access token.",
    'params'        => [],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'UTF-8',
        'accept-origin' => '*'
    ],
    'constants'     => ['GOOGLE_DOCAI_PRIVATE_KEY', 'GOOGLE_DOCAI_CLIENT_EMAIL'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

// #memo - key is expected to be provided as a PEM string, with \n for new lines (as in Google JSON credentials file)
$private_key  = str_replace("\\n", "\n", constant('GOOGLE_DOCAI_PRIVATE_KEY'));
$client_email = constant('GOOGLE_DOCAI_CLIENT_EMAIL');

$time = time();

$payload = [
    'iss'   => $client_email,
    'scope' => 'https://www.googleapis.com/auth/cloud-translation',
    'aud'   => 'https://oauth2.googleapis.com/token',
    'exp'   => $time + 3600,
    'iat'   => $time
];

try {
    $jwt = JWT::encode($payload, $private_key, 'RS256');
}
catch(Exception $e) {
    throw new Exception('jwt_generation_failed', EQ_ERROR_UNKNOWN);
}

$request = new HttpRequest('POST https://oauth2.googleapis.com/token');
$request
    ->header('Content-Type', 'application/x-www-form-urlencoded')
    ->body([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt
    ]);

$response = $request->send();
$status = $response->getStatusCode();

if($status != 200) {
    trigger_error("APP::Token request failed with code $status", EQ_REPORT_ERROR);
    throw new Exception('error_obtaining_token', EQ_ERROR_UNKNOWN);
}

$data = $response->body();

if(empty($data['access_token'])) {
    throw new Exception('invalid_token_response', EQ_ERROR_UNKNOWN);
}

$context
    ->httpResponse()
    ->body(['token' => $data['access_token']])
    ->send();
