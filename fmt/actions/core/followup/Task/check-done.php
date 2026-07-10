<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2026
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use fmt\core\followup\Task;
use purchase\accounting\invoice\PurchaseInvoice;

[$params, $providers] = eQual::announce([
    'description'   => "Dismisses an alert related to given task if it is done.",
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'fmt\core\followup\Task',
            'description'       => "Identifier of the task that needs a check.",
            'required'          => true
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
    ->read(['is_done', 'entity'])
    ->first(true);

if(is_null($task)) {
    throw new Exception("unknown_task", EQ_ERROR_UNKNOWN_OBJECT);
}

$map_entities_message_models = [
    PurchaseInvoice::getType() => 'purchase.accounting.invoice.followup.task.reminder'
];

if(!isset($map_entities_message_models[$task['entity']])) {
    throw new Exception("not_handle_entity", EQ_ERROR_INVALID_PARAM);
}

$dispatch->cancel($map_entities_message_models[$task['entity']], Task::getType(), $task['id']);

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
        'fmt_core_followup_Task_check-done',
        $dispatch_params
    );
}

$context->httpResponse()
        ->status(200)
        ->send();
