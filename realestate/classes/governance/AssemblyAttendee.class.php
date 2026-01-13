<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\PropertyLotApportionmentShare;

class AssemblyAttendee extends \equal\orm\Model {

    public static function getDescription() {
        return "Represents an attendee at a condominium assembly, which can be an owner or a proxy (not necessarily an owner), or an owner with proxy from other owners.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'assembly_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly the invitation refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name of the attendee (from identity).",
                'function'          => 'calcName',
                'store'             => true
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'description'       => "The identity the attendee relates to.",
                'foreign_object'    => 'identity\Identity',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'is_owner' => [
                'type'              => 'boolean',
                'description'       => "Mark the attendee as owner.",
                'default'           => true
            ],

            'assembly_representations_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyRepresentation',
                'foreign_field'     => 'attendee_id',
                'description'       => "Validated representations held by this attendee for the assembly.",
                'help'              => "Representations are generated automatically, based on assembly_mandates_ids, and are used to link the attendee to the ownerships they represent in the assembly (directly or with a mandate).",
                'domain'            => [ ['condo_id', '=', 'object.condo_id'] ]
            ],

            'assembly_mandates_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyMandate',
                'foreign_field'     => 'attendee_id',
                'description'       => "Mandates held by this attendee for the assembly.",
                'help'              => "This field is used to complete the list of ownerships based on the mandates held by the attendee.",
                'visible'           => ['has_mandate', '=', true],
                'domain'            => [ ['condo_id', '=', 'object.condo_id'], ['assembly_id', '=', 'object.assembly_id']]
            ],

            'has_mandate' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the attendee has a mandate to represent other owner(s).",
                'help'              => "If true, the attendee holds one or more mandate(s) to represent one or more other ownerships.
                    This field simply indicates whether mandates have been presented but does not guarantee their validity.",
                'default'           => false
            ],

            'has_representation' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcHasRepresentation',
                'description'       => 'Indicates whether the attendee represents at least one Ownership.',
                'help'              => "Some attendee might be allowed ut not meant to cast any vote (e.g. secretary).",
                'store'             => true,
                'instant'           => true
            ],

            'register_document_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => "Original (immutable) attendance register document.",
                'relation'          => ['assembly_id' => 'register_document_id'],
                'store'             => true
            ],

            'register_document_signature_id' => [
                'type'              => 'many2one',
                'description'       => "Signature made by the attendee, linked to original attendance register.",
                'foreign_object'    => 'documents\DocumentSignature',
                'domain'            => [
                        ['signer_identity_id', '=', 'object.identity_id'],
                        ['condo_id', '=', 'object.condo_id'],
                        ['document_id', '=', 'object.register_document_id']
                    ],
                'visible'           => ['has_signed_register', '=', true]
            ],

            'minutes_document_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => "Original (immutable) assembly minutes document.",
                'relation'          => ['assembly_id' => 'minutes_document_id'],
                'store'             => true
            ],

            'minutes_document_signature_id' => [
                'type'              => 'many2one',
                'description'       => "Signature made by the attendee, linked to original attendance register.",
                'foreign_object'    => 'documents\DocumentSignature',
                'domain'            => [
                        ['signer_identity_id', '=', 'object.identity_id'],
                        ['condo_id', '=', 'object.condo_id'],
                        ['document_id', '=', 'object.minutes_document_id']
                    ],
                'visible'           => ['has_signed_minutes', '=', true]
            ],

            'has_signed_register' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the attendee has signed the attendance sheet.",
                'default'           => false
            ],

            'has_signed_minutes' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the attendee has signed the assembly minutes.",
                'default'           => false
            ],

            // #memo this has been disabled because it is already verified in Assembly, and it has no meaning while there are no representations
            /*
            'shares' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "The number of statutory shares the attendee represents in the assembly.",
                'function'          => 'calcShares',
                'store'             => true,
                'readonly'          => true,
                'visible'           => ['status', '=', 'validated']
            ],
            */

            'attendee_role' => [
                'type'              => 'string',
                'selection'         => [
                    'attendee',
                    'president',
                    'secretary'
                ],
                'description'       => "Additional information about the type of attendee.",
                'help'              => "This field is necessary in order to keep track of the mandatory roles.",
                'default'           => 'attendee',
                'dependents'        => ['name']
            ],

            'is_valid' => [
                'type'              => 'boolean',
                'description'       => "Can be invalidated after verification.",
                'default'           => true
            ],

            'invalidity_reason' => [
                'type'        => 'string',
                'selection'   => [
                    'invalid_attendee',      // Attendee not designated or not authorized
                    'double_attendance',     // The representation is redundant (owner prevails if present)
                    'missing_mandate',       // Joint ownership without mandate
                    'conflict'               // disagreement between 2 or more owners from a joint ownership
                ],
                'description' => "Reason for invalidity of the Representation.",
                'visible'     => ['is_valid', '=', false]
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'validated'
                ],
                'description'       => "Workflow status of the assembly attendee.",
                'default'           => 'pending'
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Attendee is pending validation.',
                'icon'        => 'done',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the Attendee to `validated`.',
                        'help'        => 'Validity check of mandates is performed at the Assembly level.',
                        'policies'    => ['is_attendee_valid'],
                        'status'      => 'validated'
                    ]
                ]
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_attendee_valid' => [
                'description' => 'Verifies that the encoded attendee is valid.',
                'help'        => "In order to be valid, the assembly must meet the representation criteria depending on its type.",
                'function'    => 'policyIsAttendeeValid'
            ],
            'can_promote_president' => [
                'description' => 'Verifies that the attendee can be promoted/marked as president of the Assembly.',
                'help'        => "In order to be valid, the assembly must meet the representation criteria depending on its type.",
                'function'    => 'policyCanPromotePresident'
            ]
        ];
    }

    public static function getActions() {
        return [
            'promote_president' => [
                'description'   => 'Assign location based on Condominium address.',
                'policies'      => ['can_promote_president'],
                'function'      => 'doPromotePresident'
            ],
            'demote_president' => [
                'description'   => 'Assign location based on Condominium address.',
                'policies'      => [],
                'function'      => 'doDemotePresident'
            ]
        ];
    }


    protected static function doPromotePresident($self) {
        $self->update(['attendee_role' => 'president']);
    }

    protected static function doDemotePresident($self) {
        $self->update(['attendee_role' => 'attendee']);
    }

    protected static function policyCanPromotePresident($self) {
        $result = [];
        $self->read(['attendee_role', 'assembly_id']);

        foreach($self as $id => $assemblyAttendee) {
            if($assemblyAttendee['attendee_role'] === 'president') {
                continue;
            }

            $presidentAttendee = self::search([
                    ['assembly_id', '=', $assemblyAttendee['assembly_id']],
                    ['attendee_role', '=', 'president']
                ])
                ->first();

            if($presidentAttendee) {
                $result[$id] = [
                    'presidency_already_assigned' => 'An Attendee is already marked as president.'
                ];
                continue;
            }

        }

        return $result;
    }

    protected static function policyIsAttendeeValid($self) {
        $result = [];
        $self->read(['condo_id', 'identity_id' => ['owners_ids'], 'has_mandate', 'attendee_role']);

        foreach($self as $id => $assemblyAttendee) {
            // either an owner, or someone holding a mandate
            if(!$assemblyAttendee['has_mandate'] && !in_array($assemblyAttendee['attendee_role'], ['president', 'secretary'], true)) {
                $owners_ids = Owner::search([
                        ['id', 'in', $assemblyAttendee['identity_id']['owners_ids']],
                        ['condo_id', '=', $assemblyAttendee['condo_id']]
                    ])
                    ->ids();

                if(count($owners_ids) <= 0) {
                    $result[$id] = [
                        'not_an_owner' => 'Attendee has no mandate nor is Owner.'
                    ];
                    continue;
                }
            }
        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['attendee_role', 'identity_id' => ['name']]);
        foreach($self as $id => $assemblyAttendee) {
            $name = $assemblyAttendee['identity_id']['name'];
            if($assemblyAttendee['attendee_role'] === 'president') {
                $name .= ' (Président) ';
            } elseif($assemblyAttendee['attendee_role'] === 'secretary') {
                $name .= ' (Secrétaire)';
            }
            $result[$id] = $name;
        }
        return $result;
    }

    protected static function calcHasRepresentation($self) {
        $result = [];
        $self->read(['status', 'assembly_representations_ids']);
        foreach($self as $id => $assemblyAttendee) {
            if($assemblyAttendee['status'] !== 'validated') {
                continue;
            }
            $result[$id] = (bool) count($assemblyAttendee['assembly_representations_ids']);
        }
        return $result;
    }

    /**
     * Compute total statutory shares based on the lots of the ownerships represented by the Attendee (assembly_representations_ids is populated based on valid mandates)
     *
     */
    // #memo this has been disabled because it is already verified in Assembly, and it has no meaning while there are no representations
    protected static function calcShares($self) {
        $result = [];
        $self->read([
                'status', 'condo_id',
                'assembly_representations_ids' => ['ownership_id'],
                'assembly_id' => ['status', 'step', 'assembly_date']
            ]);

        foreach($self as $id => $assemblyAttendee) {
            if($assemblyAttendee['status'] !== 'validated') {
                continue;
            }

            if($assemblyAttendee['assembly_representations_ids']->count() <= 0) {
                continue;
            }

            $ownerships_ids = [];
            foreach($assemblyAttendee['assembly_representations_ids'] as $assemblyRepresentation) {
                $assemblyRepresentation['ownership_id'];
            }

            // 1) identify the lots
            $property_lots_ids = [];

            $ownerships = Ownership::ids($ownerships_ids)
                ->read(['property_lot_ownerships_ids' => ['property_lot_id', 'date_to']]);

            foreach($ownerships as $ownership) {
                foreach($ownership['property_lot_ownerships_ids'] as $propertyLotOwnership) {
                    if(!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assemblyAttendee['assembly_id']['assembly_date']) {
                        $property_lots_ids[] = $propertyLotOwnership['property_lot_id'];
                    }
                }
            }

            // 2) find the statutory key
            $apportionment = Apportionment::search([
                    ['condo_id', '=', $assemblyAttendee['condo_id']],
                    ['is_statutory', '=', true]
                ])
                ->first();

            // 3) get the total shares for the targeted lots
            if(!$apportionment) {
                continue;
            }

            $apportionmentShares = PropertyLotApportionmentShare::search([
                    ['apportionment_id', '=', $apportionment['id']],
                    ['property_lot_id', 'in', $property_lots_ids],
                ])
                ->read(['property_lot_shares']);

            $shares = 0;
            foreach($apportionmentShares as $apportionmentShare) {
                $shares += $apportionmentShare['property_lot_shares'];
            }

            $result[$id] = $shares;
        }
        return $result;
    }

}
