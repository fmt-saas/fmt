<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2024
    Original author(s): Cédric FRANCOYS
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;

class Group extends  \core\Group {

    public static function getColumns() {
        return [

            'users_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'identity\User',
                'foreign_field'     => 'groups_ids',
                'rel_table'         => 'core_rel_group_user',
                'rel_foreign_key'   => 'user_id',
                'rel_local_key'     => 'group_id',
                'description'       => 'List of users that are members of the group.'
            ],

            'roles_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'hr\role\Role',
                'foreign_field'     => 'groups_ids',
                'rel_table'         => 'hr_role_rel_core_group',
                'rel_foreign_key'   => 'role_id',
                'rel_local_key'     => 'group_id',
                'description'       => 'Groups that are granted to employees assigned with the role.'
            ],

        ];
    }

}