<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2024
    Original author(s): Cédric FRANCOYS
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/
use identity\User;
use hr\Permission;
use hr\role\Role;
use hr\role\RoleAssignment;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

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
            'help'              => "Create a user, check with a non existing group.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $role = Role::create(['name' => 'test role', 'code' => 'test'])->first();
                    return [$user, $role];
                },
            'act'               => function($params) use($providers) {
                    [$user, $role] = $params;
                    Permission::create(['class_name' => 'realestate\property\Condominium', 'role_id' => $role['id'], 'rights' => 15]);
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
            'description'       => "No permissions on class with condo_id.",
            'help'              => "Verify permissions for a user who has no granted rights on a class containing a condo_id.",
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
                    var_dump($user_id);
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];
                    return !($am->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\property\PropertyLot'));
                },
            'rollback'          => function() {
                    User::search(['login', '=', 'user_test@example.com'])->delete(true);
                }
        ],


    '0203' => [
            'description'       => "Permissions on class with condo_id.",
            'help'              => "Verify permissions for user with granted rights on class holding a condo_id.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $user = User::create(['login' => 'user_test@example.com', 'password' => 'abcd1234'])->first();
                    $role = Role::create(['name' => 'test role', 'code' => 'test'])->first();
                    return [$user, $role];
                },
            'act'               => function($params) use($providers) {
                    [$user, $role] = $params;
                    Permission::create(['class_name' => 'realestate\property\PropertyLot', 'role_id' => $role['id'], 'rights' => 15]);
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
                    Role::search(['code', '=', 'test'])->delete(true);
                }
        ]
];
