<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use core\Group;
use identity\User;
use core\Permission;
use hr\role\Role;
use hr\role\RoleAssignment;
use realestate\property\Condominium;
use realestate\property\PropertyLot;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

/**
 * #memo - IMPORTANT - in general config.json, DEFAULT_RIGHTS is expected to be set to 0.
 */
$tests = [
    '0101' => [
            'description'       =>  "Retrieve Access Controller.",
            'help'              =>  "Access Controller service should be overridden by the one present in `fmt/lib` directory. ",
            'return'            =>  ['object'],
            'act'               =>  function () {
                    list($params, $providers) = eQual::announce([
                        'providers' => ['access']
                    ]);
                    return $providers['access'];
                },
            'assert'            =>  function($access) {
                    return ($access instanceof fmt\access\AccessController);
                }
        ],

    // roles assignments

    '0201' => [
            'description'       => "Assignment for non-existing group.",
            'help'              => "Create a user, check with a non existing group. User should be assigned to the role.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $role = Role::create(['name' => 'test role', 'code' => 'test'])->first();
                    return [$user, $role];
                },
            'act'               => function($params) use($providers) {
                    [$user, $role] = $params;
                    RoleAssignment::create(['user_id' => $user['id'], 'role_id' => $role['id']]);
                    return $user['id'];
                },
            'assert'            => function($user_id) use($providers) {
                    return $providers['access']->hasCondoRole('test', null, $user_id);
                },
            'rollback'          => function() {
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                    Role::search(['code', '=', 'test'])->delete(true);
                }
        ],

    '0202' => [
            'description'       => "Without permissions, on class with condo_id.",
            'help'              => "Verify permissions for a user who has no granted rights on a class containing a condo_id. User should not be granted rights.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                },
            'act'               => function() use($providers) {
                    /** @var \equal\auth\AuthenticationManager */
                    $auth = $providers['auth'];
                    $auth->authenticate('user_test@example.com', 'abcd1234');
                    return $auth->getUserId();
                },
            'assert'            => function($user_id) use($providers) {
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];
                    return !($am->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\property\PropertyLot'));
                },
            'rollback'          => function() use($providers) {
                    /** @var \equal\auth\AuthenticationManager */
                    $auth = $providers['auth'];
                    // switch back to root user
                    $auth->su();
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                }
        ],

    '0203' => [
            'description'       => "With HR permissions, on class, with condo_id.",
            'help'              => "Verify permissions for user with granted rights on class holding a condo_id. User should be granted rights.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $group = Group::create(['name' => 'test group'])->first();
                    $role = Role::create(['name' => 'test role', 'code' => 'test'])
                        ->update(['groups_ids' => [$group['id']]])
                        ->first();

                    return [$user, $role, $group];
                },
            'act'               => function($params) use($providers) {
                    [$user, $role, $group] = $params;
                    Permission::create(['class_name' => 'realestate\property\PropertyLot', 'group_id' => $group['id'], 'rights' => 15]);
                    RoleAssignment::create(['user_id' => $user['id'], 'role_id' => $role['id']]);
                    return $user['id'];
                },
            'assert'            => function($user_id) use($providers) {
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];
                    return $am->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\property\PropertyLot');
                },
            'rollback'          => function() {
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                    Group::search(['name', '=', 'test group'])->delete(true);
                    Role::search(['code', '=', 'test'])->delete(true);
                }
        ],

    '0204' => [
            'description'       => "Without permissions, on class without condo_id.",
            'help'              => "Verify permissions for a user who has no granted rights on a class that does not contain a condo_id. User should not be granted rights.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                },
            'act'               => function() use($providers) {
                    /** @var \equal\auth\AuthenticationManager */
                    $auth = $providers['auth'];
                    $auth->authenticate('user_test@example.com', 'abcd1234');
                    return $auth->getUserId();
                },
            'assert'            => function($user_id) use($providers) {
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];
                    return !($am->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\management\ManagingAgent'));
                },
            'rollback'          => function() use($providers) {
                    /** @var \equal\auth\AuthenticationManager */
                    $auth = $providers['auth'];
                    // switch back to root user
                    $auth->su();
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                }
        ],

    '0205' => [
            'description'       => "With Group permissions, on class, without condo_id.",
            'help'              => "Verify permissions for user with granted rights on class that does not contain a condo_id. User should be granted rights.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $group = Group::create(['name' => 'test group'])->first();
                    return [$user, $group];
                },
            'act'               => function($params) use($providers) {
                    [$user, $group] = $params;
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];
                    $am->addGroup($group['id'], $user['id']);
                    Permission::create(['class_name' => 'realestate\management\ManagingAgent', 'group_id' => $group['id'], 'rights' => 15]);
                    return $user['id'];
                },
            'assert'            => function($user_id) use($providers) {
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];
                    return $am->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\management\ManagingAgent');
                },
            'rollback'          => function() {
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                    Group::search(['name', '=', 'test group'])->delete(true);
                }
        ],

    '0206' => [
            'description'       => "With HR permissions, on class, without condo_id.",
            'help'              => "
                    Granting role for all condominiums on a class not holding a condo_id (ManagingAgent), with READ and UPDATE rights.
                    Testing UPDATE rights on class (ManagingAgent).
                    User should not be granted rights.
                ",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $group = Group::create(['name' => 'test group'])->first();
                    $role = Role::create(['name' => 'test role', 'code' => 'test'])
                        ->update(['groups_ids' => [$group['id']]])
                        ->first();
                    return [$user, $role, $group];
                },
            'act'               => function($params) use($providers) {
                    [$user, $role, $group] = $params;
                    Permission::create(['class_name' => 'realestate\management\ManagingAgent', 'group_id' => $group['id'], 'rights' => EQ_R_READ|EQ_R_UPDATE]);
                    RoleAssignment::create(['user_id' => $user['id'], 'role_id' => $role['id']]);
                    return $user['id'];
                },
            'assert'            => function($user_id) use($providers) {
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];
                    return !($am->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\management\ManagingAgent'));
                },
            'rollback'          => function() {
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                    Role::search(['code', '=', 'test'])->delete(true);
                    Group::search(['name', '=', 'test group'])->delete(true);
                }
        ],

    '0207' => [
            'description'       => "With insufficient HR permissions, on class, with condo_id.",
            'help'              => "
                    Granting role for all condominiums on a class holding a condo_id (PropertyLot), with READ rights.
                    Testing UPDATE rights on class (PropertyLot).
                    User should not be granted rights.
                ",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $group = Group::create(['name' => 'test group'])->first();
                    $role = Role::create(['name' => 'test role', 'code' => 'test'])
                        ->update(['groups_ids' => [$group['id']]])
                        ->first();
                    return [$user, $role, $group];
                },
            'act'               => function($params) use($providers) {
                    [$user, $role, $group] = $params;
                    Permission::create(['class_name' => 'realestate\property\PropertyLot', 'group_id' => $group['id'], 'rights' => EQ_R_READ]);
                    RoleAssignment::create(['user_id' => $user['id'], 'role_id' => $role['id']]);
                    return $user['id'];
                },
            'assert'            => function($user_id) use($providers) {
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];
                    return !($am->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\property\PropertyLot'));
                },
            'rollback'          => function() {
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                    Role::search(['code', '=', 'test'])->delete(true);
                    Group::search(['name', '=', 'test group'])->delete(true);
                }
        ],

    '0208' => [
            'description'       => "With specific HR permissions, on object, with class with condo_id.",
            'help'              => "
                    Granting role for a specific condominiums on a class holding a condo_id (PropertyLot).
                    Testing rights on specific item (PropertyLot).
                    User should be granted rights.
                ",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $group = Group::create(['name' => 'test group'])->first();
                    $role = Role::create(['name' => 'test role', 'code' => 'test'])
                        ->update(['groups_ids' => [$group['id']]])
                        ->first();
                    $condo = Condominium::create(['name' => 'test condo', 'managing_agent_id' => 1])->first();
                    return [$user, $role, $condo, $group];
                },
            'act'               => function($params) use($providers) {
                    [$user, $role, $condo, $group] = $params;
                    Permission::create(['class_name' => 'realestate\property\PropertyLot', 'group_id' => $group['id'], 'rights' => EQ_R_UPDATE]);
                    RoleAssignment::create(['user_id' => $user['id'], 'role_id' => $role['id'], 'condo_id' => $condo['id' ]]);
                    return $params;
                },
            'assert'            => function($params) use($providers) {
                    [$user, $role, $condo] = $params;
                    $lot = PropertyLot::create(['condo_id' => $condo['id'], 'name' => 'test lot', 'property_lot_ref' => '001', 'nature_id' => 1])->first();
                    return $providers['access']->userIsAllowed($user['id'], EQ_R_UPDATE, 'realestate\property\PropertyLot', [], $lot['id']);
                },
            'rollback'          => function() use($providers) {
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                    Role::search(['code', '=', 'test'])->delete(true);
                    Group::search(['name', '=', 'test group'])->delete(true);
                }
        ],

    '0209' => [
            'description'       => "With generic HR permissions, on object, with class with condo_id.",
            'help'              => "
                    Granting role for all condominiums on a class holding a condo_id (PropertyLot).
                    Testing rights on specific item (PropertyLot).
                    User should be granted rights.
                ",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $group = Group::create(['name' => 'test group'])->first();
                    $role = Role::create(['name' => 'test role', 'code' => 'test'])
                        ->update(['groups_ids' => [$group['id']]])
                        ->first();
                    $condo = Condominium::create(['name' => 'test condo', 'managing_agent_id' => 1])->first();
                    return [$user, $role, $condo, $group];
                },
            'act'               => function($params) use($providers) {
                    [$user, $role, $condo, $group] = $params;
                    Permission::create(['class_name' => 'realestate\property\PropertyLot', 'group_id' => $group['id'], 'rights' => EQ_R_UPDATE]);
                    RoleAssignment::create(['user_id' => $user['id'], 'role_id' => $role['id']]);
                    return $params;
                },
            'assert'            => function($params) use($providers) {
                    [$user, $role, $condo] = $params;
                    $lot = PropertyLot::create(['condo_id' => $condo['id'], 'name' => 'test lot', 'property_lot_ref' => '001', 'nature_id' => 1])->first();
                    return $providers['access']->userIsAllowed($user['id'], EQ_R_UPDATE, 'realestate\property\PropertyLot', [], $lot['id']);
                },
            'rollback'          => function() use($providers) {
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                    Role::search(['code', '=', 'test'])->delete(true);
                    Group::search(['name', '=', 'test group'])->delete(true);
                }
        ],

    '0210' => [
            'description'       => "With specific HR permissions, on class, with class with condo_id.",
            'help'              => "
                    Granting role for a specific condominium on a class holding a condo_id (Property Lot).
                    Testing rights on full class.
                    User should not be granted rights.
                ",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $group = Group::create(['name' => 'test group'])->first();
                    $role = Role::create(['name' => 'test role', 'code' => 'test'])
                        ->update(['groups_ids' => [$group['id']]])
                        ->first();
                    $condo = Condominium::create(['name' => 'test condo', 'managing_agent_id' => 1])->first();

                    return [$user, $role, $condo, $group];
                },
            'act'               => function($params) use($providers) {
                    [$user, $role, $condo, $group] = $params;
                    Permission::create(['class_name' => 'realestate\property\PropertyLot', 'group_id' => $group['id'], 'rights' => 15]);
                    RoleAssignment::create(['user_id' => $user['id'], 'role_id' => $role['id'], 'condo_id' => $condo['id' ]]);
                    return $user['id'];
                },
            'assert'            => function($user_id) use($providers) {
                    /** @var \fmt\access\AccessController       $am */
                    /** @var \equal\auth\AuthenticationManager  $auth */
                    ['auth' => $auth, 'access' => $am] = $providers;
                    $auth->su($user_id);
                    return !($am->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\property\PropertyLot'));
                },
            'rollback'          => function() use($providers) {
                    /** @var \equal\auth\AuthenticationManager */
                    $auth = $providers['auth'];
                    // switch back to root user
                    $auth->su();
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                    Role::search(['code', '=', 'test'])->delete(true);
                    Group::search(['name', '=', 'test group'])->delete(true);
                }
        ],
];
