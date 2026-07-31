<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\ExpenseStatement;
use realestate\funding\ExpenseStatementCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Send all expense statement emails for the target expense statement.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The Expense Statement the sending refers to.",
            'foreign_object'    => 'realestate\funding\ExpenseStatement',
            'required'          => true
        ],

        'communication_method' => [
            'type'              => 'string',
            'description'       => 'Method of sending.',
            'help'              => 'This controllers expect only digital communication methods (e.g. email).',
            'default'           => 'email',
            'selection'         => [
                'email'
            ]
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
 * @var \equal\php\Context                 $context
 */
['context' => $context] = $providers;


$expenseStatement = ExpenseStatement::id($params['id'])
    ->read(['status', 'condo_id', 'name'])
    ->first();

if(!$expenseStatement) {
    throw new Exception('unknown_expense_statement', EQ_ERROR_UNKNOWN_OBJECT);
}

// fetch correspondences relating to given communication_method
$expenseStatementCorrespondences = ExpenseStatementCorrespondence::search([
        [ 'expense_statement_id', '=', $expenseStatement['id'] ],
        [ 'communication_method', '=', $params['communication_method'] ]
    ])
    ->read(['is_sent', 'document_id']);

foreach($expenseStatementCorrespondences as $expense_statement_correspondence_id => $expenseStatementCorrespondence) {
    // #memo - `export-statements` and `send-statements` are the controllers where expense statement documents can be generated on demand
    if(!$expenseStatementCorrespondence['document_id']) {
        try {
            // generate document, add it to EDMS, and attach it to the correspondence
            eQual::run('do', 'realestate_funding_ExpenseStatementCorrespondence_generate-document', ['id' => $expense_statement_correspondence_id]);
        }
        catch(Exception $e) {
            // error while rendering or duplicate
        }
    }
}

// send all generated documents
foreach($expenseStatementCorrespondences as $expense_statement_correspondence_id => $expenseStatementCorrespondence)  {
    $expenseStatementCorrespondence = ExpenseStatementCorrespondence::id($expense_statement_correspondence_id)
        ->read(['document_id' => ['data']])
        ->first();

    if(!$expenseStatementCorrespondence['document_id']) {
        continue;
    }

    try {
        eQual::run('do', 'realestate_funding_ExpenseStatementCorrespondence_send-email', ['id' => $expense_statement_correspondence_id]);
    }
    catch(Exception $e) {
        trigger_error('APP::Error while sending documents ' . $e->getMessage(), EQ_REPORT_ERROR);
        continue;
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
