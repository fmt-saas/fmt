<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2026
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use core\alert\MessageModel;
use fmt\core\followup\Task;
use purchase\accounting\invoice\PurchaseInvoice;

[$params, $providers] = eQual::announce([
    'description'   => "Alerts followup tasks that should have be done by today.",
    'params'        => [

        'entity' => [
            'type'              => 'string',
            'description'       => 'Namespace of the concerned entity.',
            'selection'         => [
                'purchase\accounting\invoice\PurchaseInvoice'
            ],
            'required'          => true
        ],

        'message_model' => [
            'type'              => 'string',
            'description'       => "The name of the message model to use for the alert.",
            'selection'         => [
                'purchase.accounting.invoice.followup.task.reminder'
            ],
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

$domain = [
    ['is_done', '=', false],
    ['deadline_date', '<=', time()],
    ['object_class', '=', $params['entity']]
];

$tasks = Task::search($domain)
    ->read(['id'])
    ->get();

if(!empty($tasks)) {
    $message_model = MessageModel::search([
        ['name', '=', $params['message_model']]
    ])
        ->read(['name'])
        ->first();

    if(is_null($message_model)) {
        $message_model = MessageModel::create([
            'name'          => $params['message_model'],
            'label'         => "Task deadline has expired",
            'description'   => "A task was not handled within the required timeframe."
        ])
            ->read(['name'])
            ->first();
    }

    foreach($tasks as $id => $task) {
        $dispatch_params = [
            'id'            => $id,
            'message_model' => $message_model['name'],
            'severity'      => $params['severity']
        ];

        $dispatch->dispatch(
            $message_model['name'],
            Task::getType(),
            $id,
            $params['severity'],
            'fmt_core_followup_Task_check-done',
            $dispatch_params,
            [],
            null
        );
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->send();
