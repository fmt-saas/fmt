<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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
                'required'          => true
            ],

            'attendance_register_document_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => "Original (immutable) attendance register document.",
                'relation'          => ['assembly_id' => 'attendance_register_document_id'],
                'store'             => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name of the attendee (from identity).",
                'relation'          => ['identity_id' => 'name'],
                'store'             => true
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'description'       => "The identity the attendee relates to.",
                'foreign_object'    => 'identity\Identity',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'assembly_representations_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyRepresentation',
                'foreign_field'     => 'attendee_id',
                'description'       => "Validated representations held by this attendee for the assembly.",
                'help'              => "Representations are generated automatically, based on assembly_proxies_ids, and are used to link the attendee to the ownerships they represent in the assembly (directly or with a mandate).",
                'domain'            => [ ['condo_id', '=', 'object.condo_id'] ]
            ],

            'assembly_proxies_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyMandate',
                'foreign_field'     => 'attendee_id',
                'description'       => "Proxies held by this attendee for the assembly.",
                'help'              => "This field is used to complete the list of ownerships based on the proxies held by the attendee.",
                'visible'           => ['has_mandate', '=', true],
                'domain'            => [ ['condo_id', '=', 'object.condo_id'], ['assembly_id', '=', 'object.assembly_id']]
            ],

            'has_mandate' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the attendee has a mandate to represent one or more other ownerships.",
                'help'              => "This field simply indicates whether proxies have been presented but does not guarantee their validity.",
                'default'           => false
            ],

            'document_signature_id' => [
                'type'              => 'many2one',
                'description'       => "Signature made by the attendee, linked to original attendance register.",
                'foreign_object'    => 'documents\DocumentSignature',
                'domain'            => [
                        ['signer_identity_id', '=', 'object.identity_id'],
                        ['condo_id', '=', 'object.condo_id'],
                        ['document_id', '=', 'object.attendance_register_document_id']
                    ]
            ],

            'has_signed' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the attendee has signed the attendance sheet.",
                'default'           => false
            ],

            'shares' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "The number of statutory shares the attendee represents in the assembly.",
                'function'          => 'calcShares',
                'store'             => true,
                'readonly'          => true,
                'visible'           => ['status', '=', 'validated']
            ],

            'attendee_role' => [
                'type'              => 'string',
                'selection'         => [
                    'attendee',
                    'president',
                    'secretary'
                ],
                'description'       => "Additional information about the type of attendee.",
                'help'              => "This field is necessary in order to keep track of the mandatory roles.",
                'default'           => 'attendee'
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
                        'help'      => 'Validity check of mandates is performed at the Assembly level.',
                        'onbefore'    => 'onbeforeValidate',
                        'policies'    => [/*'is_valid'*/],
                        'status'      => 'validated'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return [

        ];
    }

    /**
     * Compute total statutory shares based on the lots of the ownerships represented by the Attendee (assembly_representations_ids is populated based on valid proxies)
     *
     */
    protected static function calcShares($self) {
        $result = [];
        $self->read(['status', 'condo_id', 'assembly_representations_ids' => ['ownership_id'], 'assembly_id' => ['assembly_date']]);

        foreach($self as $id => $assemblyAttendee) {
            if($assemblyAttendee['status'] !== 'validated') {
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


    protected static function onbeforeValidate($self) {
        // #todo - on doit valider la cohérence de l'attendee
    }

}
