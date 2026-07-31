<?php

namespace communication\broadcast;

use core\Task;
use documents\navigation\Node;
use equal\orm\Model;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;

class Broadcast extends Model {

    public static function constants() {
        return ['EMAIL_SMTP_ACCOUNT_EMAIL'];
    }

    public static function getDescription(): string {
        return 'Allows to prepare and send multiple emails to a specific group of co-owners.';
    }

    public static function getColumns(): array {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium concerned by the broadcast.",
                'foreign_object'    => 'realestate\property\Condominium',
                'onupdate'          => 'onupdateCondoId',
                'required'          => true,
                'help'              => 'This field is meant to be set only once at creation'
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the broadcast.'
            ],

            'step' => [
                'type'              => 'string',
                'description'       => "Step at which the broadcast is currently being.",
                'help'              => "These steps are a sub-workflow of the `creating` status.",
                'selection'         => [
                    'recipients_selection',
                    'content_edition'
                ],
                'default'           => 'recipients_selection',
                'visible'           => ['status', '=', 'draft']
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'ready',
                    'scheduled',
                    'processing',
                    'processed'
                ],
                'default'           => 'draft',
                'description'       => 'Current status of the broadcast.'
            ],

            'reply_to' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'email',
                'description'       => 'The email address of the sender.',
                'store'             => true,
                'function'          => 'calcReplyTo'
            ],

            'subject' => [
                'type'              => 'string',
                'description'       => 'Subject of the message.'
            ],

            'body' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => 'Subject of the message'
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the related entity.'
            ],

            'object_id' => [
                'type'              => 'string',
                'description'       => 'Identifier of the related entity.'
            ],

            'parent_node_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'documents\navigation\Node',
                'function'          => 'calcParentNodeId',
                'store'             => true,
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null]
                ]
            ],

            'ignore_communication_preferences' => [
                'type'              => 'boolean',
                'description'       => 'Ignore the communication preferences of the selected ownerships.',
                'default'           => false
            ],

            'ownerships_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'foreign_field'     => 'broadcasts_ids',
                'rel_table'         => 'realestate_ownership_rel_broadcast',
                'rel_foreign_key'   => 'ownership_id',
                'rel_local_key'     => 'broadcast_id',
                'description'       => 'Ownerships that are concerned by the broadcast.',
                'onupdate'          => 'onupdateOwnershipsIds',
                'visible'           => ['condo_id', '<>', null]
            ],

            'owners_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\ownership\Owner',
                'foreign_field'     => 'broadcasts_ids',
                'rel_table'         => 'realestate_owner_rel_broadcast',
                'rel_foreign_key'   => 'owner_id',
                'rel_local_key'     => 'broadcast_id',
                'description'       => 'Owners that are concerned by the broadcast.',
                'onupdate'          => 'onupdateOwnersIds',
                'visible'           => ['condo_id', '<>', null]
            ],

            'identities_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'identity\Identity',
                'foreign_field'     => 'broadcasts_ids',
                'rel_table'         => 'identity_identity_rel_broadcast',
                'rel_foreign_key'   => 'identity_id',
                'rel_local_key'     => 'broadcast_id',
                'description'       => 'Identities to which the broadcast must be sent.'
            ],

            'documents_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'documents\Document',
                'foreign_field'     => 'broadcasts_ids',
                'rel_table'         => 'communication_broadcast_rel_document',
                'rel_foreign_key'   => 'document_id',
                'rel_local_key'     => 'broadcast_id',
                'description'       => 'One or more documents that relate to the Broadcast (attachment).',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => [
                    ['object_class', '=', 'communication\broadcast\Broadcast'],
                    ['object_id', '=', 'object.id']
                ],
                'description'       => 'List of emails sent in the context of the broadcast.'
            ]

        ];
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the state of the broadcast allows it to be ready.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    protected static function policyIsValid($self) {
        $result = [];
        $self->read(['subject', 'body', 'identities_ids' => ['email']]);
        foreach($self as $id => $broadcast) {
            if(empty($broadcast['identities_ids'])) {
                $result[$id] = [
                    'identities_ids' => 'Recipients are needed.'
                ];
            }

            foreach($broadcast['identities_ids'] as $identity) {
                if(empty($identity['email'])) {
                    $result[$id] = [
                        'identities_ids' => 'At least one email is missing from an identity.'
                    ];
                    break;
                }
            }

            if(empty($broadcast['subject'])) {
                $result[$id] = [
                    'subject' => 'Subject can\'t empty.'
                ];
            }

            if(empty($broadcast['body'])) {
                $result[$id] = [
                    'body' => 'Body can\'t empty.'
                ];
            }
        }

        return $result;
    }

    public static function getWorkflow() {
        return [

            'draft' => [
                'description' => 'The broadcast is under creation.',
                'icon'        => 'draft',
                'transitions' => [
                    'mark_ready' => [
                        'description'   => 'Mark the broadcast as ready.',
                        'status'        => 'ready',
                        'policies'      => ['is_valid']
                    ]
                ]
            ],

            'ready' => [
                'description' => 'The broadcast processing can be scheduled.',
                'icon'        => 'check',
                'transitions' => [
                    'select_recipients' => [
                        'description'   => 'Return to the draft status to make some modifications of recipients.',
                        'status'        => 'draft',
                        'onbefore'      => 'onbeforeSelectRecipients'
                    ],
                    'schedule' => [
                        'description'   => 'Schedule the processing of the broadcast.',
                        'status'        => 'scheduled',
                        'onbefore'      => 'onbeforeSchedule'
                    ]
                ]
            ],

            'scheduled' => [
                'description' => 'Schedule the processing of the broadcast.',
                'icon'        => 'schedule',
                'transitions' => [
                    'start_processing' => [
                        'description'   => 'Start the processing of the broadcast.',
                        'status'        => 'processing'
                    ]
                ]
            ],

            'processing' => [
                'description' => 'Process the broadcast (e.g., generate all emails).',
                'icon'        => 'refresh',
                'transitions' => [
                    'end_processing' => [
                        'description'   => 'End the processing of the broadcast.',
                        'status'        => 'processed',
                        'onafter'       => 'onafterProcessing'
                    ]
                ]
            ],

            'processed' => [
                'description' => 'The broadcast was processed (e.g., all emails were generated).',
                'icon'        => 'done_all'
            ]

        ];
    }

    protected static function onbeforeSelectRecipients($self) {
        $self->update(['step' => 'recipients_selection']);
    }

    protected static function onbeforeSchedule($self) {
        foreach($self as $id => $broadcast) {
            Task::create([
                'name'          => "Handle broadcast {$id}",
                'is_recurring'  => false,
                'controller'    => 'communication_broadcast_Broadcast_process',
                'params'        => json_encode(['id' => $id])
            ]);
        }
    }

    protected static function onafterProcessing($self) {
        foreach($self as $id => $broadcast) {
            Task::search([
                ['controller', '=', 'communication_broadcast_Broadcast_process'],
                ['params', '=', json_encode(['id' => $id])]
            ])
                ->delete();
        }
    }

    public static function calcReplyTo($self): array {
        $result = [];
        foreach($self as $id => $broadcast) {
            $result[$id] = constant('EMAIL_SMTP_ACCOUNT_EMAIL');
        }

        return $result;
    }

    public static function calcParentNodeId($self): array {
        $result = [];
        $self->read(['condo_id']);
        foreach($self as $id => $assembly) {
            if(!$assembly['condo_id']) {
                continue;
            }
            // store document in related Imports folder
            $parentNode = Node::search([
                ['condo_id', '=', $assembly['condo_id']],
                ['node_type', '=', 'folder'],
                ['code', '=', 'imports']
            ])
                ->first();
            if($parentNode) {
                $result[$id] = $parentNode['id'];
            }
        }

        return $result;
    }

    protected static function onupdateCondoId($self, $values) {
        $self->read(['condo_id', 'ownerships_ids', 'owners_ids', 'identities_ids']);
        foreach($self as $id => $broadcast) {
            if($broadcast['condo_id'] === $values['condo_id']) {
                continue;
            }

            $data = [];
            if(!empty($broadcast['ownerships_ids'])) {
                $data['ownerships_ids'] = array_map(fn($ownership_id) => -$ownership_id, $broadcast['ownerships_ids']);
            }
            if(!empty($broadcast['owners_ids'])) {
                $data['owners_ids'] = array_map(fn($owner_id) => -$owner_id, $broadcast['owners_ids']);
            }
            if(!empty($broadcast['identities_ids'])) {
                $data['identities_ids'] = array_map(fn($owner_id) => -$owner_id, $broadcast['identities_ids']);
            }

            if(!empty($data)) {
                self::id($id)->update($data);
            }
        }
    }

    protected static function onupdateOwnershipsIds($self, $values) {
        $self->read(['ignore_communication_preferences']);
        foreach($self as $id => $broadcast) {
            $added_ownerships_ids = array_filter($values['ownerships_ids'], fn($ownership_id) => $ownership_id > 0);
            $removed_ownerships_ids = array_map('abs', array_filter($values['ownerships_ids'], fn($ownership_id) => $ownership_id < 0));

            if($broadcast['ignore_communication_preferences']) {
                // 1. Handle added ownerships must add their owners

                $added_ownerships = Ownership::ids($added_ownerships_ids)
                    ->read(['owners_ids'])
                    ->get();

                $map_owners_ids = [];
                foreach($added_ownerships as $ownership) {
                    foreach($ownership['owners_ids'] as $owner_id) {
                        $map_owners_ids[$owner_id] = true;
                    }
                }

                if(!empty($map_owners_ids)) {
                    $self->update(['owners_ids' => array_keys($map_owners_ids)]);
                }

                // 2. Handle removed ownerships must remove their owners/identities

                $removed_ownerships = Ownership::ids($removed_ownerships_ids)
                    ->read(['owners_ids'])
                    ->get();

                $map_owners_ids = [];
                foreach($removed_ownerships as $ownership) {
                    foreach($ownership['owners_ids'] as $owner_id) {
                        $map_owners_ids[$owner_id * -1] = true;
                    }
                }

                if(!empty($map_owners_ids)) {
                    $self->update(['owners_ids' => array_keys($map_owners_ids)]);
                }
            }
            else {
                // 1. Handle added ownerships must add their owners

                $added_ownerships = Ownership::ids($added_ownerships_ids)
                    ->read([
                        'ownership_communication_preferences_ids' => [
                            '@domain' => ['communication_reason', '=', 'technical_communication'],
                            'is_owner',
                            'owner_id',
                            'identity_id'
                        ]
                    ])
                    ->get();

                $map_owners_ids = [];
                $map_identities_ids = [];
                foreach($added_ownerships as $ownership) {
                    if(!empty($ownership['ownership_communication_preferences_ids'])) {
                        $technical_com_pref = reset($ownership['ownership_communication_preferences_ids']);

                        if($technical_com_pref['is_owner']) {
                            $map_owners_ids[$technical_com_pref['owner_id']] = true;
                        }
                        else {
                            $map_identities_ids[$technical_com_pref['identity_id']] = true;
                        }
                    }
                }

                if(!empty($map_owners_ids)) {
                    $self->update(['owners_ids' => array_keys($map_owners_ids)]);
                }
                if(!empty($map_identities_ids)) {
                    $self->update(['identities_ids' => array_keys($map_identities_ids)]);
                }

                // 2. Handle removed ownerships must remove their owners/identities

                $removed_ownerships = Ownership::ids($removed_ownerships_ids)
                    ->read([
                        'ownership_communication_preferences_ids' => [
                            '@domain' => ['communication_reason', '=', 'technical_communication'],
                            'is_owner',
                            'owner_id',
                            'identity_id'
                        ]
                    ])
                    ->get();

                $map_owners_ids = [];
                $map_identities_ids = [];
                foreach($removed_ownerships as $ownership) {
                    if(!empty($ownership['ownership_communication_preferences_ids'])) {
                        $technical_com_pref = reset($ownership['ownership_communication_preferences_ids']);

                        if($technical_com_pref['is_owner']) {
                            $map_owners_ids[$technical_com_pref['owner_id'] * -1] = true;
                        }
                        else {
                            $map_identities_ids[$technical_com_pref['identity_id'] * -1] = true;
                        }
                    }
                }

                if(!empty($map_owners_ids)) {
                    $self->update(['owners_ids' => array_keys($map_owners_ids)]);
                }
                if(!empty($map_identities_ids)) {
                    $self->update(['identities_ids' => array_keys($map_identities_ids)]);
                }
            }
        }
    }

    protected static function onupdateOwnersIds($self, $values) {
        // 1. Handle added owners must add their identities
        $added_owners_ids = array_filter($values['owners_ids'], fn($owner_id) => $owner_id > 0);

        $added_owners = Owner::ids($added_owners_ids)
            ->read(['identity_id'])
            ->get();

        $map_identities_ids = [];
        foreach($added_owners as $owner) {
            $map_identities_ids[$owner['identity_id']] = true;
        }

        $self->update(['identities_ids' => array_keys($map_identities_ids)]);

        // 2. Handle removed owners must remove their identities
        $removed_owners_ids = array_map('abs', array_filter($values['owners_ids'], fn($owner_id) => $owner_id < 0));

        $removed_owners = Owner::ids($removed_owners_ids)
            ->read(['identity_id'])
            ->get();

        $map_identities_ids = [];
        foreach($removed_owners as $owner) {
            $map_identities_ids[$owner['identity_id'] * -1] = true;
        }

        $self->update(['identities_ids' => array_keys($map_identities_ids)]);
    }
}

