<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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

            'identity_id' => [
                'type'              => 'many2one',
                'description'       => "The identity the attendee relates to.",
                'foreign_object'    => 'identity\Identity',
                'required'          => true
            ],

            'ownerships_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'foreign_field'     => 'attendees_ids',
                'rel_table'         => 'realestate_governance_attendee_rel_ownership',
                'rel_foreign_key'   => 'ownership_id',
                'rel_local_key'     => 'attendee_id',
                'description'       => "Ownerships represented by this attendee.",
                'help'              => "This field is generated automatically and is used to link the attendee to the ownerships they represent in the assembly."
            ],

            'assembly_proxies_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\governance\AssemblyProxy',
                'foreign_field'     => 'attendee_id',
                'description'       => "Proxies held by this attendee for the assembly.",
                'help'              => "This field is used to generate the list of ownerships based on the proxies held by the attendee."
            ],

            'has_mandate' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the attendee has a mandate to represent one or more other ownerships.",
                'default'           => false
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
                'store'             => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'validated'
                ],
                'description'       => "Workflow status of the assembly attendee.",
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
            'generate_ownerships_ids' => [
                'description'   => 'Generate ownerships_ids based on the identity_id of the attendee.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doGenerateOwnershipsIds'
            ],
        ];
    }

    /**
     * Compute total statutory shares based on the lots of the ownerships represented by the Attendee (ownerships_ids is populated based on valid proxies)
     * Note : ownership_ids must be generated beforehand.
     */
    protected static function calcShares($self) {
        $result = [];
        $self->read(['status', 'condo_id', 'assembly_id' => ['assembly_date'], 'ownerships_ids']);
        foreach($self as $id => $assemblyAttendee) {
            if($assemblyAttendee['status'] !== 'validated') {
                continue;
            }
            $property_lots_ids = [];

            // 1) identify the lots
            $ownerships = Ownership::ids($assemblyAttendee['ownerships_ids'])
                ->read(['property_lot_ownerships_ids' => ['property_lot_id', 'date_to']]);

            foreach($ownerships as $ownership) {
                foreach($ownership['property_lot_ownerships_ids'] as $propertyLotOwnership) {
                    if(!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assemblyAttendee['assembly_id']['assembly_date']) {
                        $property_lots_ids[] = $propertyLotOwnership['property_lot_id'];
                    }
                }
            }

            // 2) find the statutory key
            $apportionment = Apportionment::search([['condo_id', '=', $assemblyAttendee['condo_id']], ['is_statutory', '=', true]])->first();

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
        $self->do('generate_ownerships_ids');
    }

    /**
     * Attendees are created upon their arrival at the assembly.
     * An attendee can be a proxy for multiple ownerships, so we need to ensure that the ownerships_ids field is properly managed.
     */
    protected static function doGenerateOwnershipsIds($self) {
        $self->read(['condo_id', 'identity_id', 'ownerships_ids', 'assembly_proxies_ids' => ['is_valid', 'ownership_id']]);
        foreach($self as $id => $assemblyAttendee) {
            // empty the ownerships_ids field
            $ids_to_remove = array_map(function($a) {return -$a;}, $assemblyAttendee['ownerships_ids']);
            self::id($id)->update([
                'ownerships_ids' => $ids_to_remove
            ]);

            // 1) find the ownership corresponding to the identity for the condominium
            $owners = Owner::search([['condo_id', '=', $assemblyAttendee['condo_id']], ['identity_id', '=', $assemblyAttendee['identity_id']]])
                ->read(['ownership_id']);

            $ids_to_add = [];
            foreach($owners as $owner) {
                $ids_to_add[] = $owner['ownership_id'];
            }

            // 2) add all ownerships from proxies
            foreach($assemblyAttendee['assembly_proxies_ids'] as $assemblyProxy) {
                if($assemblyProxy['is_valid'] && $assemblyProxy['ownership_id']) {
                    $ids_to_add[] = $assemblyProxy['ownership_id'];
                }
            }

            // update ownerships_ids based on the identity
            self::id($id)->update([
                'ownerships_ids' => $ids_to_add
            ]);

        }
    }
}
