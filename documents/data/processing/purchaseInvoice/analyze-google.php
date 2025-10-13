<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use equal\http\HttpRequest;

[$params, $providers] = eQual::announce([
    'description' => 'Envoie un document PDF à Google Document AI et retourne le résultat.',
    'params' => [
        'id' =>  [
            'description'       => 'Identifier of the document.',
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'required'          => true
        ]
    ],
    'response' => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8'
    ],
    'access' => [ 'visibility' => 'protected' ],
    'providers'     => ['context', 'report']
]);

['context' => $context, 'report' => $reporter] = $providers;

$document = Document::id($params['id'])
    ->read(['name', 'content_type'])
    ->first();

if(!$document) {
    throw new Exception('unknown_target_document', EQ_ERROR_UNKNOWN_OBJECT);
}

// check that content_type is supported by the API
$supported_content_types = [
        'application/pdf',
        'image/webp',
        'image/png',
        'image/jpg',
        'image/jpeg',
        'image/heic',
        'image/tiff',
        'image/tif'
    ];

if(!in_array($document['content_type'], $supported_content_types)) {
    throw new Exception('unsupported_document_type', EQ_ERROR_INVALID_PARAM);
}

// get raw binary data of the target document
$document_data = eQual::run('get', 'documents_document', ['id' => $params['id']]);

$data = eQual::run('get', 'documents_processing_google_token');
$token = $data['token'];


$url = "https://eu-documentai.googleapis.com/v1/projects/24230475119/locations/eu/processors/c5841be08501104e:process";


$request = new HttpRequest("POST {$url}");
$request
    ->header("Authorization", "Bearer {$token}")
    ->header("Content-Type", "application/json")
    ->body([
        'rawDocument' => [
            'content'  => base64_encode($document_data),
            'mimeType' => 'application/pdf'
        ]
    ]);

$response = $request->send();
$status = $response->getStatusCode();

if($status != 200) {
    trigger_error("APP::Document AI request failed with code $status, body: " . json_encode($response->body(), JSON_PRETTY_PRINT), EQ_REPORT_ERROR);
    throw new Exception('invalid_analysis_response', EQ_ERROR_UNKNOWN);
}

$context->httpResponse()
    ->body($response->body())
    ->status(200)
    ->send();
