<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use realestate\governance\Assembly;

[$params, $providers] = eQual::announce([
    'description'   => 'Retrieve the PDF version of the Assembly Minutes for a given Assembly.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific Assembly to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$assembly = Assembly::id($params['id'])
    ->read(['status', 'step', 'minutes_document_id' => ['signed_document_id']])
    ->first();

if(!$assembly) {
    throw new Exception('unknown_assembly', EQ_ERROR_UNKNOWN_OBJECT);
}

// depending on the status of the Assembly, we fetch either the draft (signable) or the signed version
$document_id = $assembly['minutes_document_id']['id'];

if(in_array($assembly['status'], ['held', 'adjourned'], true)) {
    $document_id = $assembly['minutes_document_id']['signed_document_id'];
}

try {
    $output = eQual::run('get', 'documents_document', ['id' => $document_id]);
}
catch(Exception $e) {
    trigger_error('APP::Error while retrieving minutes document: ' . $e->getMessage(), EQ_ERROR_INVALID_CONFIG);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
