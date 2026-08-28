<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\DocumentType;
use documents\navigation\Node;
use realestate\property\transfer\OwnershipTransferSettlementCorrespondence;

[$params, $providers] = eQual::announce([
    'description' => 'Generate and store a seller or buyer settlement correspondence document.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementCorrespondence',
            'description'    => 'Settlement correspondence to generate.',
            'required'       => true
        ]
    ],
    'response'  => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context']
]);

['context' => $context] = $providers;

$correspondence = OwnershipTransferSettlementCorrespondence::id($params['id'])
    ->read([
        'condo_id',
        'recipient_role',
        'ownership_id' => ['code'],
        'owner_id',
        'document_id',
        'settlement_id' => ['id', 'name', 'ownership_transfer_id']
    ])
    ->first();

if(!$correspondence) {
    throw new Exception('unknown_settlement_correspondence', EQ_ERROR_UNKNOWN_OBJECT);
}

$document_id = $correspondence['document_id'];

if(!$document_id) {
    $sibling = OwnershipTransferSettlementCorrespondence::search([
            ['settlement_id', '=', $correspondence['settlement_id']['id']],
            ['recipient_role', '=', $correspondence['recipient_role']],
            ['ownership_id', '=', $correspondence['ownership_id']['id']],
            ['owner_id', '=', $correspondence['owner_id']],
            ['document_id', '<>', null]
        ])
        ->read(['document_id'])
        ->first();

    if($sibling) {
        $document_id = $sibling['document_id'];
    }
}

if(!$document_id) {
    $documentType = DocumentType::search(['code', '=', 'ownership_transfer_document'])
        ->read(['folder_code'])
        ->first();

    if(!$documentType) {
        throw new Exception('unknown_ownership_transfer_document_type', EQ_ERROR_INVALID_CONFIG);
    }

    $parentNode = Node::search([
            ['condo_id', '=', $correspondence['condo_id']],
            ['node_type', '=', 'folder'],
            ['code', '=', $documentType['folder_code']]
        ])
        ->first();

    $data = eQual::run(
        'get',
        'realestate_property_transfer_OwnershipTransferSettlementCorrespondence_render-pdf',
        ['id' => $correspondence['id']]
    );

    $role_label = $correspondence['recipient_role'] === 'seller' ? 'vendeur' : 'acquéreur';
    $document = Document::create([
            'name'                  => "Régularisation de mutation - {$role_label} - {$correspondence['ownership_id']['code']}",
            'data'                  => $data,
            'condo_id'              => $correspondence['condo_id'],
            'ownership_transfer_id' => $correspondence['settlement_id']['ownership_transfer_id'],
            'document_type_id'      => $documentType['id'],
            'document_visibility'   => 'ownership'
        ])
        ->update([
            'parent_node_id' => $parentNode['id'] ?? null,
            'ownership_id'   => $correspondence['ownership_id']['id'],
            'owner_id'       => $correspondence['owner_id']
        ])
        ->first();

    if(!$document) {
        throw new Exception('settlement_correspondence_document_creation_failed', EQ_ERROR_UNKNOWN);
    }

    $document_id = $document['id'];
}

OwnershipTransferSettlementCorrespondence::id($correspondence['id'])
    ->update(['document_id' => $document_id]);

$context->httpResponse()
    ->status(201)
    ->send();

