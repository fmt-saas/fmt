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
                'description'       => "Specific code for identifying the assembly item."
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
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Mark the item as a group of sub-items.',
                'help'              => "Group items are used to organize sub-items in the assembly agenda. They can have a description, but cannot be voted on directly.",
                'store'             => true,
                'function'          => 'calcHasParentGroup',
                'visible'           => ['is_group', '=', false]
            ],

            'parent_group_id' => [
                'type'              => 'many2one',
                'description'       => 'Parent group item for this item, if it is a sub-item.',
                'foreign_object'    => 'realestate\governance\AssemblyItemTemplate',
                'visible'           => [['is_group', '=', false], ['has_parent_group', '=', true]],
                'dependents'        => ['has_parent_group'],
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
                'default'           => false
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
            'refresh_items_count'  => [
                'description'   => 'Refresh sub-items count for parent groups.',
                'policies'      => [],
                'function'      => 'doRefreshItemsCount'
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

    protected static function onupdateAssemblyTemplateId($self) {
        $self->do('refresh_order');
    }

    protected static function onupdateParentGroupId($self) {
        $self
            ->do('refresh_order')
            ->do('refresh_items_count');
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['is_group']) && $event['is_group'] === true) {
            $result['has_vote_required'] = false;
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

    protected static function calcHasParentGroup($self) {
        $result = [];
        $self->read(['parent_group_id']);
        foreach($self as $id => $item) {
            $result[$id] = !empty($item['parent_group_id']);
        }
        return $result;
    }
}
