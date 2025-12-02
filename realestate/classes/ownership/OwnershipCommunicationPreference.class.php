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

            'identity_id' => [
                'type'              => 'many2one',
                'description'       => "Identity of an external person.",
                'foreign_object'    => 'identity\Identity',
                'domain'            => ['type_id', '=', 1],
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


    /**
     * #memo - we allow only several identity for a same communication reason for the `email` channel.
     *
     */
    protected static function canupdate($self) {
        $self->read([
                'identity_id',
                'communication_reason',
                'has_channel_postal',
                'has_channel_postal_registered',
                'has_channel_postal_registered_receipt',
                'ownership_id' => ['representative_identity_id', 'owners_ids' => ['identity_id']]
            ]);

        foreach($self as $id => $ownershipCommunicationPreference) {
            $ownership_id = $ownershipCommunicationPreference['ownership_id']['id'];
            if($ownershipCommunicationPreference['has_channel_postal']
                || $ownershipCommunicationPreference['has_channel_postal_registered']
                || $ownershipCommunicationPreference['has_channel_postal_registered_receipt']) {
                    $postalMailPreferences = self::search([
                        [
                            ['ownership_id', '=', $ownership_id],
                            ['communication_reason', '=', $ownershipCommunicationPreference['communication_reason']],
                            ['has_channel_postal', '=', true]
                        ],
                        [
                            ['ownership_id', '=', $ownership_id],
                            ['communication_reason', '=', $ownershipCommunicationPreference['communication_reason']],
                            ['has_channel_postal_registered', '=', true]
                        ],
                        [
                            ['ownership_id', '=', $ownership_id],
                            ['communication_reason', '=', $ownershipCommunicationPreference['communication_reason']],
                            ['has_channel_postal_registered_receipt', '=', true]
                        ]
                    ]);
                if($postalMailPreferences->count() > 1) {
                    return ['communication_reason' => ['not_allowed' => 'Only a single postal courier is allowed per communication reason.']];
                }
            }


            $identity_id = $ownershipCommunicationPreference['identity_id'];
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

        return parent::canupdate($self);
    }

    protected static function onafterupdate($self, $dispatch) {
        $self->read([
                'identity_id',
                'ownership_id',
                'has_channel_email'
            ]);

        foreach($self as $id => $ownershipCommunicationPreference) {
            if($ownershipCommunicationPreference['has_channel_email']) {
                $identity = Identity::id($ownershipCommunicationPreference['identity_id'])
                    ->read(['email','email_alt']);
                if(!$identity['email'] && !$identity['email_alt']) {
                    $dispatch->dispatch('realestate.workflow.ownership.invalid_communication_prefs', self::getType(), $ownershipCommunicationPreference['ownership_id'], 'warning');
                    $dispatch->dispatch('realestate.workflow.communication_prefs.email_missing', self::getType(), $id, 'warning');
                }
            }
        }

    }

}