<?php

namespace communication\broadcast;

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
                'foreign_object'    => 'realestate\property\Condominium'
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
                'visible'           => ['status', '=', 'creating']
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'creating',
                    'ready'
                ],
                'default'           => 'creating',
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
            ]

        ];
    }

    public static function calcReplyTo($self): array {
        $result = [];
        foreach($self as $id => $broadcast) {
            $result[$id] = constant('EMAIL_SMTP_ACCOUNT_EMAIL');
        }

        return $result;
    }

    protected static function onupdateOwnershipsIds($self, $values) {
        // 1. Handle added ownerships must add their owners

        $added_ownerships_ids = array_filter($values['ownerships_ids'], fn($ownership_id) => $ownership_id > 0);

        $added_ownerships = Ownership::ids($added_ownerships_ids)
            ->read(['owners_ids'])
            ->get();

        $map_owners_ids = [];
        foreach($added_ownerships as $ownership) {
            foreach($ownership['owners_ids'] as $owner_id) {
                $map_owners_ids[$owner_id] = true;
            }
        }

        $self->update(['owners_ids' => array_keys($map_owners_ids)]);

        // 2. Handle removed ownerships must remove their owners

        $removed_ownerships_ids = array_map('abs', array_filter($values['ownerships_ids'], fn($ownership_id) => $ownership_id < 0));

        $removed_ownerships = Ownership::ids($removed_ownerships_ids)
            ->read(['owners_ids'])
            ->get();

        $map_owners_ids = [];
        foreach($removed_ownerships as $ownership) {
            foreach($ownership['owners_ids'] as $owner_id) {
                $map_owners_ids[$owner_id * -1] = true;
            }
        }

        $self->update(['owners_ids' => array_keys($map_owners_ids)]);
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

