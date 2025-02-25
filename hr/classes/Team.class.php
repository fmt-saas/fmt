<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace hr;

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
                'description'       => 'Mnemo code assigned to the employee.'
            ],

            'employees_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'teams_ids',
                'rel_table'         => 'hr_rel_employee_team',
                'rel_foreign_key'   => 'employee_id',
                'rel_local_key'     => 'team_id',
                'description'       => 'Employees members of the team.',
                'dependents'        => ['members_count']
            ],

            'members_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Number of employees in the team.',
                'function'          => 'calcMembersCount',
                'store'             => true
            ]

        ];
    }

    public static function calcMembersCount($self) {
        $result = [];
        $self->read(['employees_ids']);
        foreach($self as $id => $team) {
            $result[$id] = count($team['employees_ids']);
        }
        return $result;
    }
}