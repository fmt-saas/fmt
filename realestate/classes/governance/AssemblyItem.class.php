<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

use Exception;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\PropertyLotApportionmentShare;

class AssemblyItem extends AssemblyItemTemplate {

    public function getTable() {
        return 'realestate_governance_assembly_item';
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
                'required'          => true,
                'onupdate'          => 'onupdateAssemblyId'
            ],

            'assembly_template_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly template this item come from.",
                'help'              => 'This field is not relevant here, and present only to override parent definition.',
                'foreign_object'    => 'realestate\governance\AssemblyTemplate',
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Short description of the assembly item.",
                'required'          => true
            ],

            'parent_group_id' => [
                'type'              => 'many2one',
                'description'       => "Parent group item for this item, if it is a sub-item.",
                'foreign_object'    => 'realestate\governance\AssemblyItem',
                'visible'           => [['is_group', '=', false], ['has_parent_group', '=', true]],
                'onupdate'          => 'onupdateParentGroupId'
            ],

            'children_items_ids' => [
                'type'              => 'one2many',
                'description'       => "Children items of the group, if any.",
                'foreign_object'    => 'realestate\governance\AssemblyItem',
                'foreign_field'     => 'parent_group_id',
                'order'             => 'order',
                'domain'            => [['assembly_id', '=', 'object.assembly_id'], ['has_parent_group', '=', true]],
                'visible'           => ['is_group', '=', true],
            ],

            'assembly_votes_ids' => [
                'type'              => 'one2many',
                'description'       => "Votes cast related to the assembly item.",
                'foreign_object'    => 'realestate\governance\AssemblyVote',
                'foreign_field'     => 'assembly_item_id',
                'visible'           => ['has_vote_required', '=', true]
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The apportionment key used for the item (statutory or not).",
                'foreign_object'    => 'realestate\property\Apportionment',
                'visible'           => ['has_vote_required', '=', true]
            ],

            'count_represented_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Number of shares represented in the assembly for the targeted apportionment.",
                'help'              => "This value might differ from the count_represented_shares in Assembly, depending on selected apportionment.",
                'function'          => 'calcCountRepresentedShares',
                'store'             => true,
                'readonly'          => true
            ],

            'documents_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'documents\Document',
                'foreign_field'     => 'assembly_items_ids',
                'rel_table'         => 'realestate_governance_assembly_item_rel_document',
                'rel_foreign_key'   => 'document_id',
                'rel_local_key'     => 'assembly_item_id',
                'description'       => "One or more documents that relate to the point.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'vote_result' => [
                'type'              => 'string',
                'selection'         => [
                    'approved',
                    'rejected'
                ],
                'description'       => "Result of the votes at closure time, once voting is completed.",
                'visible'           => [['has_vote_required', '=', true], ['status', '=', 'closed']],
            ],

            /**
             * Votes can be casted while the resolution is not closed
             * There should be only one resolution open (being discussed) at a time.
             */
            'status' => [
                'type'           => 'string',
                'description'    => "Workflow status of the assembly resolution.",
                'default'        => 'pending',
                'selection'      => [
                    'pending',
                    'open',
                    'voted',
                    'closed'
                ]
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => '',
                'icon' => 'sent',
                'transitions' => [
                    'open' => [
                        'description'   => 'Marks the Assembly Item as open.',
                        'policies'      => ['can_open'],
                        'onafter'       => '',
                        'status'        => 'open'
                    ]
                ]
            ],
            'open' => [
                'description'   => '',
                'icon'          => 'done',
                'transitions'   => [
                    'revert' => [
                        'description'   => 'Marks the Assembly Item as open.',
                        'policies'      => ['can_revert'],
                        'status'        => 'pending'
                    ],
                    'close' => [
                        'description'   => 'Marks the Assembly Item as open.',
                        'policies'      => ['can_close'],
                        'onbefore'      => 'onbeforeClose',
                        'status'        => 'closed'
                    ]
                ]
            ]
        ];
    }
    public static function getActions() {
        return [
            'cast_vote' => [
                'description'   => 'Perform the update of the balance according to given accounting entry.',
                'policies'      => ['can_vote'],
                'function'      => 'doCastVote'
            ],

            'refresh_vote_result' => [
                'description'   => 'Perform the update of the balance according to given accounting entry.',
                'policies'      => [],
                'function'      => 'doRefreshVoteResult'
            ],
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_open' => [
                'description' => 'Verifies that an assembly item can be opened.',
                'function'    => 'policyCanOpen'
            ],
            'can_revert' => [
                'description' => 'Verifies that an assembly item can be reverter to `pending`.',
                'function'    => 'policyCanRevert'
            ],
            'can_close' => [
                'description' => 'Verifies that an assembly item can be closed.',
                'help'        => 'A resolution can be closed even if some votes have not been casted.',
                'function'    => 'policyCanClose'
            ],
            'can_vote' => [
                'description' => 'Verifies that a vote can be casted for the resolution.',
                'function'    => 'policyCanVote'
            ]
        ];
    }

    /**
     * #memo - local field `count_represented_shares` is taken under account in AssemblyVote::calcVoteWeight
     */
    protected static function doRefreshVoteResult($self) {
        $self->read(['majority', 'assembly_id' => ['count_owners', 'count_represented_owners'], 'assembly_votes_ids' => ['vote_value', 'vote_weight']]);

        foreach($self as $id => $item) {
            $result = 'rejected';

            $weights = ['for' => 0.0, 'against' => 0.0, 'abstain' => 0.0];

            $count_votes = $item['assembly_votes_ids']->count();
            $unanimity = true;

            foreach($item['assembly_votes_ids'] as $vote) {
                if(!isset($vote['vote_value'], $vote['vote_weight'])) {
                    continue;
                }
                if($vote['vote_value'] !== 'for') {
                    $unanimity = false;
                }
                $weights[$vote['vote_value']] += $vote['vote_weight'];
            }

            $majority = $item['majority'] ?? null;

            $epsilon = 1e-6;
            $weights_for = $weights['for'];

            if($majority === 'absolute' && $weights_for > (0.5 + $epsilon)) {
                $result = 'approved';
            }
            elseif($majority === '2_3' && $weights_for >= ((2/3) + $epsilon)) {
                $result = 'approved';
            }
            elseif($majority === '4_5' && $weights_for >= ((4/5) + $epsilon)) {
                $result = 'approved';
            }
            elseif($majority === 'unanimity'
                // no vote against, nor abstention
                && $unanimity
                // all owners are presents or represented
                && $item['assembly_id']['count_owners'] == $item['assembly_id']['count_represented_owners']
                // a vote have been cast for each owner
                && $count_votes == $item['assembly_id']['count_owners']
            ) {
                $result = 'approved';
            }

            self::id($id)->update(['vote_result' => $result]);
        }

    }

    protected static function onbeforeClose($self) {
        $self->do('refresh_vote_result');
    }

    protected static function onupdateAssemblyId($self) {
        $self->do('refresh_order');
    }

    protected static function policyCanClose($self) {
        $result = [];
        return $result;
    }

    protected static function policyCanRevert($self) {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $assemblyItem) {
            if($assemblyItem['status'] !== 'open') {
                $result[$id] = [
                        'invalid_status' => 'Assembly item cannot be reverted.'
                    ];
                continue;
            }
        }
        return $result;
    }

    protected static function policyCanOpen($self) {
        $result = [];
        $self->read(['status', 'assembly_id' => ['status']]);
        foreach($self as $id => $assemblyItem) {
            if($assemblyItem['status'] !== 'pending') {
                $result[$id] = [
                        'invalid_status' => 'Assembly item cannot be opened (either closed or already opened).'
                    ];
                continue;
            }

            if($assemblyItem['assembly_id']['status'] !== 'in_progress') {
                $result[$id] = [
                        'assembly_not_in_progress' => 'Assembly is not in progress.'
                    ];
                continue;
            }

            $openedItem = self::search([
                    ['assembly_id', '=', $assemblyItem['assembly_id']['id']],
                    ['status', '=', 'open'],
                    ['id', '<>', $id]
                ])
                ->first();

            if($openedItem) {
                $result[$id] = [
                        'other_item_opened' => 'Another item is still opened.'
                    ];
                continue;
            }

        }
        return $result;
    }

    protected static function policyCanVote($self): array {
        $result = [];
        $self->read(['status', 'assembly_id' => ['status']]);
        foreach($self as $id => $assemblyItem) {
            if($assemblyItem['status'] === 'closed') {
                $result[$id] = [
                        'invalid_status' => 'Votes for assembly item are closed (already voted).'
                    ];
                continue;
            }
            if($assemblyItem['assembly_id']['status'] !== 'in_progress') {
                $result[$id] = [
                        'invalid_status' => 'Assembly is not in progress.'
                    ];
                continue;
            }
        }
        return $result;
    }

    /**
     * Expected args in $values map are:
     * - ownership_id
     * - vote_value
     */
    protected static function doCastVote($self, $values) {
        if(!isset($values['attendee_id'], $values['ownership_id'], $values['vote_value'])) {
            throw new \Exception('missing_param', EQ_ERROR_INVALID_PARAM);
        }

        $self->read(['condo_id', 'assembly_id']);

        foreach($self as $id => $assemblyItem) {

            $votes = AssemblyVote::search([
                ['assembly_item_id', '=', $id],
                ['ownership_id', '=', $values['ownership_id']]
            ]);

            if($votes->count() > 0) {
                throw new \Exception('vote_already_casted', EQ_ERROR_NOT_ALLOWED);
            }

            $representation = AssemblyRepresentation::search([
                    ['assembly_id', '=', $assemblyItem['assembly_id']],
                    ['ownership_id', '=', $values['ownership_id']],
                    ['attendee_id', '=', $values['attendee_id']]
                ])
                ->first();

            if(!$representation) {
                throw new \Exception('attendee_without_mandate', EQ_ERROR_NOT_ALLOWED);
            }

            AssemblyVote::create([
                'condo_id'              => $assemblyItem['condo_id'],
                'assembly_id'           => $assemblyItem['assembly_id'],
                'assembly_item_id'      => $id,
                'assembly_attendee_id'  => $values['attendee_id'],
                'ownership_id'          => $values['ownership_id'],
                'vote_value'            => $values['vote_value']
            ]);

        }
    }

    protected static function calcCountRepresentedShares($self) {
        $result = [];
        $self->read(['has_vote_required', 'apportionment_id', 'assembly_id' => ['id', 'status', 'step', 'assembly_date']]);
        foreach($self as $id => $assemblyItem) {
            if(!$assemblyItem['has_vote_required']) {
                continue;
            }
            // make sure we compute value when all required data is known
            if($assemblyItem['assembly_id']['status'] !== 'in_progress' || $assemblyItem['assembly_id']['step'] !== 'agenda_processing') {
                continue;
            }

            // 1) identify the lots
            $property_lots_ids = [];

            $ownerships_ids = [];
            $representations = AssemblyRepresentation::search([
                    ['assembly_id', '=', $assemblyItem['assembly_id']['id']]
                ])
                ->read(['ownership_id']);
            foreach($representations as $representation) {
                $ownerships_ids[] = $representation['ownership_id'];
            }

            $ownerships = Ownership::ids($ownerships_ids)
                ->read(['property_lot_ownerships_ids' => ['property_lot_id', 'date_to']]);

            foreach($ownerships as $ownership) {
                foreach($ownership['property_lot_ownerships_ids'] as $propertyLotOwnership) {
                    if(!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assemblyItem['assembly_id']['assembly_date']) {
                        $property_lots_ids[] = $propertyLotOwnership['property_lot_id'];
                    }
                }
            }

            // 2) find the targeted apportionment
            $apportionment = Apportionment::id($assemblyItem['apportionment_id'])->first();

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
