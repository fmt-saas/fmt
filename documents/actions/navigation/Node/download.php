<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\Document;
use documents\navigation\Node;

[$params, $providers]= eQual::announce([
    'description'   => 'Return raw data (with original MIME) of a document identified by given hash.',
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the node to download.',
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/octet-stream'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$node = Node::id($params['id'])->read(['document_id'])->first();

if(!$node) {
    throw new Exception('unknown_node', EQ_ERROR_UNKNOWN_OBJECT);
}

$document = Document::id($node['document_id'])->read(['name', 'extension', 'content_type'])->first();

if(!$document) {
    throw new Exception('unknown_document', EQ_ERROR_UNKNOWN_OBJECT);
}

$output = eQual::run('get', 'documents_document', ['id' => $document['id']]);

$context->httpResponse()
        ->status(202)
        ->header('Content-Disposition', 'attachment; filename="' . $document['name'] . '.' . $document['extension'] . '"')
        ->header('Content-Type', $document['content_type'])
        ->body($output, true)
        ->send();
