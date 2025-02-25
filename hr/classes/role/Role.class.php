<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace hr\role;

use core\Group;

class Role extends Group {

    public function getTable() {
        return 'hr_role_role';
    }

    public static function getName() {
        return 'Role';
    }

    public static function getDescription() {
        return "A role relates to a Job Title, describes a set of tasks assigned to an employee, and targets a specific set of permissions.";
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Official Name of the role.',
                'multilang'         => true,
                'required'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique mnemo code assigned to the role.',
                'unique'            => true,
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Details about the role.',
                'multilang'         => true
            ],

            'role_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\role\RoleAssignment',
                'foreign_field'     => 'role_id',
                'description'       => 'List of assignments targeting the Role.'
            ],

            'permissions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\Permission',
                'foreign_field'     => 'role_id'
            ]

        ];
    }

}