<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\http\HttpRequest;

[$params, $providers] = eQual::announce([
    'description'   => "Returns Google Translation API translations for the given contents.",
    'params'        => [
        'token' => [
            'type'              => 'string',
            'description'       => "Google API access token.",
            'required'          => true
        ],
        'contents' => [
            'type'              => 'array',
            'description'       => "Texts to translate.",
            'required'          => true
        ],
        'source_lang' => [
            'type'              => 'string',
            'description'       => "The source language code.",
            'required'          => true
        ],
        'target_lang' => [
            'type'              => 'string',
            'description'       => "The target language code.",
            'required'          => true
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
    'constants'     => ['GOOGLE_DOCAI_PROJECT_ID'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$project_id = constant('GOOGLE_DOCAI_PROJECT_ID');
$url = "https://translation.googleapis.com/v3/projects/{$project_id}/locations/global:translateText";

$request = new HttpRequest("POST {$url}");
$request
    ->header("Authorization", "Bearer {$params['token']}")
    ->header("Content-Type", "application/json")
    ->body([
        'contents'              => $params['contents'],
        'sourceLanguageCode'    => $params['source_lang'],
        'targetLanguageCode'    => $params['target_lang']
    ]);

$response = $request->send();
$status = $response->getStatusCode();

if($status != 200) {
    trigger_error("APP::Translation request failed with code $status, body: " . json_encode($response->body(), JSON_PRETTY_PRINT), EQ_REPORT_ERROR);
    throw new Exception('invalid_translation_response', EQ_ERROR_UNKNOWN);
}

$context
    ->httpResponse()
    ->body(json_decode($response->getBody(true), true))
    ->send();
