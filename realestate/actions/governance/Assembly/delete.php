<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use documents\Document;
use documents\navigation\Node;
use realestate\governance\Assembly;

[$params, $providers] = eQual::announce([
    'description'   => "Delete a given selection of Assembly objects, along with their related documents and document nodes, if their status allows deletion.",
    'params'        => [
        'ids' =>  [
            'type'              => 'one2many',
            'description'       => "List of assembly ids to delete.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch', 'auth']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 * @var equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'dispatch' => $dispatch, 'auth' => $auth] = $providers;

// ensure assembly objects exist and are readable
$document_fields = [
    'register_document_id',
    'signed_register_document_id',
    'minutes_document_id',
    'signed_minutes_document_id'
];

$assemblies = Assembly::ids($params['ids'])
    ->read(array_merge(['status', 'documents_ids'], $document_fields));

$map_documents_ids = [];

foreach($assemblies as $id => $assembly) {
    if(!in_array($assembly['status'], ['pending', 'published'], true)) {
        throw new Exception('cannot_remove_assembly_with_sending_in_progress', EQ_ERROR_INVALID_PARAM);
    }

    foreach($assembly['documents_ids'] ?? [] as $document_id) {
        $map_documents_ids[$document_id] = true;
    }

    foreach($document_fields as $field) {
        if($assembly[$field] ?? null) {
            $map_documents_ids[$assembly[$field]] = true;
        }
    }
}

$user_id = $auth->userId();

try {
    // Documents linked to an active assembly cannot be deleted, so remove assemblies first.
    $assemblies->delete(true);

    $auth->su();

    $documents_ids = array_keys($map_documents_ids);
    $nodes_ids = [];

    if(count($documents_ids)) {
        $nodes_ids = Node::search(['document_id', 'in', $documents_ids])->ids();
    }

    if(count($nodes_ids)) {
        Node::ids($nodes_ids)->delete(true);
    }

    if(count($documents_ids)) {
        Document::ids($documents_ids)->delete(true);
    }
}
finally {
    $auth->su($user_id);
}

$context->httpResponse()
    ->status(204)
    ->send();
