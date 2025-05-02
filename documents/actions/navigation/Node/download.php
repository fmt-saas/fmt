<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
        'accept-origin' => '*'
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
