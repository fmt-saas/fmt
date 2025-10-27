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

class AssemblyVoteIntention extends \equal\orm\Model {

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
                'dependents'        => ['vote_weight', 'is_choice']
            ],

            'is_choice' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['assembly_item_id' => 'has_choices'],
                'description'       => 'Does the vote relate to a choice.',
                'default'           => false
            ],

            'assembly_item_choice_id' => [
                'type'              => 'many2one',
                'description'       => "The choice this vote refers to, if any.",
                'help'              => "By convention, a choice is always specified for votes marked with `is_choice`. In this case, the vote value is forced to 'for'.",
                'foreign_object'    => 'realestate\governance\AssemblyItemChoice',
                'visible'           => ['is_choice', '=', true]
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership concerned by the vote, via one of its lots.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true
            ],

            'vote_value' => [
                'type'              => 'string',
                'description'       => "Vote value ('for', 'against', or 'abstain').",
                'selection'         => [
                    'for',
                    'against',
                    'abstain'
                ],
                'default'           => 'abstain',
                'dependents'        => ['vote_weight_for', 'vote_weight_against', 'vote_weight_abstain']
            ],

            'vote_weight' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'number/real:6.5',
                'description'       => "Computed weight of the vote, based on shares and majority type.",
                'function'          => 'calcVoteWeight',
                'store'             => true
            ],

            'vote_shares' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Shares implied by the Attendee that casted the vote.",
                'help'              => "Number of shares, based on the applicable allocation key and the owner in whose name the vote is cast.",
                'function'          => 'calcVoteShares',
                'store'             => true
            ]


        ];
    }


    protected static function calcVoteShares($self) {
        $result = [];
        $self->read(['assembly_id' => ['assembly_date'], 'assembly_item_id' => ['apportionment_id'], 'ownership_id']);
        foreach($self as $id => $assemblyVote) {

            if(!isset($assemblyVote['ownership_id'], $assemblyVote['assembly_id'])) {
                continue;
            }

            // 1) identify the lots
            $property_lots_ids = [];

            $ownership = Ownership::id($assemblyVote['ownership_id'])
                ->read(['property_lot_ownerships_ids' => ['property_lot_id', 'date_to']])
                ->first();

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


    /**
     * Calculate the vote weight based on the shares of the property lots of the ownership,
     * for the the related apportionment at the moment of the assembly.
     *
     */
    protected static function calcVoteWeight($self) {
        $result = [];
        $self->read(['assembly_id' => ['status', 'step', 'assembly_date'], 'vote_effective_shares', 'assembly_item_id' => ['count_represented_shares']]);

        foreach($self as $id => $assemblyVote) {
            if($assemblyVote['assembly_id']['status'] !== 'in_progress' || $assemblyVote['assembly_id']['step'] !== 'agenda_processing') {
                continue;
            }
            if($assemblyVote['vote_effective_shares'] <= 0) {
                $result[$id] = 0;
            }
            else {
                $result[$id] = round($assemblyVote['vote_effective_shares'] / $assemblyVote['assembly_item_id']['count_represented_shares'], 5);
            }
        }
        return $result;
    }

}
