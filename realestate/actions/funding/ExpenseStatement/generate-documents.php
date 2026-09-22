<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\ExpenseStatement;
use realestate\funding\ExpenseStatementCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate the static documents and correspondences of an expense statement, then schedule each correspondence document.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\ExpenseStatement',
            'description'       => 'Identifier of the expense statement whose documents must be generated.',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8'
    ],
    'providers'     => ['context', 'cron']
]);

/**
 * @var \equal\php\Context      $context
 * @var \equal\cron\Scheduler  $cron
 */
['context' => $context, 'cron' => $cron] = $providers;

$expenseStatement = ExpenseStatement::id($params['id'])
    ->read(['status'])
    ->first();

if(!$expenseStatement) {
    throw new Exception('unknown_expense_statement', EQ_ERROR_UNKNOWN_OBJECT);
}

if($expenseStatement['status'] !== 'posted') {
    throw new Exception('expense_statement_not_posted', EQ_ERROR_INVALID_PARAM);
}

ExpenseStatement::id($expenseStatement['id'])
    ->do('generate_expense_statement_documents')
    ->do('generate_expense_statement_correspondences');

$expenseStatementCorrespondences = ExpenseStatementCorrespondence::search([
        ['expense_statement_id', '=', $expenseStatement['id']]
    ])
    ->read(['document_id']);

$moment = time();

foreach($expenseStatementCorrespondences as $id => $expenseStatementCorrespondence) {
    if($expenseStatementCorrespondence['document_id']) {
        continue;
    }

    $task_name = "realestate_funding_ExpenseStatementCorrespondence_generate-document.{$id}";
    $moment += 60;
    // Replace a pending task with the same purpose when the orchestration is retried.
    $cron->cancel($task_name);
    $cron->schedule(
        $task_name,
        $moment,
        'realestate_funding_ExpenseStatementCorrespondence_generate-document',
        ['id' => $id]
    );
}

$context->httpResponse()
    ->status(201)
    ->send();
