<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace hr\employee;

use hr\role\RoleAssignment;
use identity\Identity;


class Employee extends Identity {

    public function getTable() {
        return 'hr_employee_employee';
    }

    public static function getName() {
        return 'Employee';
    }

    public static function getDescription() {
        return "An employee is relationship relating to a contract that has been made between an identity and a company.";
    }

    public static function getColumns() {

        return [
            'organization_id' => [
                'type'            => 'many2one',
                'description'     => "Organization the Employee works for.",
                'foreign_object'  => 'identity\Organisation',
                'default'         => 1
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\IdentityType',
                'onupdate'          => 'onupdateTypeId',
                'default'           => 1,
                'dependents  '      => ['type', 'name'],
                'description'       => 'Type of identity.',
                'help'              => 'For employees, default to `individual`.'
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current entity .',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'hr\employee\Employee'
            ],

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

            'condominiums_ids' => [
                'type'              => 'computed',
                'result_type'       => 'one2many',
                'foreign_object'    => 'realestate\property\Condominium',
                'function'          => 'calcCondominiumsIds',
                'store'             => false,
                'description'       => 'Condominiums assigned to the employee, through role assignments.'
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
                'onupdate'          => 'onupdateTeamsIds',
                'rel_table'         => 'hr_rel_employee_team',
                'rel_foreign_key'   => 'team_id',
                'rel_local_key'     => 'employee_id',
                'description'       => 'Teams the employee is a members of.'
            ]

        ];
    }

    public static function getActions() {
        return [
            'sync_from_teams' => [
                'description'   => "Sync role assignments from Teams and subsequent roles.",
                'policies'      => [],
                'function'      => 'doSyncFromTeams'
            ]
        ];
    }

    protected static function oncreate($self, $orm) {
        $self->read(['firstname', 'lastname', 'type_id', 'identity_id']);
        foreach($self as $id => $employee) {
            if($employee['identity_id']) {
                continue;
            }
            $identity = Identity::create([
                    'employee_id'   => $id,
                    'type_id'       => $employee['type_id'],
                    'firstname'     => $employee['firstname'],
                    'lastname'      => $employee['lastname']
                ])
                ->first();
            self::id($id)->update(['identity_id' => $identity['id']]);
        }
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $employee) {
            if($employee['identity_id']) {
                Identity::id($employee['identity_id'])->update(['employee_id' => $id]);
            }
        }
    }

    protected static function calcCondominiumsIds($self) {
        $result = [];
        $self->read(['role_assignments_ids' => ['condo_id']]);
        foreach($self as $id => $employee) {
            $result[$id] = [];
            foreach($employee['role_assignments_ids'] as $role_assignment_id => $roleAssignment) {
                if($roleAssignment['condo_id']) {
                    $result[$id][] = $roleAssignment['condo_id'];
                }
            }
        }
        return $result;
    }

    protected static function doSyncFromTeams($self) {
        $self->read(['teams_ids' => ['role_id']]);
        foreach($self as $id => $employee) {
            if(!$employee['teams_ids'] || empty($employee['teams_ids'])) {
                continue;
            }
            $role_assignments_ids = [];
            foreach($employee['teams_ids'] as $team) {
                if(!$team['role_id']) {
                    continue;
                }
                $roleAssignment = RoleAssignment::search([['employee_id', '=', $id], ['role_id', '=', $team['role_id']]])->first();
                if($roleAssignment) {
                    continue;
                }
                $roleAssignment = RoleAssignment::create([
                        'employee_id'   => $id,
                        'role_id'       => $team['role_id']
                    ])
                    ->first();
                $role_assignments_ids[] = $roleAssignment['id'];
            }
            self::id($id)->update(['role_assignments_ids' => $role_assignments_ids]);
        }
    }

    /**
     * Sync roles with currently assigned teams.
     */
    protected static function onupdateTeamsIds($self) {
        $self->do('sync_from_teams');
    }

    protected static function calcRoleId($self) {
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