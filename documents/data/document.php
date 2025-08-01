<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\Document;
use equal\http\HttpRequest;

[$params, $providers] = eQual::announce([
    'description'   => 'Return raw data (with original MIME) of a document identified by given identifier.',
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
        'accept-origin' => '*',
        'content-type'  => 'application/octet-stream'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_URL_EDMS'],
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
    throw new Exception("document_unknown", EQ_ERROR_UNKNOWN_OBJECT);
}

if(constant('FMT_INSTANCE_TYPE') === 'edms' && !$document['uuid']) {
    throw new Exception("invalid_document", EQ_ERROR_UNKNOWN_OBJECT);
}

// pull document data from EDMS server
if($document['uuid']) {
    // #todo - inject APP_TOKEN in header
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

    $content_type = $result['content_type'];
    $filename = $result['name'];
    $output = $adapter->adaptIn($result['data'], 'binary');
}
// no UUID, fallback to data (this can occur when condo_id is still missing)
else {
    $document = $collection->read(['name', 'data', 'content_type'])->first();
    $content_type = $document['content_type'];
    $filename = $document['name'];
    $output = $document['data'];
}


$context->httpResponse()
        ->header('Content-Disposition', $params['disposition'].'; filename="'.$filename.'"')
        ->header('Content-Type', $content_type)
        ->body($output, true)
        ->send();
