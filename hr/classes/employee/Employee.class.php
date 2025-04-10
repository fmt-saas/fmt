<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace hr\employee;

use identity\Identity;
use identity\Partner;

class Employee extends Identity {

    public function getTable() {
        return 'hr_employee_employee';
    }

    public static function getName() {
        return 'Employee';
    }

    public static function getDescription() {
        return "An employee is relationship relating to contract that has been made between an identity and a company.";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the Owner.",
                'relation'          => ['identity_id' => 'name'],
                'store'             => true,
                'readonly'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Mnemo code assigned to the employee.',
                'unique'            => true
            ],

            'role_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'hr\role\Role',
                'description'       => 'Primary role, if set.',
                'function'          => 'calcRoleId',
                'readonly'          => true,
                'store'             => true
            ],

            'role_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\role\RoleAssignment',
                'foreign_field'     => 'employee_id',
                'description'       => 'Roles assigned to the employee.'
            ],

            'relationship' => [
                'deprecated'        => 'Employee uses its own table and Partner should not be used as a final entity.',
                'type'              => 'string',
                'default'           => 'employee',
                'description'       => 'Force relationship to Employee.'
            ],

            'date_start' => [
                'type'              => 'date',
                'description'       => 'Date of the first day of work.'
            ],

            'date_end' => [
                'type'              => 'date',
                'description'       => 'Date of the last day of work.',
                'help'              => 'Date at which the contract ends (known in advance for fixed-term or unknown for permanent).'
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => 'Marks the employee as currently active within the organisation.',
                'default'           => true
            ],

            'teams_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'hr\Team',
                'foreign_field'     => 'employees_ids',
                'rel_table'         => 'hr_rel_employee_team',
                'rel_foreign_key'   => 'team_id',
                'rel_local_key'     => 'employee_id',
                'description'       => 'Teams the employee is a members of.'
            ]

        ];
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $employee) {
            if($employee['identity_id']) {
                Identity::id($employee['identity_id'])->update(['employee_id' => $id]);
            }
        }
    }

    public static function calcRoleId($self) {
        $result = [];
        $self->read(['role_assignments_ids' => ['is_primary', 'role_id']]);
        foreach($self as $id => $employee) {
            foreach($employee['role_assignments_ids'] as $assignment) {
                if($assignment['is_primary']) {
                    $result[$id] = $assignment['role_id'];
                }
            }
        }
        return $result;
    }

}