<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\ExpenseStatement;

[$params, $providers] = eQual::announce([
    'description'   => "Export assembly minutes: generate per-invitation documents (if missing), merge them into a single PDF, store the result as a non-EDMS document, and return its id.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The Expense Statement the export refers to.",
            'foreign_object'    => 'realestate\funding\ExpenseStatement',
            'required'          => true
        ],
        'perform_sending' => [
            'type'              => 'boolean',
            'description'       => 'If enabled, generated expense statement will be sent automatically.',
            'default'           => function ($id) {
                $expenseStatement = ExpenseStatement::id($id)->read(['is_sending_disabled'])->first();
                if($expenseStatement && $expenseStatement['is_sending_disabled']) {
                    return false;
                }
                return true;
            }
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;


$expenseStatements = ExpenseStatement::id($params['id'])
    ->read(['status', 'condo_id', 'name']);

if($expenseStatements->count() <= 0) {
    throw new Exception("unknown_fund_request_execution", EQ_ERROR_UNKNOWN_OBJECT);
}

$values = [
    'perform_sending' => $params['perform_sending']
];

$expenseStatements
    ->transition('validate')
    ->do('send_expense_statements', $values);

$context->httpResponse()
        ->status(204)
        ->send();
