<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\DocumentType;
use documents\DocumentSubtype;
use documents\navigation\Node;
use realestate\funding\ExpenseStatementCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Create a document for a given expense statement correspondence.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\funding\ExpenseStatementCorrespondence',
            'description'      => 'Identifier of the Expense Statement correspondence.',
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

$document_id = null;

$expenseStatementCorrespondence = ExpenseStatementCorrespondence::id($params['id'])
    ->read(['status', 'condo_id', 'document_id', 'ownership_id', 'owner_id', 'expense_statement_id', 'name'])
    ->first();

if(!$expenseStatementCorrespondence) {
    throw new Exception("unknown_expense_statement_correspondence", EQ_ERROR_UNKNOWN_OBJECT);
}

if($expenseStatementCorrespondence['document_id']) {
    throw new Exception("document_already_generated", EQ_ERROR_UNKNOWN_OBJECT);
}

$siblingExpenseStatementCorrespondence = ExpenseStatementCorrespondence::search([
        ['condo_id', '=', $expenseStatementCorrespondence['condo_id']],
        ['assembly_id', '=', $expenseStatementCorrespondence['assembly_id']],
        ['ownership_id', '=', $expenseStatementCorrespondence['ownership_id']],
        ['owner_id', '=', $expenseStatementCorrespondence['owner_id']],
        ['document_id', '<>', null]
    ])
    ->read(['document_id'])
    ->first();

if($siblingExpenseStatementCorrespondence) {
    $document_id = $siblingExpenseStatementCorrespondence['document_id'];
}


if(!$document_id) {

    // generate document and add it to EDMS
    $data = eQual::run('get', 'realestate_funding_ExpenseStatementCorrespondence_render-pdf', ['id' => $expenseStatementCorrespondence['id']]);

    $documentType = DocumentType::search(['code', '=', 'expense_statement'])
        ->read(['folder_code', 'visibility'])
        ->first();

    // retrieve FS Node relating to expense statements
    $parentNode = Node::search([
            ['condo_id', '=', $expenseStatementCorrespondence['condo_id'] ],
            ['node_type', '=', 'folder'],
            ['code', '=', $documentType['folder_code'] ?? 'operation_statements']
        ])
        ->first();

    $document = Document::create([
            'name'                  => 'Décompte de charges - ' . $expenseStatementCorrespondence['name'],
            'data'                  => $data,
            'condo_id'              => $expenseStatementCorrespondence['condo_id'],
            'expense_statement_id'  => $expenseStatementCorrespondence['expense_statement_id'],
            'document_visibility'   => 'protected',
            'document_type_id'      => $documentType['id'] ?? null
        ])
        ->update([
            // place node in dedicated folder
            'parent_node_id'    => $parentNode['id'] ?? null,
            // make node private
            'ownership_id'      => $expenseStatementCorrespondence['ownership_id'],
            'owner_id'          => $expenseStatementCorrespondence['owner_id']
        ])
        ->first();

    if(!$document) {
        throw new Exception('document_creation_failed', EQ_ERROR_UNKNOWN);
    }
    $document_id = $document['id'];
}

if($document_id) {
    // attach generated document to invitation
    ExpenseStatementCorrespondence::id($expenseStatementCorrespondence['id'])->update(['document_id' => $document_id]);
}

$context->httpResponse()
        ->status(201)
        ->send();
