<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => 'Request a document analysis using Mindee.com service, and return the a result as a JSON descriptor.',
    'params'        => [
        'document_id' =>  [
            'description'   => 'Identifier of the document to parse.',
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$document = Document::id($params['document_id'])->read(['content_type', 'data'])->first();

if(!$document) {
    throw new Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
}

$supported_content_types = [
        'text/plain',
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

if(!in_array($document['content_type'], $supported_content_types)) {
    throw new Exception('non_supported_document_type', EQ_ERROR_INVALID_PARAM);
}

$data = $document['data'];

switch($document['content_type']) {
    case 'text/plain':
        $result = eQual::run('get', 'finance_bank_BankStatement_parse-coda', ['data' => base64_encode($data)]);
        break;
    case 'application/vnd.ms-excel':
    case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
        $result = eQual::run('get', 'finance_bank_BankStatement_parse-xls', ['data' => base64_encode($data)]);
        break;
}

$context->httpResponse()
        ->body($result)
        ->send();
