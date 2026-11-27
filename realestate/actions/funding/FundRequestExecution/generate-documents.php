<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestExecutionCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Schedule each fund request execution correspondence document, then initialize sending and export.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\FundRequestExecution',
            'description'       => 'Identifier of the fund request execution whose documents must be generated.',
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
 * @var \equal\php\Context     $context
 * @var \equal\cron\Scheduler $cron
 */
['context' => $context, 'cron' => $cron] = $providers;

$fundRequestExecution = FundRequestExecution::id($params['id'])
    ->read(['status'])
    ->first();

if(!$fundRequestExecution) {
    throw new Exception('unknown_fund_request_execution', EQ_ERROR_UNKNOWN_OBJECT);
}

if($fundRequestExecution['status'] !== 'posted') {
    throw new Exception('fund_request_execution_not_posted', EQ_ERROR_INVALID_PARAM);
}

$fundRequestExecutionCorrespondences = FundRequestExecutionCorrespondence::search([
        ['fund_request_execution_id', '=', $fundRequestExecution['id']]
    ])
    ->read(['document_id']);

$moment = time();

foreach($fundRequestExecutionCorrespondences as $id => $fundRequestExecutionCorrespondence) {
    if($fundRequestExecutionCorrespondence['document_id']) {
        continue;
    }

    $task_name = "realestate_funding_FundRequestExecutionCorrespondence_generate-document.{$id}";
    $cron->cancel($task_name);
    $cron->schedule(
        $task_name,
        ++$moment,
        'realestate_funding_FundRequestExecutionCorrespondence_generate-document',
        ['id' => $id]
    );
}

FundRequestExecution::id($fundRequestExecution['id'])
    ->do('send_fund_request_execution_correspondences');

$context->httpResponse()
    ->status(201)
    ->send();
