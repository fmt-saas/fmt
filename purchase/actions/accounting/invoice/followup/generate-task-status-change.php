<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use purchase\accounting\invoice\PurchaseInvoice;
use purchase\accounting\invoice\followup\Task;
use purchase\accounting\invoice\followup\TaskModel;

[$params, $providers] = eQual::announce([
    'description'	=> "Generate task models' tasks when a purchase_invoice status changes.",
    'params' 		=> [

        'purchase_invoice_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'purchase\accounting\invoice\PurchaseInvoice',
            'description'       => "PurchaseInvoice the status has just changed.",
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

$task_models = TaskModel::search(['entity', '=', 'purchase\accounting\invoice\PurchaseInvoice'])
    ->read([
        'name',
        'trigger_event_id' => [
            'event_type',
            'entity_status',
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
        return $task_model['trigger_event_id']['event_type'] === 'status_change';
    }
);

if(!empty($task_models)) {
    $map_date_fields = [];
    foreach($task_models as $task_model) {
        if(isset($task_model['deadline_event_id']['entity_date_field'])) {
            $map_date_fields[$task_model['deadline_event_id']['entity_date_field']] = true;
        }
    }

    $date_fields = array_keys($map_date_fields);

    $invoice = PurchaseInvoice::id($params['purchase_invoice_id'])
        ->read(array_merge($date_fields, ['status']))
        ->first(true);

    if(is_null($invoice)) {
        throw new Exception("unknown_entity", EQ_ERROR_UNKNOWN_OBJECT);
    }

    foreach($task_models as $task_model) {
        if($task_model['trigger_event_id']['entity_status'] !== $invoice['status']) {
            continue;
        }

        $visible_date = time() + (86400 * $task_model['trigger_event_id']['offset']);

        $deadline_date = null;
        if(isset($task_model['deadline_event_id'])) {
            if(isset($invoice[$task_model['deadline_event_id']['entity_date_field']])) {
                $deadline_date = $invoice[$task_model['deadline_event_id']['entity_date_field']] + (86400 * $task_model['deadline_event_id']['offset']);
            }
            else {
                // TODO: report problem date not set
            }
        }

        $task = Task::search([
                ['task_model_id', '=', $task_model['id']],
                ['purchase_invoice_id', '=', $invoice['id']]
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
            'purchase_invoice_id'   => $invoice['id'],
            'notes'                 => $notes
        ])
            ->read(['description']);
    }
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
