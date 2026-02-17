<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\PropertyLotApportionmentShare;


class AssemblyMandate extends \equal\orm\Model {

    public static function getColumns() {

        return [

            // #memo - `created` date could be used to prioritize the mandates in case the amount exceeds the maximum allowed number of mandates.

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'assembly_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly the proxy refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'attendee_id' => [
                'type'              => 'many2one',
                'description'       => "Attendee holder of the mandate.",
                'foreign_object'    => 'realestate\governance\AssemblyAttendee',
                'domain'            => [ ['condo_id', '=', 'object.condo_id'], ['assembly_id', '=', 'object.assembly_id']],
                'ondelete'          => 'null',
                // 'required'          => true,
                'dependents'        => ['identity_id']
            ],

            'identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'relation'          => ['attendee_id' => 'identity_id'],
                'description'       => "Person (natural or legal) holder of the proxy.",
                'foreign_object'    => 'identity\Identity',
                'store'             => true
            ],

            // #memo #todo - allow to pre-encoding of mandates FMT-175
            /*
            'holder_ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership holder of the proxy, if any.",
                'help'              => "This is for encoding in 2 passes: the holder can be an identity from one of the ownerships of the condominium
                    In such case, the mandates can be auto-populated at Attendee creation.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
            ],

            'holder_identity_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership holder of the proxy, if any.",
                'help'              => "This is for encoding in 2 passes: the holder can be an identity from one of the ownerships of the condominium
                    In such case, the mandates can be auto-puploated at Attendee creation.",
                'foreign_object'    => 'identity\Identity',
                'domain'            => [['condo_id', '=', 'object.condo_id'], []],
            ],
            */

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that is represented by the proxy .",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                // 'required'          => true,
                'dependents'        => ['mandate_shares']
            ],

            'mandate_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => "Date for which the proxy was granted (as stated on document).",
                'default'           => time()
            ],

            'mandate_type' => [
                'type'              => 'string',
                'selection'         => [
                    'written',
                    'electronic'
                ],
                'description'       => "Type of proxy.",
                'default'           => 'written'
            ],

            'mandate_document_id' => [
                'type'              => 'many2one',
                'description'       => "PDF scan or eID/itsme file.",
                'help'              => "In case of wet signature, this is a scan of the received document.
                    For electronic signatures, this is the pre-filled mandate original document.",
                'foreign_object'    => 'documents\Document',
                'required'          => false,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'mandate_shares' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Computed weight of the vote, based on shares and majority type (via assembly_item_id).",
                'function'          => 'calcMandateShares',
                'store'             => true
            ],

            'has_wet_signature' => [
                'type'              => 'boolean',
                'description'       => "Mark the mandate as having a handwritten signature.",
                'default'           => true
            ],

            'has_vote_intentions' => [
                'type'              => 'boolean',
                'description'       => "Mark the mandate as having a handwritten signature.",
                'default'           => false
            ],

            'signed_mandate_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Targets the final printable version of the document.',
                'help'              => 'Optional version of the document with signatures on it, applicable for signed documents only. Has no legal value.'
            ],

            'is_valid' => [
                'type'              => 'boolean',
                'description'       => 'Can be invalidated after verification.',
                'help'              => "This field is meant to describe the validity of the mandate according to legal rule (not consistency of the object, which is handled through `status`.)",
                'default'           => true
            ],

            'invalidity_reason' => [
                'type'              => 'string',
                'selection'         => [
                    'no_signature',          // Missing signature
                    'missing_or_wrong_date', // Missing or incorrect date
                    'invalid_signature',     // Invalid electronic signature
                    'invalid_document',      // Incomplete or incorrect form
                    'expired_or_mismatch',   // Proxy expired or was issued for another assembly
                    'too_many_proxies',      // Too many proxies per proxy holder
                    'duplicated_owner',      // Owner has granted more than one mandate
                    'not_owner',             // Grantor is not legitimate (not owner [anymore])
                    'invalid_attendee'       // Other : Attendee is not valid (missing ID or attendance signature)
                ],
                'description'       => "Reason for invalidity of the proxy (e.g. no signature, expired, too many mandates, etc.)",
                'visible'           => ['is_valid', '=', false]
            ],

            'status' => [
                'type'           => 'string',
                'description'    => "Workflow status of the assembly.",
                'default'        => 'pending',
                'selection'      => [
                    'pending',
                    'validated'
                ]
            ],

            'assembly_vote_intentions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyVoteIntention',
                'foreign_field'     => 'assembly_mandate_id',
                'description'       => 'The vote intentions related to the assembly.'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Validate a mandate (once all data have been received).',
                'icon' => 'sent',
                'transitions' => [
                    'validate' => [
                        'description'   => 'Marks the mandate as validated.',
                        'policies'      => ['can_validate'],
                        'onafter'       => 'onafterValidate',
                        'status'        => 'validated'
                    ]
                ]
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_validate' => [
                'description' => 'Verifies that the constituted assembly is valid, ',
                'help'        => "In order to be valid, the assembly must met the representation criteria depending on its type.",
                'function'    => 'policyCanValidate'
            ],
        ];
    }

    protected static function calcMandateShares($self) {
        $result = [];
        $self->read(['condo_id', 'ownership_id', 'assembly_id' => ['assembly_date']]);
        foreach($self as $id => $assemblyMandate) {
            // 1) identify the lots
            $property_lots_ids = [];

            $ownership = Ownership::id($assemblyMandate['ownership_id'])
                ->read(['property_lot_ownerships_ids' => ['property_lot_id', 'date_to']])
                ->first();

            foreach($ownership['property_lot_ownerships_ids'] as $propertyLotOwnership) {
                if(!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assemblyMandate['assembly_id']['assembly_date']) {
                    $property_lots_ids[] = $propertyLotOwnership['property_lot_id'];
                }
            }

            // 2) retrieve statutory apportionment
            $apportionment = Apportionment::search([
                    ['is_statutory', '=', true],
                    ['condo_id', '=', $assemblyMandate['condo_id']]
                ])
                ->first();

            if(!$apportionment) {
                trigger_error('APP::unexpected missing statutory apportionment for condo ' . $assemblyMandate['condo_id'], EQ_REPORT_ERROR);
                continue;
            }

            // 3) get the total shares for the targeted lots
            $apportionmentShares = PropertyLotApportionmentShare::search([
                    ['apportionment_id', '=', $apportionment['id']],
                    ['property_lot_id', 'in', $property_lots_ids]
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

    // on doit créer l'objet en deux temps : 1) avec uniquement le assembly_id, 2) avec toutes les infos (attendee_id et ownership_id)
    // à la validation il faut vérifier la cohérence
    protected static function policyCanValidate($self) {
        $result = [];
        $self->read(['status', 'attendee_id', 'ownership_id']);

        foreach($self as $id => $assemblyMandate) {

            if(!$assemblyMandate['attendee_id']) {
                $result[$id] = [
                    'missing_attendee' => 'The attendee must be provided.'
                ];
                continue;
            }
            if(!$assemblyMandate['ownership_id']) {
                $result[$id] = [
                    'missing_ownership' => 'The ownership must be provided.'
                ];
                continue;
            }
        }

        return $result;
    }

    /**
     * #memo - AssemblyRepresentations are re-built at mandates validation (in Assembly)
     */
    protected static function onafterValidate($self) {
        $self->read(['condo_id', 'assembly_id', 'attendee_id', 'ownership_id']);
        // Create Representations : add ownerships from valid mandates
        foreach($self as $id => $assemblyMandate) {
            if($assemblyMandate['ownership_id']) {
                $existingRepresentations = AssemblyRepresentation::search([
                        ['assembly_id', '=', $assemblyMandate['assembly_id']],
                        ['ownership_id', '=', $assemblyMandate['ownership_id']]
                    ]);
                if($existingRepresentations->count() > 0) {
                    continue;
                }
                AssemblyRepresentation::create([
                    'condo_id'              => $assemblyMandate['condo_id'],
                    'assembly_id'           => $assemblyMandate['assembly_id'],
                    'attendee_id'           => $assemblyMandate['attendee_id'],
                    'ownership_id'          => $assemblyMandate['ownership_id'],
                    'representation_type'   => 'proxy',
                    'assembly_mandate_id'   => $id
                ]);
            }
            Assembly::id($assemblyMandate['assembly_id'])->update(['count_represented_shares' => null, 'count_represented_owners' => null]);

            try {
                \eQual::run('do', 'realestate_governance_Assembly_check-quorum', ['id' => $assemblyMandate['assembly_id']]);
            }
            catch(\Exception $e) {
                // ignore in case of error (non critical)
                trigger_error("APP::Failed to check assembly quorum after validating a mandate: " . $e->getMessage(), EQ_REPORT_WARNING);
            }

        }
    }

}
