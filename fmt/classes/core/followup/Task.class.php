<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace fmt\core\followup;

use core\setting\Setting;

class Task extends \core\followup\Task {

    public static function getDescription(): string {
        return "Task that has been or must be completed.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the task."
            ],

            'description' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Short description of the task.",
                'store'             => true,
                'function'          => 'calcDescription'
            ],

            'is_done' => [
                'type'              => 'boolean',
                'description'       => "Whether the task is done.",
                'default'           => false,
                'onupdate'          => 'onupdateIsDone'
            ],

            'done_by' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => "The user who completed the task."
            ],

            'task_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'fmt\core\followup\TaskModel',
                'description'       => 'The model used to create the task.',
                'help'              => 'Based on model or arbitrary.',
                'required'          => false
            ],

            'entity' => [
                'type'              => 'string',
                'description'       => 'Namespace of the concerned entity.',
                'selection'         => [
                    'purchase\accounting\invoice\PurchaseInvoice'
                ],
                'required'          => true
            ],

            'entity_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Id of the associated entity.',
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcEntityId',
                'readonly'          => true
            ],

            'purchase_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Purchase invoice the task relates to.',
                'readonly'          => true
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Namespace of the concerned entity.',
                'required'          => false,
                'help'              => 'Overloaded to make field optional.'
            ],

            'object_id' => [
                'type'              => 'integer',
                'description'       => 'Id of the associated entity.',
                'required'          => false,
                'help'              => 'Overloaded to make field optional.'
            ]

        ];
    }

    public static function calcDescription($self): array {
        $purchase_invoice_description_format = Setting::get_value('purchase', 'accounting', 'task.description_format', '');
        $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

        $result = [];
        $self->read([
            'purchase_invoice_id' => [
                'name',
                'invoice_type',
                'description',
                'on_hold_description',
                'emission_date',
                'posting_date'
            ]
        ]);
        foreach($self as $id => $task) {
            if(isset($task['purchase_invoice_id'])) {
                $result[$id] = Setting::parse_format($purchase_invoice_description_format, [
                    'name'                  => $task['purchase_invoice_id']['name'],
                    'invoice_type'          => $task['purchase_invoice_id']['invoice_type'],
                    'description'           => $task['purchase_invoice_id']['description'],
                    'on_hold_description'   => $task['purchase_invoice_id']['on_hold_description'],
                    'emission_date'         => date($date_format, $task['purchase_invoice_id']['emission_date']),
                    'posting_date'          => date($date_format, $task['purchase_invoice_id']['posting_date'])
                ]);
            }
        }

        return $result;
    }

    public static function calcEntityId($self): array {
        $result = [];
        $self->read(['purchase_invoice_id']);
        foreach($self as $id => $task) {
            if(isset($task['purchase_invoice_id'])) {
                $result[$id] = $task['purchase_invoice_id'];
            }
        }

        return $result;
    }

    public static function getConstraints(): array {
        return [

            'entity' =>  [
                'not_allowed' => [
                    'message'   => 'Entity must be "purchase\accounting\invoice\PurchaseInvoice".',
                    'function'  => function ($entity, $values) {
                        return in_array($entity, ['purchase\accounting\invoice\PurchaseInvoice']);
                    }
                ]
            ]

        ];
    }

    protected static function doCancelAlerts($self, $dispatch) {
        $self->read(['purchase_invoice_id']);
        foreach($self as $id => $task) {
            if($task['purchase_invoice_id']) {
                $dispatch->cancel('purchase.accounting.invoice.followup.task.reminder', 'fmt\core\followup\Task', $id);
            }
        }
    }

    public static function getActions(): array {
        return [

            'cancel_alerts' => [
                'description'   => "Cancel alerts linked to this task.",
                'policies'      => [],
                'function'      => 'doCancelAlerts'
            ]

        ];
    }

    public static function onupdateIsDone($self) {
        parent::onupdateIsDone($self);

        $self->do('cancel_alerts');
    }
}
