<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\ownership;

use identity\Identity;

class OwnershipCommunicationPreference extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the ownership belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'alias',
                'alias'             => 'communication_reason',
                'description'       => 'Name of the communication preference.'
            ],

            'communication_title' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Title to use for addressing, used to send the invitation.",
                'relation'          => ['ownership_id' => 'address_recipient'],
                'store'             => false,
                'readonly'          => true
            ],

            'communication_reason' => [
                'type'              => 'string',
                'selection'         => [
                    'general_assembly_call',
                    'general_assembly_minutes',
                    'expense_statement',
                    'fund_request',
                    'technical_communication'
                ],
                'description'       => "Method used to send the invitation.",
                'required'          => true
            ],

            'has_channel_email' => [
                'type'              => 'boolean',
                'description'       => "Mark the preference for email as communication channel.",
                'default'           => true
            ],

            'has_channel_postal' => [
                'type'              => 'boolean',
                'description'       => "Mark the preference for courier as communication channel.",
                'default'           => false
            ],

            'has_channel_postal_registered' => [
                'type'              => 'boolean',
                'description'       => "Mark the preference for registered courier as communication channel.",
                'default'           => false
            ],

            'has_channel_postal_registered_receipt' => [
                'type'              => 'boolean',
                'description'       => "Mark the preference for registered courier + receipt as communication channel.",
                'default'           => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true,
                'readonly'          => true,
                'dependents'        => ['communication_title']
            ],

            'is_owner' => [
                'type'              => 'boolean',
                'description'       => "Mark the recipient as an owner (from ownership).",
                'default'           => true
            ],

            'owner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Owner',
                'description'       => 'The Owner the communication preference refers to.',
                'visible'           => ['is_owner', '=', true],
                'domain'            => [ ['condo_id', '=', 'object.condo_id'], ['ownership_id', '=', 'object.ownership_id'] ],
                'help'              => 'This field might be empty in case of an external representant (not an owner)',
                'onupdate'          => 'onupdateOwnerId',
                'oncreate'          => 'onupdateOwnerId'
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'description'       => "Identity of an external person.",
                'foreign_object'    => 'identity\Identity',
                'visible'           => ['is_owner', '=', false],
                'dependents'        => ['name', 'email', 'address_street', 'address_city', 'address_zip']
            ],

            'email' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'email',
                'relation'          => ['identity_id' => 'email'],
                'store'             => true,
                'description'       => "Identity main email address."
            ],

            'email_alt' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'email',
                'relation'          => ['identity_id' => 'email_alt'],
                'store'             => true,
                'description'       => "Identity secondary email address."
            ],

            'address_street' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['identity_id' => 'address_street'],
                'store'             => true,
                'description'       => 'Street and number.',
                'visible'           => [
                    [
                        ['has_channel_postal', '=', true]
                    ],
                    [
                        ['has_channel_postal_registered', '=', true]
                    ],
                    [
                        ['has_channel_postal_registered_receipt', '=', true]
                    ]
                ]
            ],

            'address_city' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['identity_id' => 'address_city'],
                'store'             => true,
                'description'       => 'City.',
                'visible'           => [
                    [
                        ['has_channel_postal', '=', true]
                    ],
                    [
                        ['has_channel_postal_registered', '=', true]
                    ],
                    [
                        ['has_channel_postal_registered_receipt', '=', true]
                    ]
                ]
            ],

            'address_zip' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['identity_id' => 'address_zip'],
                'store'             => true,
                'description'       => 'Postal code.',
                'visible'           => [
                    [
                        ['has_channel_postal', '=', true]
                    ],
                    [
                        ['has_channel_postal_registered', '=', true]
                    ],
                    [
                        ['has_channel_postal_registered_receipt', '=', true]
                    ]
                ]
            ]

        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'remove' => [
                'description'   => 'Remove the communication preference.',
                'policies'      => ['can_remove'],
                'function'      => 'doRemove'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_remove' => [
                'description' => 'Checks that another preference remains for the same communication reason.',
                'function'    => 'policyCanRemove'
            ]
        ]);
    }

    protected static function onupdateOwnerId($self) {
        $self->read(['state', 'owner_id' => ['identity_id']]);
        foreach($self as $id => $ownershipCommunicationPreference) {
            if($ownershipCommunicationPreference['owner_id']) {
                self::id($id)->update([
                    'state'         => $ownershipCommunicationPreference['state'],
                    'identity_id'   => $ownershipCommunicationPreference['owner_id']['identity_id']
                ]);
            }
        }
    }

    /**
     * #memo - we allow only several identity for a same communication reason for the `email` channel.
     *
     */
    protected static function canupdate($self, $values) {
        $self->read([
                'identity_id',
                'communication_reason',
                'has_channel_email',
                'has_channel_postal',
                'has_channel_postal_registered',
                'has_channel_postal_registered_receipt',
                'ownership_id' => ['id', 'representative_identity_id', 'owners_ids' => ['identity_id']]
            ]);

        foreach($self as $id => $ownershipCommunicationPreference) {
            $ownership_id = $ownershipCommunicationPreference['ownership_id']['id'];
            $identity_id = array_key_exists('identity_id', $values)
                ? $values['identity_id']
                : $ownershipCommunicationPreference['identity_id'];

            // When an owner is selected, the related identity is synchronized by onupdateOwnerId after validation.
            // Resolve it now so that canupdate validates the values that will actually be stored.
            if(!empty($values['owner_id'])) {
                $owner = Owner::id($values['owner_id'])
                    ->read(['identity_id'])
                    ->first();
                $identity_id = $owner['identity_id'] ?? null;
            }

            $has_channel_email = array_key_exists('has_channel_email', $values)
                ? $values['has_channel_email']
                : $ownershipCommunicationPreference['has_channel_email'];
            if($has_channel_email) {
                $identity = $identity_id
                    ? Identity::id($identity_id)->read(['email', 'email_alt'])->first()
                    : null;
                if(!$identity || (empty($identity['email']) && empty($identity['email_alt']))) {
                    return ['has_channel_email' => ['email_missing' => 'An email address is required when email is used as communication channel.']];
                }
            }

            $communication_reason = array_key_exists('communication_reason', $values)
                ? $values['communication_reason']
                : $ownershipCommunicationPreference['communication_reason'];
            $has_channel_postal = array_key_exists('has_channel_postal', $values)
                ? $values['has_channel_postal']
                : $ownershipCommunicationPreference['has_channel_postal'];
            $has_channel_postal_registered = array_key_exists('has_channel_postal_registered', $values)
                ? $values['has_channel_postal_registered']
                : $ownershipCommunicationPreference['has_channel_postal_registered'];
            $has_channel_postal_registered_receipt = array_key_exists('has_channel_postal_registered_receipt', $values)
                ? $values['has_channel_postal_registered_receipt']
                : $ownershipCommunicationPreference['has_channel_postal_registered_receipt'];

            if($has_channel_postal
                || $has_channel_postal_registered
                || $has_channel_postal_registered_receipt) {
                $postalMailPreferences = self::search([
                    [
                        ['id', '<>', $id],
                        ['ownership_id', '=', $ownership_id],
                        ['identity_id', '=', $identity_id],
                        ['communication_reason', '=', $communication_reason],
                        ['has_channel_postal', '=', true]
                    ],
                    [
                        ['id', '<>', $id],
                        ['ownership_id', '=', $ownership_id],
                        ['identity_id', '=', $identity_id],
                        ['communication_reason', '=', $communication_reason],
                        ['has_channel_postal_registered', '=', true]
                    ],
                    [
                        ['id', '<>', $id],
                        ['ownership_id', '=', $ownership_id],
                        ['identity_id', '=', $identity_id],
                        ['communication_reason', '=', $communication_reason],
                        ['has_channel_postal_registered_receipt', '=', true]
                    ]
                ]);
                if($postalMailPreferences->count() > 0) {
                    trigger_error("APP::Duplicate found while checking Ownership[{$ownership_id}] for {$communication_reason} on {$identity_id}", EQ_REPORT_WARNING);
                    return ['communication_reason' => ['not_allowed' => 'Only a single postal courier is allowed per communication reason.']];
                }
            }

            $found = false;
            foreach($ownershipCommunicationPreference['ownership_id']['owners_ids'] as $owner_id => $owner) {
                if($owner['identity_id'] === $identity_id) {
                    $found = true;
                    break;
                }
            }
            if(!$found && $ownershipCommunicationPreference['ownership_id']['representative_identity_id'] === $identity_id) {
                $found = true;
            }

            if(!$found) {
                return ['identity_id' => ['not_allowed' => 'Identity does not relate to any owner or representative.']];
            }

        }

        return parent::canupdate($self, $values);
    }

    protected static function policyCanRemove($self): array {
        $result = [];
        $self->read(['ownership_id', 'communication_reason']);
        $removed_ids = $self->ids();

        foreach($self as $id => $ownershipCommunicationPreference) {
            $remainingPreferences = self::search([
                ['ownership_id', '=', $ownershipCommunicationPreference['ownership_id']],
                ['communication_reason', '=', $ownershipCommunicationPreference['communication_reason']],
                ['id', 'not in', $removed_ids]
            ]);

            if($remainingPreferences->count() <= 0) {
                $result[$id] = [
                    'last_preference_for_communication_reason' => 'At least one communication preference must remain for each communication reason.'
                ];
            }
        }

        return $result;
    }

    protected static function doRemove($self) {
        $self->delete();
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['has_channel_postal']) && $event['has_channel_postal']) {
            $result['has_channel_postal_registered'] = false;
            $result['has_channel_postal_registered_receipt'] = false;
        }

        if(isset($event['has_channel_postal_registered']) && $event['has_channel_postal_registered']) {
            $result['has_channel_postal'] = false;
            $result['has_channel_postal_registered_receipt'] = false;
        }

        if(isset($event['has_channel_postal_registered_receipt']) && $event['has_channel_postal_registered_receipt']) {
            $result['has_channel_postal'] = false;
            $result['has_channel_postal_registered'] = false;
        }

        return $result;
    }

}
