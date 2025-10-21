<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace hr;

use hr\employee\Employee;
use hr\role\RoleAssignment;

class Team extends \equal\orm\Model {

    public static function getName() {
        return 'Team';
    }

    public static function getDescription() {
        return "A Team is an arbitrary group of employees that can be used to assign tasks, projects, etc.";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Mnemo code assigned to the employee.',
                'required'          => true
            ],

            'organization_id' => [
                'type'            => 'many2one',
                'description'     => "Organization the Team is part of.",
                'foreign_object'  => 'identity\Organisation',
                'default'         => 1
            ],

            'employees_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'teams_ids',
                'onupdate'          => 'onupdateEmployeesIds',
                'rel_table'         => 'hr_rel_employee_team',
                'rel_foreign_key'   => 'employee_id',
                'rel_local_key'     => 'team_id',
                'description'       => 'Employees members of the team.',
                'dependents'        => ['members_count'],
                'domain'            => [['organization_id', '=', 'object.organization_id']]
            ],

            'members_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Number of employees in the team.',
                'function'          => 'calcMembersCount',
                'store'             => false
            ],

            'role_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\role\Role',
                'description'       => 'Primary role, if set.',
            ]

        ];
    }

    protected static function calcMembersCount($self) {
        $result = [];
        $self->read(['employees_ids']);
        foreach($self as $id => $team) {
            $result[$id] = count($team['employees_ids']);
        }
        return $result;
    }

    /**
     * Sync roles with currently assigned teams.
     */
    protected static function onupdateEmployeesIds($self) {
        $self->read(['condo_id', 'role_id', 'employees_ids']);
        foreach($self as $id => $team) {
            if(!$team['employees_ids'] || empty($employee['employees_ids'])) {
                continue;
            }
            Employee::ids($team['employees_ids'])->do('sync_from_teams');
        }
    }
}