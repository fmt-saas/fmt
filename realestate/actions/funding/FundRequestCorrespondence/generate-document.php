<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\navigation\Node;
use realestate\funding\FundRequestCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Create a document for a given Fund Request Correspondence.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\funding\FundRequestCorrespondence',
            'description'      => 'Identifier of the Fund request correspondence.',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context                  $context
 */
['context' => $context] = $providers;


$fundRequestCorrespondence = FundRequestCorrespondence::id($params['id'])
    ->read(['status', 'condo_id', 'ownership_id', 'name'])
    ->first();

if(!$fundRequestCorrespondence) {
    throw new Exception("unknown_assembly_invitation", EQ_ERROR_UNKNOWN_OBJECT);
}


// retrieve FS Node relating to general meetings (assemblies)
$parentNode = Node::search([
        ['condo_id', '=', $fundRequestCorrespondence['condo_id'] ],
        ['node_type', '=', 'folder'],
        ['code', '=', 'operation_statements']
    ])
    ->first();

// generate document and add it to EDMS
$data = eQual::run('get', 'realestate_funding_FundRequestCorrespondence_render-pdf', ['id' => $fundRequestCorrespondence['id']]);

$document = Document::create([
        'name'          => 'Appel de fonds - ' . $fundRequestCorrespondence['name'],
        'data'          => $data,
        'condo_id'      => $fundRequestCorrespondence['condo_id']
    ])
    ->update([
        // place node in dedicated folder
        'parent_node_id'    => $parentNode['id'] ?? null,
        // make node private
        'ownership_id'      => $fundRequestCorrespondence['ownership_id']
    ])
    ->first();

if(!$document) {
    throw new Exception('document_creation_failed', EQ_ERROR_UNKNOWN);
}

// attach generated document to invitation
FundRequestCorrespondence::id($fundRequestCorrespondence['id'])->update(['document_id' => $document['id']]);


$context->httpResponse()
        ->status(201)
        ->send();
