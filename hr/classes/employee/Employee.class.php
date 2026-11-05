<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace hr\employee;

use hr\role\RoleAssignment;
use identity\Group;
use identity\Identity;
use identity\User;


class Employee extends Identity {

    /** @deprecated */
    public function getTable() {
        return self::getModelTable();
    }

    public static function getModelTable() {
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
                'dependents'        => ['type', 'name'],
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
        return array_merge(parent::getActions(), [
            'sync_from_teams' => [
                'description'   => "Sync role assignments from Teams and subsequent roles.",
                'policies'      => [],
                'function'      => 'doSyncFromTeams'
            ],
            'create_user_account' => [
                'description'   => 'Create a related User account.',
                'policies'      => ['can_create_user_account'],
                'function'      => 'doCreateUserAccount'
            ],
            'disable_user_account' => [
                'description'   => 'Disable the related User account.',
                'policies'      => ['can_manage_user_account'],
                'function'      => 'doDisableUserAccount'
            ],
            'delete_user_account' => [
                'description'   => 'Delete the related User account.',
                'policies'      => ['can_manage_user_account'],
                'function'      => 'doDeleteUserAccount'
            ]
        ]);
    }

    public static function getPolicies() {
        return array_merge(parent::getPolicies(), [
            'can_create_user_account' => [
                'description'   => 'Checks that the current user can create the related User account.',
                'function'      => 'policyCanCreateUserAccount'
            ],
            'can_manage_user_account' => [
                'description'   => 'Checks that the current user can manage the related User account.',
                'function'      => 'policyCanManageUserAccount'
            ]
        ]);
    }

    protected static function onafterinstantiate($self, $orm, $values) {
        $self->read(['firstname', 'lastname', 'type_id', 'identity_id']);
        foreach($self as $id => $employee) {
            $data = [];
            if(!$employee['identity_id']) {
                $identity = Identity::create([
                        'employee_id'   => $id,
                        'type_id'       => $employee['type_id'],
                        'firstname'     => $employee['firstname'],
                        'lastname'      => $employee['lastname']
                    ])
                    ->first();
                $data['identity_id'] = $identity['id'];
            }
            $data['code'] = sprintf("%03d", $id);
            self::id($id)->update($data);
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

    protected static function policyCanCreateUserAccount($self, $user_id, $access): array {
        $result = [];
        $is_allowed = $user_id === EQ_ROOT_USER_ID
            || $access->userHasGroup($user_id, 'admins')
            || $access->userHasGroup($user_id, 'operators');

        if(!$is_allowed) {
            foreach($self->ids() as $id) {
                $result[$id]['not_allowed'] = 'Only administrators and operators can create User accounts.';
            }
        }

        return $result;
    }

    protected static function policyCanManageUserAccount($self, $user_id, $access): array {
        $result = [];
        $is_allowed = $user_id === EQ_ROOT_USER_ID
            || $access->userHasGroup($user_id, 'admins')
            || $access->userHasGroup($user_id, 'operators');

        $self->read(['user_id']);
        foreach($self as $id => $employee) {
            if(!$is_allowed) {
                $result[$id]['not_allowed'] = 'Only administrators and operators can manage User accounts.';
            }
            if(!$employee['user_id']) {
                $result[$id]['missing_user_account'] = 'The Employee has no related User account.';
            }
        }

        return $result;
    }

    protected static function doCreateUserAccount($self, $auth, $orm) {
        $self->read(['identity_id', 'role_assignments_ids']);

        $groups_ids = Group::search(['name', 'in', ['employees', 'users']])->ids();
        $current_user_id = $auth->userId();
        $auth->su();

        try {
            foreach($self as $employee_id => $employee) {
                $identity = Identity::id($employee['identity_id'])
                    ->read(['name', 'email', 'user_id'])
                    ->first();

                // In case the User already exists, simply ignore the request.
                if(!$identity['user_id']) {
                    if(!$identity['email']) {
                        trigger_error("APP::Ignored user creation for identity {$identity['name']} of employee [{$employee_id}] without email address.", EQ_REPORT_WARNING);
                        continue;
                    }

                    $new_user_id = $orm->create(User::getType(), [
                            // Capabilities for EQ_R_UPDATE are based on the creator context.
                            'creator'       => $current_user_id,
                            'login'         => $identity['email'],
                            'language'      => 'fr',
                            'validated'     => true,
                            'is_employee'   => true,
                            'is_owner'      => false,
                            'groups_ids'    => $groups_ids
                        ]);

                    if($new_user_id <= 0) {
                        trigger_error("APP::Error while creating a User for identity {$identity['name']}.", EQ_REPORT_WARNING);
                        continue;
                    }

                    User::id($new_user_id)
                        ->update(['identity_id' => $identity['id']])
                        ->do('sync_from_identity');

                    self::id($employee_id)->update(['user_id' => $new_user_id]);

                    try {
                        \eQual::run('do', 'identity_User_send-confirmation', ['id' => $new_user_id]);
                    }
                    catch(\Throwable $throwable) {
                        trigger_error(
                            "APP::Unable to send confirmation email for user [{$new_user_id}]: {$throwable->getMessage()}.",
                            EQ_REPORT_WARNING
                        );
                    }
                }

                // Force refreshing role assignments.
                RoleAssignment::ids($employee['role_assignments_ids'])->read(['user_id']);
            }
        }
        finally {
            $auth->su($current_user_id);
        }
    }

    protected static function doDisableUserAccount($self, $auth) {
        $self->read(['user_id']);

        $users_ids = [];
        foreach($self as $employee) {
            if($employee['user_id']) {
                $users_ids[] = $employee['user_id'];
            }
        }

        if(empty($users_ids)) {
            return;
        }

        $current_user_id = $auth->userId();
        $auth->su();
        try {
            User::ids(array_values(array_unique($users_ids)))->do('suspend');
        }
        finally {
            $auth->su($current_user_id);
        }
    }

    protected static function doDeleteUserAccount($self, $auth) {
        $self->read(['user_id', 'identity_id']);

        $users_ids = [];
        $identities_ids = [];
        $employees_ids = [];
        foreach($self as $id => $employee) {
            if(!$employee['user_id']) {
                continue;
            }
            $users_ids[] = $employee['user_id'];
            $employees_ids[] = $id;
            if($employee['identity_id']) {
                $identities_ids[] = $employee['identity_id'];
            }
        }

        if(empty($users_ids)) {
            return;
        }

        $current_user_id = $auth->userId();
        $auth->su();
        try {
            User::ids(array_values(array_unique($users_ids)))->delete(true);
            self::ids($employees_ids)->update(['user_id' => null]);
        }
        finally {
            $auth->su($current_user_id);
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
