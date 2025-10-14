<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace hr\role;

use core\Assignment;

class RoleAssignment extends \equal\orm\Model {

    public static function getName() {
        return 'Role Assignment';
    }

    public static function getDescription() {
        return "An assignment links a user to one or more roles which, in turn, relate to specific permissions.
        Assignments implicitly relate to the current Organization / Managing Agent of the current instance but can also be external to it.";
    }

    public static function getColumns() {

        return [
            'organization_id' => [
                'type'            => 'many2one',
                'description'     => "Organization the Employee works for.",
                'foreign_object'  => 'identity\Organisation',
                'default'         => 1
            ],

            'condo_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['condo_id' => 'name'],
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
                'ondelete'          => 'cascade',
                'dependents'        => ['identity_id']
            ],

            'is_external' => [
                'type'              => 'boolean',
                'description'       => 'The assignment is granted to an external User.',
                'help'              => 'External Users do not relate to the Organization (not employees).',
                'default'           => false
            ],

            'employee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\employee\Employee',
                'description'       => 'Employee the assignment applies to, if any.',
                'help'              => 'Role can be assigned both to employees and external users. When assignment relates to an employee, corresponding user_id is automatically retrieved.',
                'onupdate'          => 'onupdateEmployeeId',
                'visible'           => ['is_external', '=', false],
                'domain'            => ['organization_id', '=', 'object.organization_id']
            ],

            'role_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\role\Role',
                'description'       => 'Role the assignment relates to.',
                'ondelete'          => 'cascade',
                'dependents'        => ['role_code']
            ],

            'role_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['role_id' => 'code'],
                'description'       => 'Role the assignment relates to.',
                'store'             => true,
                'instant'           => true
            ],

            'identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'relation'          => ['user_id' => 'identity_id'],
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Targeted Identity, retrieved from User.',
                'store'             => true,
                'instant'           => true
            ],

            'is_primary' => [
                'type'              => 'boolean',
                'description'       => 'This is the primary role of the User.',
                'default'           => false
            ]

        ];
    }

    public static function onupdateEmployeeId($self) {
        $self->read(['employee_id' => ['identity_id' => 'user_id'], 'user_id' ]);
        foreach($self as $id => $assignment) {
            if($assignment['employee_id'] && !$assignment['user_id']) {
                self::id($id)->update(['user_id' => $assignment['employee_id']['identity_id']['user_id']]);
            }
        }
    }

}