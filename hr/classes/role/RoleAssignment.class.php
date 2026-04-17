<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace hr\role;

class RoleAssignment extends \equal\orm\Model {

    public static function getName() {
        return 'Role Assignment';
    }

    public static function getDescription() {
        return "An assignment links a user to one or more roles which, in turn, relate to specific groups, and related permissions.
        Assignments implicitly relate to the current Organization / Managing Agent of the current instance (through Employees) but can also be external to it (through Users).";
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Short description of the assignment rule.',
                'function'          => 'calcName',
                'readonly'          => true
            ],

            'organization_id' => [
                'type'            => 'many2one',
                'description'     => "Organization the Employee works for.",
                'foreign_object'  => 'identity\Organisation',
                'default'         => 1
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'description'       => 'Condominium the assignment applies to.',
                'help'              => 'If not set, the assignment applies to all condominiums.',
                'dependents'        => ['condo_name']
            ],

            'condo_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['condo_id' => 'name'],
                'store'             => true
            ],

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User (internal or external) the assignment applies to.',
                'help'              => 'The user should always be set and is used for Access Control.
                    When `employee_id` is set, user is automatically retrieved from related Identity (in `onupdateEmployeeId`).',
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
                'onupdate'          => 'onupdateRoleId',
                'ondelete'          => 'cascade',
                'dependents'        => ['role_code'],
                'required'          => true
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

    protected static function calcName($self) {
        $result = [];
        $self->read(['state', 'user_id' => ['name'], 'condo_id' => ['name'], 'role_id' => ['name']]);
        foreach($self as $id => $roleAssignment) {
            if($roleAssignment['state'] === 'draft') {
                continue;
            }
            $result[$id] = $roleAssignment['condo_id']['name'] . " - " .
                $roleAssignment['role_id']['name'] . " -> " .
                $roleAssignment['user_id']['name'];
        }
        return $result;
    }

    /**
     * #memo - this will trigger `identity_id` refresh
     *
     */
    protected static function onupdateEmployeeId($self) {
        $self->read(['employee_id' => ['identity_id' => ['user_id']]]);
        foreach($self as $id => $assignment) {
            if(isset($assignment['employee_id']['identity_id'], $assignment['employee_id']['identity_id']['user_id'])) {
                self::id($id)->update(['user_id' => $assignment['employee_id']['identity_id']['user_id']]);
            }
        }
    }

    public static function canupdate($self) {
        $self->read(['condo_id', 'role_id' => ['code'], 'employee_id']);
        foreach($self as $id => $roleAssignment) {
            if($roleAssignment['condo_id'] && $roleAssignment['employee_id'] && $roleAssignment['role_id']['code'] === 'condo_manager') {
                // make sure there is no more than one condo_manager per condominium
                $roleAssignments = self::search([
                        ['condo_id', '=', $roleAssignment['condo_id']],
                        ['role_id', '=', $roleAssignment['role_id']['id']],
                        ['employee_id', '=', $roleAssignment['employee_id']],
                        ['id', '<>', $id]
                    ]);

                if($roleAssignments->count() > 0) {
                    return ['employee_id' => ['not_allowed' => 'Only one condo_manager is allowed for a condominium.']];
                }
            }
        }
        return parent::canupdate($self);
    }

    public static function onchange($event, $values): array {
        $result = [];
        if(isset($event['role_id'])) {
            $role = Role::id($event['role_id'])->read(['is_external'])->first();
            $result['is_external'] = $role['is_external'];
        }
        return $result;
    }

    public function getUnique() {
        return [
            ['condo_id', 'role_id', 'user_id'],
            ['condo_id', 'role_id', 'employee_id']
        ];
    }
}