<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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
                'dependents'        => ['vote_weight', 'is_choice', 'vote_shares', 'vote_effective_shares']
            ],

            'cast_by' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'visible'           => ['status', '=', 'casted']
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
                'description'       => "The choice this vote adds up to, if any.",
                'help'              => "By convention, a choice is always specified for votes marked with `is_choice`. In this case, the vote value is forced to 'for'.",
                'foreign_object'    => 'realestate\governance\AssemblyItemChoice',
                'visible'           => ['is_choice', '=', true]
            ],

            'assembly_attendee_id' => [
                'type'              => 'many2one',
                'description'       => "The attendee who cast the vote (possibly as a proxy holder).",
                'foreign_object'    => 'realestate\governance\AssemblyAttendee',
                'required'          => true,
                'dependents'        => ['has_mandate']
            ],

            'has_mandate' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Mark a vote as "represented" (vote by proxy), or "present".',
                'stored'            => true,
                'function'          => 'calcHasMandate'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership concerned by the vote, via one of its lots.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true,
                'dependents'        => ['vote_shares', 'vote_effective_shares']
            ],

            'vote_display' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Printable choice/value of the Vote.",
                'function'          => 'calcVoteDisplay',
                'store'             => false
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
                'dependents'        => [
                    'vote_weight_for', 'vote_weight_against', 'vote_weight_abstain',
                    'vote_shares_for', 'vote_shares_against', 'vote_shares_abstain'
                ],
                'visible'           => ['status', '=', 'casted']
            ],

            'vote_weight' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'number/real:6.5',
                'description'       => "Computed weight of the vote, based on shares and majority type.",
                'function'          => 'calcVoteWeight',
                'store'             => true
            ],

            'vote_weight_for' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'number/real:6.5',
                'description'       => "Weight of the vote, if the vote was 'for'.",
                'help'              => "This is used to ease the reading of the results.",
                'function'          => 'calcVoteWeightFor',
                'store'             => true,
                'visible'           => ['status', '=', 'casted']
            ],

            'vote_weight_against' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'number/real:6.5',
                'description'       => "Weight of the vote, if the vote was 'against'.",
                'help'              => "This is used to ease the reading of the results.",
                'function'          => 'calcVoteWeightAgainst',
                'store'             => true,
                'visible'           => ['status', '=', 'casted']
            ],

            'vote_weight_abstain' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'number/real:6.5',
                'description'       => "Weight of the vote, if the vote was 'abstain'.",
                'help'              => "This is used to ease the reading of the results.",
                'function'          => 'calcVoteWeightAbstain',
                'store'             => true,
                'visible'           => ['status', '=', 'casted']
            ],

            'vote_shares_for' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Shares of the vote, if the vote was 'for'.",
                'help'              => "This is used to ease the reading of the results.",
                'function'          => 'calcVoteSharesFor',
                'store'             => true,
                'visible'           => ['status', '=', 'casted']
            ],

            'vote_shares_against' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Shares of the vote, if the vote was 'against'.",
                'help'              => "This is used to ease the reading of the results.",
                'function'          => 'calcVoteSharesAgainst',
                'store'             => true,
                'visible'           => ['status', '=', 'casted']
            ],

            'vote_shares_abstain' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Shares of the vote, if the vote was 'abstain'.",
                'help'              => "This is used to ease the reading of the results.",
                'function'          => 'calcVoteSharesAbstain',
                'store'             => true,
                'visible'           => ['status', '=', 'casted']
            ],

            'vote_shares' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Shares implied by the Attendee that casted the vote.",
                'help'              => "Number of shares, based on the applicable allocation key and the owner in whose name the vote is cast.",
                'function'          => 'calcVoteShares',
                'store'             => true
            ],

            'vote_effective_shares' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Caped shares of the vote, based on applicable limits.",
                'help'              => "Effective shares represented, with a maximum of 50% of the shares represented by the assembly.",
                'function'          => 'calcVoteEffectiveShares',
                'store'             => true
            ],

            'status' => [
                'type'           => 'string',
                'description'    => "Workflow status of the vote.",
                'default'        => 'pending',
                'selection'      => [
                    'pending',
                    'casted'
                ]
            ]
        ];
    }

    public function getIndexes(): array {
        return [
            ['assembly_item_id']
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => '',
                'icon' => 'sent',
                'transitions' => [
                    'cast' => [
                        'description'   => 'Marks the Assembly vote as `casted`.',
                        'policies'      => ['can_cast'],
                        'status'        => 'casted'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'cast' => [
                'description'   => 'Cast the vote. This action accepts an arg telling which user is casting the vote.',
                'policies'      => ['can_cast'],
                'function'      => 'doCast'
            ],
            'refresh_vote_calc' => [
                'description'   => 'Force re-computing all fields relating to vote shares and weight calculations.',
                'policies'      => [],
                'function'      => 'doRefreshVoteCalc'
            ]

        ]);
    }

    public static function getPolicies(): array {
        return [
            'can_cast' => [
                'description' => 'Verifies that a vote can be `casted`.',
                'function'    => 'policyCanCast'
            ]
        ];
    }

    protected static function policyCanCast($self) {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $assemblyVote) {
            if($assemblyVote['status'] === 'casted') {
                $result[$id] = [
                        'vote_already_casted' => 'Vote cannot be casted more than once.'
                    ];
                continue;
            }
        }
    }

    protected static function doRefreshVoteCalc($self) {
        $self->update([
            'vote_display' => null,
            'vote_weight' => null,
            'vote_weight_for' => null,
            'vote_weight_against' => null,
            'vote_weight_abstain' => null,
            'vote_shares_for' => null,
            'vote_shares_against' => null,
            'vote_shares_abstain' => null,
            'vote_effective_shares' => null
        ])
        ->read([
            'vote_effective_shares',
            'vote_display',
            'vote_weight',
            'vote_weight_for',
            'vote_weight_against',
            'vote_weight_abstain',
            'vote_shares_for',
            'vote_shares_against',
            'vote_shares_abstain'
        ]);
    }

    protected static function doCast($self, $auth, $values) {
        $user_id = $auth->userId();
        if(isset($values['user_id'])) {
            $user_id = $user_id;
        }
        $self->update(['cast_by' => $user_id]);
        $self->transition('cast');
    }

    protected static function calcVoteDisplay($self) {
        $result = [];
        $self->read(['is_choice', 'vote_value', 'assembly_item_choice_id' => ['name']]);

        // #todo - translate
        $map_vote_translations = [
            'for'       => 'pour',
            'against'   => 'contre',
            'abstain'   => 'abstention'
        ];

        foreach($self as $id => $assemblyVote) {
            if($assemblyVote['is_choice']) {
                $result[$id] = $assemblyVote['assembly_item_choice_id']['name'];
            }
            else {
                $result[$id] = $map_vote_translations[$assemblyVote['vote_value']];
            }
        }

        return $result;
    }


    /**
     * The computation logic is similar to AssemblyItem::calcCountRepresentedShares(), but for a single ownership.
     */
    protected static function calcVoteShares($self) {
        $result = [];
        $self->read(['assembly_id' => ['assembly_date'], 'assembly_item_id' => ['apportionment_id'], 'ownership_id']);
        foreach($self as $id => $assemblyVote) {

            if(!isset($assemblyVote['ownership_id'], $assemblyVote['assembly_id'])) {
                continue;
            }

            // 1) identify the property lots
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
     * Compute the actual weight to consider for ballot (not more than 50 % of the represented shares when using a mandate).
     */
    protected static function calcVoteEffectiveShares($self) {
        $result = [];
        $self->read(['vote_shares', 'assembly_id' => ['is_second_session'], 'assembly_item_id' => ['count_represented_shares']]);
        foreach($self as $id => $assemblyVote) {
            // #memo - Art. 3.87 §7 - Nul ne peut prendre part au vote, même comme mandant ou mandataire, pour un nombre de voix supérieur à la somme des voix dont disposent les autres copropriétaires présents ou représentés
            $voter_shares = $assemblyVote['vote_shares'];
            $total_shares = $assemblyVote['assembly_item_id']['count_represented_shares'];

            // if second session we allow one person to vote alone
            if($assemblyVote['assembly_id']['is_second_session'] && $total_shares == $voter_shares) {
                $result[$id] = $voter_shares;
            }
            // otherwise do not allow attendee to vote for more shares than the sum of the other attendees
            else {
                $max = max(0, $total_shares - $voter_shares);
                $result[$id] = min($voter_shares, $max);
            }
        }
        return $result;
    }

    /**
     * Calculate the vote weight based on the shares of the property lots of the ownership,
     * for the related apportionment at the moment of the assembly.
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

    protected static function calcVoteWeightFor($self) {
        $result = [];
        $self->read(['status', 'vote_value', 'vote_weight']);
        foreach($self as $id => $assemblyVote) {
            $result[$id] = ($assemblyVote['vote_value'] === 'for') ? $assemblyVote['vote_weight'] : 0.0;
        }
        return $result;
    }

    protected static function calcVoteWeightAgainst($self) {
        $result = [];
        $self->read(['status', 'vote_value', 'vote_weight']);
        foreach($self as $id => $assemblyVote) {
            $result[$id] = ($assemblyVote['vote_value'] === 'against') ? $assemblyVote['vote_weight'] : 0.0;
        }
        return $result;
    }

    protected static function calcVoteWeightAbstain($self) {
        $result = [];
        $self->read(['status', 'vote_value', 'vote_weight']);
        foreach($self as $id => $assemblyVote) {
            $result[$id] = ($assemblyVote['vote_value'] === 'abstain') ? $assemblyVote['vote_weight'] : 0.0;
        }
        return $result;
    }


    protected static function calcVoteSharesFor($self) {
        $result = [];
        $self->read(['status', 'vote_value', 'vote_effective_shares']);
        foreach($self as $id => $assemblyVote) {
            $result[$id] = ($assemblyVote['vote_value'] === 'for') ? $assemblyVote['vote_effective_shares'] : 0;
        }
        return $result;
    }

    protected static function calcVoteSharesAgainst($self) {
        $result = [];
        $self->read(['status', 'vote_value', 'vote_effective_shares']);
        foreach($self as $id => $assemblyVote) {
            $result[$id] = ($assemblyVote['vote_value'] === 'against') ? $assemblyVote['vote_effective_shares'] : 0;
        }
        return $result;
    }

    protected static function calcVoteSharesAbstain($self) {
        $result = [];
        $self->read(['status', 'vote_value', 'vote_effective_shares']);
        foreach($self as $id => $assemblyVote) {
            $result[$id] = ($assemblyVote['vote_value'] === 'abstain') ? $assemblyVote['vote_effective_shares'] : 0;
        }
        return $result;
    }

    protected static function calcHasMandate($self) {
        $result = [];
        $self->read(['assembly_id', 'ownership_id', 'assembly_attendee_id']);

        foreach($self as $id => $vote) {
            $proxy = AssemblyMandate::search([
                    ['assembly_id', '=', $vote['assembly_id']],
                    ['ownership_id', '=', $vote['ownership_id']],
                    ['attendee_id', '=', $vote['assembly_attendee_id']]
                ])
                ->first();

            $result[$id] = ($proxy !== null);
        }

        return $result;
    }
}
