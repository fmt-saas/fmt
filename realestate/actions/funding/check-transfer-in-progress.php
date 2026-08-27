<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\ExpenseStatement;
use realestate\funding\FundRequest;
use realestate\funding\FundRequestExecution;
use realestate\property\Condominium;
use realestate\property\OwnershipTransfer;

[$params, $providers] = eQual::announce([
    'description'   => "Warns about draft fund requests and expense statements when an ownership transfer is in progress.",
    'params'        => [
        'condo_id' => [
            'type'              => 'many2one',
            'description'       => 'Identifier of the condominium to check.',
            'foreign_object'    => 'realestate\property\Condominium',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context         $context
 * @var \equal\dispatch\Dispatcher $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

$condominium = Condominium::id($params['condo_id'])
    ->read(['id'])
    ->first();

if(!$condominium) {
    throw new Exception('unknown_condominium', EQ_ERROR_UNKNOWN_OBJECT);
}

$ownershipTransfer = OwnershipTransfer::search([
        ['condo_id', '=', $params['condo_id']],
        ['status', 'in', ['seller_documents_sent', 'confirmed']]
    ])
    ->read(['id'])
    ->first();

if($ownershipTransfer) {
    $tasks = [];

    $fundRequestExecutions = FundRequestExecution::search([
            ['condo_id', '=', $params['condo_id']],
            ['status', '=', 'proforma']
        ])
        ->read(['id', 'fund_request_id']);

    foreach($fundRequestExecutions as $fundRequestExecution) {
        $tasks[FundRequestExecution::getType().':'.$fundRequestExecution['id']] = [
            'object_class' => FundRequestExecution::getType(),
            'object_id'    => $fundRequestExecution['id']
        ];

        if($fundRequestExecution['fund_request_id']) {
            $tasks[FundRequest::getType().':'.$fundRequestExecution['fund_request_id']] = [
                'object_class' => FundRequest::getType(),
                'object_id'    => $fundRequestExecution['fund_request_id']
            ];
        }
    }

    $expenseStatements = ExpenseStatement::search([
            ['condo_id', '=', $params['condo_id']],
            ['status', '=', 'proforma']
        ])
        ->read(['id']);

    foreach($expenseStatements as $expenseStatement) {
        $tasks[ExpenseStatement::getType().':'.$expenseStatement['id']] = [
            'object_class' => ExpenseStatement::getType(),
            'object_id'    => $expenseStatement['id']
        ];
    }

    foreach($tasks as $task) {
        $dispatch->dispatch(
            'finance.accounting.fund_request.transfer_in_progress',
            $task['object_class'],
            $task['object_id'],
            'important'
        );
    }
}

$context->httpResponse()
    ->status(204)
    ->send();
