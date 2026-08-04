<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\auth\JWT;
use equal\http\HttpRequest;

[$params, $providers] = eQual::announce([
    'description'   => "Returns a given object currently stored translations depending on the linked condominium languages configuration.",
    'params' => [
        'id' => [
            'type'              => 'integer',
            'description'       => "The id of the concerned object.",
            'required'          => true
        ],
        'entity' => [
            'type'              => 'string',
            'description'       => "Name of the entity we want the translations for.",
            'required'          => true
        ],
        'field' => [
            'type'              => 'string',
            'description'       => "Optional parameter, the field we want the translations for.",
            'help'              => "If empty all entity multilang fields returned."
        ],
        'source_lang' => [
            'type'              => 'string',
            'description'       => "The language from which we want the translations.",
            'help'              => "If empty all condominium languages returned.",
            'default'           => constant('DEFAULT_LANG')
        ],
        'target_lang' => [
            'type'              => 'string',
            'description'       => "Optional parameter, the language for which we want the translation.",
            'help'              => "If empty all condominium languages handle."
        ]
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'UTF-8',
        'accept-origin' => '*'
    ],
    'constants'     => ['DEFAULT_LANG', 'GOOGLE_DOCAI_PRIVATE_KEY', 'GOOGLE_DOCAI_CLIENT_EMAIL', 'GOOGLE_DOCAI_PROJECT_ID'],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

// #todo - create a reusable get ctrl to get google token (see the one for document AI)
$fetchToken = function($private_key, $client_email) {
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

    return $response->body();
};

$translate = function($project_id, $token, $contents, $source, $target) {
    $url = "https://translation.googleapis.com/v3/projects/{$project_id}/locations/global:translateText";

    $request = new HttpRequest("POST {$url}");
    $request
        ->header("Authorization", "Bearer {$token}")
        ->header("Content-Type", "application/json")
        ->body([
            'contents'              => [$contents],
            'sourceLanguageCode'    => $source,
            'targetLanguageCode'    => $target
        ]);

    $response = $request->send();
    $status = $response->getStatusCode();

    if($status != 200) {
        trigger_error("APP::Translation request failed with code $status, body: " . json_encode($response->body(), JSON_PRETTY_PRINT), EQ_REPORT_ERROR);
        throw new Exception('invalid_translation_response', EQ_ERROR_UNKNOWN);
    }

    return json_decode($response->getBody(true), true);
};

// #memo - key is expected to be provided as a PEM string, with \n for new lines (as in Google JSON credentials file)
// #todo - create private key and client for translation API
$private_key  = str_replace("\\n", "\n", constant('GOOGLE_DOCAI_PRIVATE_KEY'));
$client_email = constant('GOOGLE_DOCAI_CLIENT_EMAIL');

// #todo - create project for translation API
$project_id = constant('GOOGLE_DOCAI_PROJECT_ID');

$data = $fetchToken($private_key, $client_email);
$token = $data['access_token'];

$current_values_params = [
    'id'        => $params['id'],
    'entity'    => $params['entity']
];
if(isset($params['field'])) {
    $current_values_params['field'] = $params['field'];
}

$current_values = eQual::run('get', 'fmt_translations_current-values', $current_values_params);

$source_lang_values = [];
foreach($current_values[$params['source_lang']] as $field => $value) {
    if(!empty($value)) {
        $source_lang_values[$field] = $value;
    }
}

$result = [];
foreach($current_values as $lang => $values) {
    if($lang === $params['source_lang']) {
        continue;
    }

    $missing_values = [];
    foreach($source_lang_values as $field => $value) {
        if(empty($values[$field])) {
            $missing_values[$field] = $value;
        }
    }

    if(empty($missing_values)) {
        continue;
    }

    $data = $translate($project_id, $token, array_values($missing_values), $params['source_lang'], $lang);

    $result[$lang] = [];
    foreach($data['translations'] as $index => $translation_res) {
        $field = array_keys($missing_values)[$index];

        $result[$lang][$field] = $translation_res['translatedText'];
    }
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
