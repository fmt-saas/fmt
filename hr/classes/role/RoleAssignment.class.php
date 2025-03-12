<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace hr\role;

use core\Assignment;

class RoleAssignment extends \equal\orm\Model {

    public static function getName() {
        return 'Role Assignment';
    }

    public static function getDescription() {
        return "An assignment links a user to one or more roles which, in turn, relate to specific permissions.";
    }

    public static function getColumns() {

        return [

            'condo_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcCondoName',
                'store'             => true
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'description'       => 'Condominium the assignment applies to.',
                'help'              => 'If not set, the assignment applies to all condominiums.',
                'dependents'        => ['condo_name']
            ],

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User (internal or external) the assignment applies to.',
                'help'              => 'The user should always be set and is used for Access Control. It is automatically retrieved from the employee_id.',
                'ondelete'          => 'cascade'
            ],

            'employee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\employee\Employee',
                'description'       => 'Employee the assignment applies to, if any.',
                'help'              => 'Role can bee assigned both to employees and external users. When assignment relates to an employee, corresponding user_id is automatically retrieved.',
                'onupdate'          => 'onupdateEmployeeId'
            ],

            'role_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\role\Role',
                'description'       => 'Role the assignment relates to.',
                'ondelete'          => 'cascade'
            ],

            'is_primary' => [
                'type'              => 'boolean',
                'description'       => 'This is the primary role of the User.',
                'default'           => false
            ]

        ];
    }

    public static function calcCondoName($self) {
        $result = [];
        $self->read(['condo_id' => ['name']]);
        foreach($self as $id => $assignment) {
            $result[$id] = $assignment['condo_id'] ? $assignment['condo_id']['name'] : '*';
        }
        return $result;
    }

    public static function onupdateEmployeeId($self) {
        $self->read(['employee_id' => ['partner_identity_id' => 'user_id'], 'user_id' ]);
        foreach($self as $id => $assignment) {
            if($assignment['employee_id'] && !$assignment['user_id']) {
                self::id($id)->update(['user_id' => $assignment['employee_id']['partner_identity_id']['user_id']]);
            }
        }
    }

}