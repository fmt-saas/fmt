<?php

use equal\auth\JWT;
use equal\http\HttpResponse;

[$params, $providers] = eQual::announce([
    'description' => 'Génère un JWT signé à partir des credentials Google.',
    'params' => [],
    'constants' => ['GOOGLE_DOCAI_PRIVATE_KEY', 'GOOGLE_DOCAI_CLIENT_EMAIL'],
    'response' => [
        'content-type' => 'application/json',
        'charset' => 'utf-8'
    ],
    'access' => [ 'visibility' => 'protected' ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$private_key  = str_replace("\\n", "\n", constant('GOOGLE_DOCAI_PRIVATE_KEY'));
$client_email = constant('GOOGLE_DOCAI_CLIENT_EMAIL');

$payload = [
    'iss'   => $client_email,
    'scope' => 'https://www.googleapis.com/auth/cloud-platform',
    'aud'   => 'https://accounts.google.com/o/oauth2/token',
    'exp'   => time() + 3600,
    'iat'   => time()
];

try {
    $jwt = JWT::encode($payload, $private_key, 'RS256');
}
catch(Exception $e) {
    throw new Exception('jwt_generation_failed', EQ_ERROR_UNKNOWN);
}

$context->httpResponse()
    ->body([ 'jwt' => $jwt ])
    ->status(200)
    ->send();
