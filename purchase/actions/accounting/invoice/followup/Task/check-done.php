<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2026
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use purchase\accounting\invoice\followup\Task;

[$params, $providers] = eQual::announce([
    'description'   => "Dismisses an alert related to given task if it is done.",
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'purchase\accounting\invoice\followup\Task',
            'description'       => "Identifier of the task that needs a check.",
            'required'          => true
        ],

        'message_model' => [
            'type'              => 'string',
            'description'       => "The name of the message model to use for the alert.",
            'default'           => 'purchase.accounting.invoice.followup.task.reminder'
        ],

        'severity' => [
            'type'              => 'string',
            'description'       => "Severity of the created alerts.",
            'selection'         => [
                'notice',
                'warning',
                'important',
                'urgent'
            ],
            'default'           => 'important'
        ]

    ],
    'access'        => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

$task = Task::id($params['id'])
    ->read(['is_done'])
    ->first(true);

if(is_null($task)) {
    throw new Exception("unknown_task", EQ_ERROR_UNKNOWN_OBJECT);
}

$dispatch->cancel($params['message_model'], Task::getType(), $task['id']);

if(!$task['is_done']) {
    $dispatch_params = [
        'id'            => $task['id'],
        'message_model' => $params['message_model'],
        'severity'      => $params['severity']
    ];

    $dispatch->dispatch(
        $params['message_model'],
        Task::getType(),
        $task['id'],
        $params['severity'],
        'purchase_accounting_invoice_followup_Task_check-done',
        $dispatch_params,
        [],
        null
    );
}

$context->httpResponse()
        ->status(200)
        ->send();
