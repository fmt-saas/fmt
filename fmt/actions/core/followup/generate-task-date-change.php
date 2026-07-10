<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2026
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use fmt\core\followup\Task;
use fmt\core\followup\TaskModel;
use purchase\accounting\invoice\PurchaseInvoice;

[$params, $providers] = eQual::announce([
    'description'   => "Generate task models' tasks when the date changes.",
    'help'          => "Is meant to be triggered once each day.",
    'params'        => [

        'entity' => [
            'type'              => 'string',
            'description'       => 'Namespace of the concerned entity.',
            'selection'         => [
                'purchase\accounting\invoice\PurchaseInvoice'
            ],
            'required'          => true
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
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$task_models = TaskModel::search(['entity', '=', $params['entity']])
    ->read([
        'name',
        'trigger_event_id' => [
            'event_type',
            'entity_date_field',
            'offset'
        ],
        'deadline_event_id' => [
            'event_type',
            'entity_date_field',
            'offset'
        ]
    ])
    ->get(true);

$task_models = array_filter(
    $task_models,
    function($task_model) {
        return $task_model['trigger_event_id']['event_type'] === 'date_field';
    }
);

if(!empty($task_models)) {
    $map_date_fields = [];
    foreach($task_models as $task_model) {
        $map_date_fields[$task_model['trigger_event_id']['entity_date_field']] = true;
        if(isset($map_date_fields[$task_model['deadline_event_id']['entity_date_field']])) {
            $map_date_fields[$task_model['deadline_event_id']['entity_date_field']] = true;
        }
    }

    $date_fields = array_keys($map_date_fields);

    $today = date('Y-m-d', time());

    $objects = $params['entity']::search()
        ->read($date_fields)
        ->get();

    $map_entities_relation_field = [
        PurchaseInvoice::getType() => 'purchase_invoice_id'
    ];

    $relation_field = $map_entities_relation_field[$params['entity']];

    foreach($task_models as $task_model) {
        foreach($objects as $object) {
            $date = $object[$task_model['trigger_event_id']['entity_date_field']] + (86400 * $task_model['trigger_event_id']['offset']);
            if(date('Y-m-d', $date) !== $today) {
                continue;
            }

            $visible_date = time();

            $deadline_date = null;
            if(isset($task_model['deadline_event_id'])) {
                if(isset($object[$task_model['deadline_event_id']['entity_date_field']])) {
                    $deadline_date = $object[$task_model['deadline_event_id']['entity_date_field']] + (86400 * $task_model['deadline_event_id']['offset']);
                }
                else {
                    // TODO: report problem date not set
                }
            }

            $domain = [
                ['task_model_id', '=', $task_model['id']]
            ];


            $task = Task::search([
                ['task_model_id', '=', $task_model['id']],
                [$relation_field, '=', $object['id']],
            ])
                ->read(['notes'])
                ->first();

            $notes = null;
            if(!is_null($task)) {
                // Keep notes of existing task, but remove it to be replaced
                $notes = $task['notes'];

                Task::id($task['id'])->delete();
            }

            Task::create([
                'name'                  => $task_model['name'],
                'visible_date'          => $visible_date,
                'deadline_date'         => $deadline_date,
                'task_model_id'         => $task_model['id'],
                $relation_field         => $object['id'],
                'notes'                 => $notes
            ])
                ->read(['description']);
        }
    }
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
