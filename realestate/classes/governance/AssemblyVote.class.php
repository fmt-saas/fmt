<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\governance;

use realestate\ownership\Ownership;
use realestate\property\PropertyLotApportionmentShare;

class AssemblyVote extends \equal\orm\Model {

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

            'assembly_item_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly item this vote refers to.",
                'foreign_object'    => 'realestate\governance\AssemblyItem',
                'required'          => true,
                'dependents'       => ['vote_weight']
            ],

            'assembly_attendee_id' => [
                'type'              => 'many2one',
                'description'       => "The attendee who cast the vote (possibly via proxy).",
                'foreign_object'    => 'realestate\governance\AssemblyAttendee',
                'required'          => true
            ],

            'has_proxy' => [
                'type'              => 'boolean',
                'description'       => 'Mark a vote as "represented" (vote by proxy), or "Present".',
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership concerned by the vote, via one of its lots.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true
            ],

            'vote_value' => [
                'type'              => 'string',
                'description'       => "Vote value: 'for', 'against', or 'abstain'.",
                'selection'         => ['for', 'against', 'abstain'],
                'required'          => true
            ],

            'vote_weight' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Computed weight of the vote, based on shares and majority type (via assembly_item_id).",
                'function'          => 'calcVoteWeight',
                'store'             => true
            ]

        ];
    }

    /**
     * Calculate the vote weight based on the shares of the property lots of the ownership
     * for the the related apportionment at the moment of the assembly.
     *
     */
    protected static function calcVoteWeight($self) {
        $result = [];
        $self->read(['ownership_id', 'assembly_id' => ['assembly_date'], 'assembly_item_id' => ['apportionment_id']]);

        foreach($self as $id => $assemblyVote) {
            // 1) identify the lots
            $property_lots_ids = [];

            $ownership = Ownership::ids($assemblyVote['ownership_id'])
                ->read(['property_lot_ownerships_ids' => ['property_lot_id', 'date_to']]);

            foreach($ownership['property_lot_ownerships_ids'] as $propertyLotOwnership) {
                if(!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assemblyVote['assembly_id']['assembly_date']) {
                    $property_lots_ids[] = $propertyLotOwnership['property_lot_id'];
                }
            }

            // 2) get the total shares for the targeted lots
            $apportionmentShares = PropertyLotApportionmentShare::search([
                    ['apportionment_id', '=', $assemblyVote['assembly_item_id']['apportionment_id']],
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
}
