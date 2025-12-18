<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\navigation\Node;
use realestate\governance\AssemblyMinutesCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Create a document for a given assembly invitation.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\governance\AssemblyMinutesCorrespondence',
            'description'      => 'Identifier of the Assembly invitation.',
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


$assemblyMinutesCorrespondence = AssemblyMinutesCorrespondence::id($params['id'])
    ->read(['status', 'condo_id', 'ownership_id', 'name'])
    ->first();

if(!$assemblyMinutesCorrespondence) {
    throw new Exception("unknown_assembly_invitation", EQ_ERROR_UNKNOWN_OBJECT);
}


// retrieve FS Node relating to general meetings (assemblies)
$parentNode = Node::search([
        ['condo_id', '=', $assemblyMinutesCorrespondence['condo_id'] ],
        ['node_type', '=', 'folder'],
        ['code', '=', 'general_meetings']
    ])
    ->first();

// generate document and add it to EDMS
$data = eQual::run('get', 'realestate_governance_AssemblyMinutesCorrespondence_render-pdf', ['id' => $assemblyMinutesCorrespondence['id']]);

$document = Document::create([
        'name'          => 'Invitation Assemblée - ' . $assemblyMinutesCorrespondence['name'],
        'data'          => $data,
        'condo_id'      => $assemblyMinutesCorrespondence['condo_id'],
        'ownership_id'  => $assemblyMinutesCorrespondence['ownership_id']
    ])
    ->update(['parent_node_id' => $parentNode['id'] ?? null])
    ->first();

if(!$document) {
    throw new Exception('document_creation_failed', EQ_ERROR_UNKNOWN);
}

// attach generated document to invitation
AssemblyMinutesCorrespondence::id($assemblyMinutesCorrespondence['id'])->update(['document_id' => $document['id']]);


$context->httpResponse()
        ->status(201)
        ->send();
