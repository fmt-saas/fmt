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
    'access' => [ 'visibility' => 'protected' ]
]);

['context' => $context, 'report' => $reporter] = $providers;

/*
$credentials = json_decode(file_get_contents($params['credentials_path']), true);
$privateKey  = $credentials['private_key'];
$clientEmail = $credentials['client_email'];
*/

$privateKey  = constant('GOOGLE_DOCAI_PRIVATE_KEY');
$clientEmail = constant('GOOGLE_DOCAI_CLIENT_EMAIL');

$header  = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
$time    = time();
$payload = base64_encode(json_encode([
    'iss'   => $clientEmail,
    'scope' => 'https://www.googleapis.com/auth/cloud-platform',
    'aud'   => 'https://oauth2.googleapis.com/token',
    'exp'   => $time + 3600,
    'iat'   => $time
]));

$base64UrlHeader  = rtrim(strtr($header, '+/', '-_'), '=');
$base64UrlPayload = rtrim(strtr($payload, '+/', '-_'), '=');

$data = $base64UrlHeader . "." . $base64UrlPayload;

openssl_sign($data, $signature, $privateKey, "sha256WithRSAEncryption");
$base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

$jwt = $data . "." . $base64UrlSignature;

$context->httpResponse()
    ->body([ 'jwt' => $jwt ])
    ->status(200)
    ->send();
