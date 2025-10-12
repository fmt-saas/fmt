<?php
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

/*
$credentials = json_decode(file_get_contents($params['credentials_path']), true);
$privateKey  = $credentials['private_key'];
$clientEmail = $credentials['client_email'];
*/

$privateKey  = str_replace("\\n", "\n", constant('GOOGLE_DOCAI_PRIVATE_KEY'));
$clientEmail = constant('GOOGLE_DOCAI_CLIENT_EMAIL');

$time = time();

$header = ['alg' => 'RS256', 'typ' => 'JWT'];
$payload = [
    'iss'   => $clientEmail,
    'scope' => 'https://www.googleapis.com/auth/cloud-platform',
    'aud'   => 'https://oauth2.googleapis.com/token',
    'exp'   => $time + 3600,
    'iat'   => $time
];

// encodage direct en base64url du JSON
$base64UrlHeader  = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
$base64UrlPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

$data = $base64UrlHeader . '.' . $base64UrlPayload;

// signature
openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

$base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
$jwt = $data . '.' . $base64UrlSignature;

$context->httpResponse()
    ->body([ 'jwt' => $jwt ])
    ->status(200)
    ->send();
