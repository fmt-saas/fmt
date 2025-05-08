<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use documents\Document;
use equal\http\HttpRequest;

list($params, $providers) = announce([
    'description'   => 'Return raw data (with original MIME) of a document identified by given hash.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the document.',
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'required'          => true
        ],
        'disposition' => [
            'type'          => 'string',
            'selection'     => [
                'inline',
                'attachment'
            ],
            'default'       => 'inline'
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'accept-origin' => '*'
    ],
    'constants'     => ['FMT_API_URL_EDMS'],
    'providers'     => ['context', 'orm', 'auth', 'adapt']
]);

['context' => $context, 'orm' => $om, 'auth' => $auth, 'adapt' => $adapt] = $providers;

$user_id = $auth->userId();

// documents can be public : switch to root user to bypass any permission check
$auth->su();

// search for documents matching given hash code (should be only one match)
$collection = Document::id($params['id']);
$document = $collection->read(['uuid'])->first();

if(!$document) {
    throw new Exception("document_unknown", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$document['uuid']) {
    throw new Exception("invalid_document", QN_ERROR_UNKNOWN_OBJECT);
}

// pull document data from EDMS server

// #todo - il faut injecter le APP_TOKEN dans le header

$url = constant('FMT_API_URL_EDMS');
$request = new HttpRequest('GET '.$url.'?get=documents_pull&uuid=' . $document['uuid']);
$response = $request->send();

$result = $response->body();

if(!isset($result['data'], $result['name'], $result['content_type'])) {
    throw new Exception('invalid_response', EQ_ERROR_UNKNOWN);
}

if(strlen($result['data']) <= 0) {
    throw new Exception('empty_response', EQ_ERROR_UNKNOWN);
}

/** @var \equal\data\adapt\DataAdapter */
$adapter = $adapt->get('json');

$output = $adapter->adaptIn($result['data'], 'binary');

$context->httpResponse()
        ->header('Content-Disposition', $params['disposition'].'; filename="'.$result['name'].'"')
        ->header('Content-Type', $result['content_type'])
        ->body($output, true)
        ->send();
