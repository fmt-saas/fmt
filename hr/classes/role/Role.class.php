<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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

            'is_external' => [
                'type'              => 'boolean',
                'description'       => 'The assignment is granted to an external User.',
                'help'              => 'External Users do not relate to the Organization (not employees).',
                'default'           => false
            ],

            'is_mandatory' => [
                'type'              => 'boolean',
                'description'       => 'The role must be assigned at the creation of a new Condominium.',
                'help'              => 'Some roles are mandatory for the management of a Condominium and must be assigned at all time.',
                'default'           => false
            ],

            'role_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\role\RoleAssignment',
                'foreign_field'     => 'role_id',
                'description'       => 'List of assignments targeting the Role.'
            ],

            'role_permissions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\role\RoleGrant',
                'foreign_field'     => 'role_id',
                'description'       => "Targeted role to which the permission applies.",
                'ondelete'          => 'cascade'
            ],

            'document_assignment_rules_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\processing\DocumentAssignmentRule',
                'foreign_field'     => 'role_id',
                'description'       => "Document assignment rules that are related to this role."
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'identity\Group',
                'foreign_field'     => 'roles_ids',
                'rel_table'         => 'hr_role_rel_core_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'role_id',
                'description'       => 'Groups that are granted to employees assigned with the role.'
            ],

        ];
    }

}