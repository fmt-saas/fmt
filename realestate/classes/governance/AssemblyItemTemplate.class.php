<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

class   AssemblyItemTemplate extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Short description of the assembly item.",
                'required'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Specific code for identifying the assembly item.",
                'selection'         => [
                    'assembly_officers_appointment',
                    'contentious_cases_info',
                    'accounts_approval',
                    'budget_approval',
                    'working_fund_adjustment',
                    'appointment_auditors',
                    'supplier_list_approval',
                    'syndic_mandate_renewal',
                    'work_decision',
                    'work_funding_mode',
                    'active_contracts_info',
                    'contract_modification_mandate',
                    'energy_supplier_mandate',
                    'tender_threshold',
                    'specification_threshold',
                    'setting_ag_period',
                    'long_term_contracts',
                    'roi_update',
                    'sanctions_unpaid_dues',
                    'owner_proposed_items',
                    'concierge_replacement',
                    'governance_setup',
                    'accounting_configuration',
                    'fiscal_year_definition',
                    'statement_periodicity',
                    'provision_frequency',
                    'initial_budget_approval',
                    'initial_operating_budget',
                    'working_fund_creation',
                    'reserve_fund_creation',
                    'administrative_setup',
                    'initial_information_validation',
                    'debtor_status',
                    'extraordinary_points',
                    'extraordinary_works',
                    'statutes_change',
                    'roi_change',
                    'judicial_decision',
                    'other_extraordinary_items',
                    'recovery_governance',
                    'syndic_appointment',
                    'recovery_accounting',
                    'fiscal_year_reset',
                    'financial_reconstruction',
                    'funds_recovery',
                    'working_fund_recovery',
                    'reserve_fund_recovery',
                    'debts_contracts_validation',
                    'recovery_budget',
                    'other_recovery_decisions'
                ]
            ],

            'helper' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Additional information about the item and its legal basis, when relevant."
            ],

            'order' => [
                'type'              => 'integer',
                'description'       => "Order of the item in the assembly agenda.",
                'default'           => 1
            ],

            'items_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Number of items contained by the node.',
                'visible'           => ['is_group', '=', true],
                'store'             => true,
                'function'          => 'calcItemsCount'
            ],

            'assembly_template_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly template this item belongs to.",
                'foreign_object'    => 'realestate\governance\AssemblyTemplate',
                'required'          => true,
                'onupdate'          => 'onupdateAssemblyTemplateId',
            ],

            'is_group' => [
                'type'              => 'boolean',
                'description'       => 'Mark the item as a group of sub-items.',
                'help'              => "Group items are used to organize sub-items in the assembly agenda. They can have a description, but cannot be voted on directly.",
                'default'           => false,
                'visible'           => ['has_parent_group', '=', false]
            ],

            'has_parent_group' => [
                'type'              => 'boolean',
                'description'       => 'Mark the item as a group of sub-items.',
                'help'              => "Group items are used to organize sub-items in the assembly agenda. They can have a description, but cannot be voted on directly.",
                'visible'           => ['is_group', '=', false],
                'onupdate'          => 'onupdateHasParentGroup'
            ],

            'parent_group_id' => [
                'type'              => 'many2one',
                'description'       => 'Parent group item for this item, if it is a sub-item.',
                'foreign_object'    => 'realestate\governance\AssemblyItemTemplate',
                'visible'           => [['is_group', '=', false], ['has_parent_group', '=', true]],
                'onupdate'          => 'onupdateParentGroupId'
            ],

            'children_items_ids' => [
                'type'              => 'one2many',
                'description'       => "Children items of the group, if any.",
                'foreign_object'    => 'realestate\governance\AssemblyItemTemplate',
                'foreign_field'     => 'parent_group_id',
                'domain'            => [['assembly_template_id', '=', 'object.assembly_template_id'], ['has_parent_group', '=', true]],
                'visible'           => ['is_group', '=', true]
            ],

            'description_call' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => 'Description for the assembly call.',
                /*'visible'           => [['is_group', '=', false]],*/
            ],

            'description_minutes' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => 'Description for the assembly minutes.',
                /*'visible'           => [['is_group', '=', false]],*/
            ],

            'description_ballot' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => 'Description for the assembly minutes.',
                'visible'           => [['is_group', '=', false], ['has_vote_required', '=', true]],
            ],

            'has_vote_required' => [
                'type'              => 'boolean',
                'description'       => 'Flag indicating if a vote is required for this item.',
                'default'           => false,
                'visible'           => ['is_group', '=', false]
            ],

            'majority' => [
                'type'              => 'string',
                'description'       => "Type of majority required for the vote.",
                'selection'         => [
                    'unanimity',    /*
                                        first_session  : 100% - all ownerships (possible only if all are presents or represented)
                                        second_session : 100% - all ownerships presented or represented
                                    */
                    'absolute',     /* > 50% */
                    '2_3',          /* */
                    /*'3_4',*/
                    '4_5',
                    /*'1_5'*/       /* amount of owners for requesting an Assembly */
                ],
                'visible'           => ['has_vote_required', '=', true]
            ],

            'apportionment_code' => [
                'type'              => 'string',
                'description'       => "Code of the default apportionment key to use.",
                'help'              => "The code is mandatory and implied in retrieval of related apportionment_id.",
                'visible'           => ['has_vote_required', '=', true],
                'default'           => 'STAT'
            ]

        ];
    }

    public static function getActions() {
        return [
            'refresh_order' => [
                'description'   => 'Refresh order according to parent assembly & group.',
                'policies'      => [],
                'function'      => 'doRefreshOrder'
            ],
            'refresh_subitems_order'  => [
                'description'   => 'Refresh sub-items count for parent groups.',
                'policies'      => [],
                'function'      => 'doRefreshSubitemsOrder'
            ],
            'refresh_items_count'  => [
                'description'   => 'Refresh sub-items count for parent groups.',
                'policies'      => [],
                'function'      => 'doRefreshItemsCount'
            ],
            'refresh_has_vote' => [
                'description'   => 'Only for groups (parent items), refresh has_vote based on sub-items.',
                'policies'      => [],
                'function'      => 'doRefreshHasVote'
            ]
        ];
    }

    /**
     * Refresh the whole list (children) if a parent is updated
     */
    protected static function doRefreshOrder($self) {
        $self->read(['state', 'assembly_template_id', 'has_parent_group', 'parent_group_id']);
        foreach($self as $id => $item) {
            if(!$item['assembly_template_id']) {
                continue;
            }

            $conditions = [
                ['assembly_template_id', '=', $item['assembly_template_id']],
                ['id', '<>', $id]
            ];

            if($item['has_parent_group']) {
                $conditions[] = ['parent_group_id', '=', $item['parent_group_id']];
            }
            else {
                $conditions[] = ['parent_group_id', 'is', null];
            }

            $count = count(self::search($conditions)->ids());
            self::id($id)
                ->update([
                    'state' => $item['state'],
                    'order' => $count + 1
                ]);
        }
    }

    protected static function doRefreshItemsCount($self) {
        $self->read(['parent_group_id']);
        foreach($self as $id => $assemblyItem) {
            self::id($assemblyItem['parent_group_id'])->update(['items_count' => null]);
        }
        $self->update(['items_count' => null]);
    }

    protected static function doRefreshSubitemsOrder($self) {
        $self->read(['children_items_ids']);
        foreach($self as $id => $assemblyItem) {
            self::ids($assemblyItem['children_items_ids'])->do('refresh_order');
        }
    }

    protected static function doRefreshHasVote($self) {
        $self->read(['has_parent_group', 'children_items_ids' => ['has_vote_required']]);
        foreach($self as $id => $assemblyItem) {
            // applies only on parents items/groups
            if($assemblyItem['has_parent_group']) {
                continue;
            }
            $has_vote = false;
            foreach($assemblyItem['children_items_ids'] as $item) {
                if($item['has_vote_required']) {
                    $has_vote = true;
                    break;
                }
            }
            if($has_vote) {
                self::id($id)->update(['has_subvote_required' =>  true]);
            }
        }
    }

    protected static function onupdateAssemblyTemplateId($self) {
        $self->do('refresh_order');
    }

    protected static function onupdateHasParentGroup($self) {
        $self->read(['parent_group_id']);
        foreach($self as $id => $assemblyItem) {
            if(!$assemblyItem['parent_group_id']) {
                self::id($id)->update(['has_parent_group' => false]);
            }
        }
        $self
            ->do('refresh_order')
            ->do('refresh_items_count');
    }

    protected static function onupdateParentGroupId($self) {
        $self
            ->read(['assembly_template_id'])
            ->do('refresh_order')
            ->do('refresh_items_count');
        foreach($self as $id => $assemblyItem) {
            AssemblyTemplate::id($assemblyItem['assembly_template_id'])
                ->do('refresh_items_order');
        }
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['is_group']) && $event['is_group'] === true) {
            $result['has_vote_required'] = false;
        }
        if(isset($event['has_parent_group']) && $event['has_parent_group'] === false) {
            $result['parent_group_id'] = null;
        }
        return $result;
    }

    protected static function calcItemsCount($self) {
        $result = [];
        $self->read(['children_items_ids']);
        foreach($self as $id => $item) {
            $result[$id] = count($item['children_items_ids']);
        }
        return $result;
    }

}
