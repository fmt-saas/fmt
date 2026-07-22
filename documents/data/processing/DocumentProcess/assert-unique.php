<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\processing\DocumentProcess;

[$params, $providers] = eQual::announce([
    'description'   => "Validate consistency and completeness of a purchase invoice.",
    'help'          => "This controller is meant to be called as a result of a ValidationRule verification.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The invoice to validate.",
            'foreign_object'    => 'documents\processing\DocumentProcess',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;


$documentProcess = DocumentProcess::id($params['id'])
    ->read([
        'status',
        'document_id' => ['hash_sha256']
    ])
    ->first();

if(!$documentProcess) {
    throw new Exception("unknown_document_process", EQ_ERROR_UNKNOWN_OBJECT);
}


$existingDocument = Document::search([
        ['id', '<>', $documentProcess['document_id']['id']],
        ['hash_sha256', '=', $documentProcess['document_id']['hash_sha256']]
    ])
    ->read(['document_process_id' => ['status']])
    ->first();

if($existingDocument
    && isset($existingDocument['document_process_id']['status'])
    && !in_array($existingDocument['document_process_id']['status'], ['cancelled', 'removed'])
) {
    $dispatch->dispatch('documents.import.duplicate_document', 'documents\processing\DocumentProcess', $params['id'], 'important');
    throw new Exception("duplicate_document", EQ_ERROR_INVALID_PARAM);
}

// a 2xx response mean validation was successful, in all other cases, an Exception is raised
$context->httpResponse()
        ->status(204)
        ->send();
