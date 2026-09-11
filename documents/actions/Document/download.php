<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\Document;

[$params, $providers]= eQual::announce([
    'description'   => 'Return raw data (with original MIME) of a document identified by given hash.',
    'help'          => 'This controller is meant to be used in Views for `documents\Document` offering a download action.',
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the document to download.',
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$document = Document::id($params['id'])->read(['name', 'hash', 'extension', 'content_type'])->first();

if(!$document) {
    throw new Exception('unknown_document', EQ_ERROR_UNKNOWN_OBJECT);
}

$output = eQual::run('get', 'documents_document', ['id' => $document['hash']]);

$document_name = trim((string) ($document['name'] ?? ''));

if(pathinfo($document_name, PATHINFO_EXTENSION) === '') {
    $extension = ltrim(trim((string) ($document['extension'] ?? '')), '.');

    if($extension !== '') {
        $document_name = rtrim($document_name, '.') . '.' . $extension;
    }
}

$context->httpResponse()
        ->header('Content-Disposition', 'attachment; filename="' . $document_name . '"')
        ->header('Content-Type', $document['content_type'])
        ->body($output, true)
        ->send();
