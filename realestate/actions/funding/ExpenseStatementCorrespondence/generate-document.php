<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
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


$expenseStatementCorrespondence = ExpenseStatementCorrespondence::id($params['id'])
    ->read(['status', 'condo_id', 'ownership_id', 'expense_statement_id', 'name'])
    ->first();

if(!$expenseStatementCorrespondence) {
    throw new Exception("unknown_expense_statement_correspondence", EQ_ERROR_UNKNOWN_OBJECT);
}


// retrieve FS Node relating to expense statements
$parentNode = Node::search([
        ['condo_id', '=', $expenseStatementCorrespondence['condo_id'] ],
        ['node_type', '=', 'folder'],
        ['code', '=', 'operation_statements']
    ])
    ->first();

// generate document and add it to EDMS
$data = eQual::run('get', 'realestate_funding_ExpenseStatementCorrespondence_render-pdf', ['id' => $expenseStatementCorrespondence['id']]);

$document = Document::create([
        'name'                  => 'Décompte de charges - ' . $expenseStatementCorrespondence['name'],
        'data'                  => $data,
        'condo_id'              => $expenseStatementCorrespondence['condo_id'],
        'expense_statement_id'  => $expenseStatementCorrespondence['expense_statement_id']
    ])
    ->update([
        // place node in dedicated folder
        'parent_node_id'    => $parentNode['id'] ?? null,
        // make node private
        'ownership_id'      => $expenseStatementCorrespondence['ownership_id']
    ])
    ->first();

if(!$document) {
    throw new Exception('document_creation_failed', EQ_ERROR_UNKNOWN);
}

// attach generated document to invitation
ExpenseStatementCorrespondence::id($expenseStatementCorrespondence['id'])->update(['document_id' => $document['id']]);


$context->httpResponse()
        ->status(201)
        ->send();
