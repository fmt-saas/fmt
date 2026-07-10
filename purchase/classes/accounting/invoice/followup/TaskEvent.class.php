<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace purchase\accounting\invoice\followup;

class TaskEvent extends \core\followup\TaskEvent {

    public static function getDescription(): string {
        return "A task event associated with a purchase invoice status change or an purchase invoice date field value.";
    }

    public static function getColumns(): array {
        return [

            'entity' => [
                'type'              => 'string',
                'description'       => "Namespace of the concerned entity.",
                'default'           => 'purchase\accounting\invoice\PurchaseInvoice'
            ],

            'entity_status' => [
                'type'              => 'string',
                'description'       => "Status of the purchase invoice the task event is associated with.",
                'selection'         => [
                    'proforma',
                    'posted',
                    'cancelled'
                ],
                'visible'           => ['event_type', '=', 'status_change'],
                'default'           => 'quote'
            ],

            'entity_date_field' => [
                'type'              => 'string',
                'description'       => "Date field of the entity the task event is associated with.",
                'visible'           => ['event_type', '=', 'date_field'],
                'selection'         => [
                    'due_date',
                    'date_from',
                    'date_to',
                    'emission_date'
                ],
                'default'           => 'due_date'
            ],

            'trigger_event_task_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\accounting\invoice\followup\TaskModel',
                'foreign_field'     => 'trigger_event_id',
                'description'       => "List of task models that uses the event as a trigger."
            ],

            'deadline_event_task_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\accounting\invoice\followup\TaskModel',
                'foreign_field'     => 'deadline_event_id',
                'description'       => "List of task models that uses the event as a deadline."
            ]

        ];
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['event_type'])) {
            if($event['event_type'] === 'status_change') {
                $result['entity_status'] = 'proforma';
            }
            elseif($event['event_type'] === 'date_field') {
                $result['entity_date_field'] = 'due_date';
            }
        }

        return $result;
    }

    public static function getConstraints(): array {
        return [

            'entity' => [
                'not_allowed' => [
                    'message'   => 'Entity must be "purchase\accounting\invoice\PurchaseInvoice".',
                    'function'  => function ($entity, $values) {
                        return $entity === 'purchase\accounting\invoice\PurchaseInvoice';
                    }
                ]
            ],

            'entity_status' => [
                'invalid' => [
                    'message'   => 'Invalid PurchaseInvoice status.',
                    'function'  => function ($entity_status, $values) {
                        return in_array($entity_status, [
                            'proforma',
                            'posted',
                            'cancelled'
                        ]);
                    }
                ]
            ],

            'entity_date_field' => [
                'invalid' => [
                    'message'   => 'Invalid PurchaseInvoice status.',
                    'function'  => function ($entity_date_field, $values) {
                        return in_array($entity_date_field, [
                            'due_date',
                            'date_from',
                            'date_to',
                            'emission_date'
                        ]);
                    }
                ]
            ]

        ];
    }
}
