<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace fmt\core\followup;

class TaskEvent extends \core\followup\TaskEvent {

    public static function getDescription(): string {
        return "A task event associated with an entity status change or date field value.";
    }

    public static function getColumns(): array {
        return [

            'entity' => [
                'type'              => 'string',
                'description'       => 'Namespace of the concerned entity.',
                'selection'         => [
                    'purchase\accounting\invoice\PurchaseInvoice'
                ],
                'required'          => true
            ],

            'entity_status' => [
                'type'              => 'string',
                'description'       => "Status of the purchase invoice the task event is associated with.",
                'selection'         => [
                    // PurchaseInvoice
                    'proforma',
                    'posted',
                    'cancelled'
                ],
                'visible'           => ['event_type', '=', 'status_change']
            ],

            'entity_date_field' => [
                'type'              => 'string',
                'description'       => "Date field of the entity the task event is associated with.",
                'visible'           => ['event_type', '=', 'date_field'],
                'selection'         => [
                    // PurchaseInvoice
                    'due_date',
                    'date_from',
                    'date_to',
                    'emission_date'
                ]
            ],

            'trigger_event_task_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'fmt\core\followup\TaskModel',
                'foreign_field'     => 'trigger_event_id',
                'description'       => "List of task models that uses the event as a trigger."
            ],

            'deadline_event_task_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'fmt\core\followup\TaskModel',
                'foreign_field'     => 'deadline_event_id',
                'description'       => "List of task models that uses the event as a deadline."
            ]

        ];
    }

    public static function getConstraints(): array {
        return [

            'entity' => [
                'not_allowed' => [
                    'message'   => 'Entity must be "purchase\accounting\invoice\PurchaseInvoice".',
                    'function'  => function ($entity, $values) {
                        return in_array($entity, ['purchase\accounting\invoice\PurchaseInvoice']);
                    }
                ]
            ],

            'entity_status' => [
                'invalid' => [
                    'message'   => 'Invalid PurchaseInvoice status.',
                    'function'  => function ($entity_status, $values) {
                        $map_statutes = [
                            'purchase\accounting\invoice\PurchaseInvoice' => [
                                'proforma',
                                'posted',
                                'cancelled'
                            ]
                        ];

                        $allowed_statuses = [];
                        if($values['entity'] && $map_statutes[$values['entity']]) {
                            $allowed_statuses = $map_statutes[$values['entity']];
                        }

                        return in_array($entity_status, $allowed_statuses);
                    }
                ]
            ],

            'entity_date_field' => [
                'invalid' => [
                    'message'   => 'Invalid PurchaseInvoice status.',
                    'function'  => function ($entity_date_field, $values) {
                        $map_date_fields = [
                            'purchase\accounting\invoice\PurchaseInvoice' => [
                                'due_date',
                                'date_from',
                                'date_to',
                                'emission_date'
                            ]
                        ];

                        $allowed_date_fields = [];
                        if($values['entity'] && $map_date_fields[$values['entity']]) {
                            $allowed_date_fields = $map_date_fields[$values['entity']];
                        }

                        return in_array($entity_date_field, $allowed_date_fields);
                    }
                ]
            ]

        ];
    }
}
