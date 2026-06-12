<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use identity\Group;
use identity\Identity;
use identity\User;
use core\Permission;
use hr\role\Role;
use hr\role\RoleAssignment;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\CommonArea;
use realestate\property\Condominium;
use realestate\property\NotaryOffice;
use realestate\property\OwnershipTransfer;
use realestate\property\OwnershipTransferAdjustmentLine;
use realestate\property\OwnershipTransferBankLoanLine;
use realestate\property\OwnershipTransferContact;
use realestate\property\OwnershipTransferFee;
use realestate\property\OwnershipTransferFundBalanceLine;
use realestate\property\OwnershipTransferFundRequestLine;
use realestate\property\PropertyEntrance;
use realestate\property\PropertyLot;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;
use realestate\property\PropertyLotStatutoryQuota;
use realestate\property\PropertyLotSuppliershipReference;
use realestate\property\Tenancy;
use realestate\property\TenancyTransfer;
use realestate\property\Tenant;

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
                    $ac = $providers['access'];
                    return $ac->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\property\PropertyLot');
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

    '0211' => [
            'description'       => "Owner user permissions are read-only.",
            'help'              => "
                    Granting READ and UPDATE permissions to a user linked to an Owner.
                    Testing READ, UPDATE, and mixed READ|UPDATE rights.
                    User should keep READ rights and be denied non-read rights.
                ",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $identity = Identity::create([
                        'type_id'   => 1,
                        'type'      => 'IN',
                        'firstname' => 'Owner',
                        'lastname'  => 'Access Test',
                        'lang_id'   => 2
                    ])->first();
                    $user = User::create(['login' => 'owner_access_test@example.com', 'password' => 'abcd1234', 'identity_id' => $identity['id']])->first();
                    Owner::create(['identity_id' => $identity['id']])->first();
                    $group = Group::create(['name' => 'test owner group'])->first();

                    return [$user, $group];
                },
            'act'               => function($params) use($providers) {
                    [$user, $group] = $params;
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];
                    $am->addGroup($group['id'], $user['id']);
                    Permission::create(['class_name' => 'realestate\management\ManagingAgent', 'group_id' => $group['id'], 'rights' => EQ_R_READ|EQ_R_UPDATE]);

                    return $user['id'];
                },
            'assert'            => function($user_id) use($providers) {
                    /** @var \fmt\access\AccessController */
                    $am = $providers['access'];

                    return $am->userIsAllowed($user_id, EQ_R_READ, 'realestate\management\ManagingAgent')
                        && !$am->userIsAllowed($user_id, EQ_R_UPDATE, 'realestate\management\ManagingAgent')
                        && !$am->userIsAllowed($user_id, EQ_R_READ|EQ_R_UPDATE, 'realestate\management\ManagingAgent');
                },
            'rollback'          => function() use($providers) {
                    $users = User::search(['login', '=', 'owner_access_test@example.com'])->read(['identity_id']);
                    $identity_ids = [];
                    foreach($users as $user) {
                        if(isset($user['identity_id'])) {
                            $identity_ids[] = $user['identity_id'];
                        }
                    }

                    User::search(['login', '=', 'owner_access_test@example.com'])->delete(true);
                    foreach($identity_ids as $identity_id) {
                        Owner::search(['identity_id', '=', $identity_id])->delete(true);
                        Identity::id($identity_id)->delete(true);
                    }

                    $groups_ids = Group::search(['name', '=', 'test owner group'])->ids();
                    if(count($groups_ids)) {
                        Permission::search(['group_id', 'in', $groups_ids])->delete(true);
                        Group::ids($groups_ids)->delete(true);
                    }
                }
        ],
    '0212' => [
            'description'   => "Test owner access to object configured with a condo_id",
            'help'          => "",
            'return'            => ['boolean'],
            'arrange'       => function() {
                $condo_1 = Condominium::create([
                    'name'              => 'test condo 1 for owner access test',
                    'managing_agent_id' => 1
                ])
                    ->read(['id'])
                    ->first();

                $condo_2 = Condominium::create([
                    'name'              => 'test condo 2 for owner access test',
                    'managing_agent_id' => 1
                ])
                    ->read(['id'])
                    ->first();

                $owner_identity = Identity::create([
                    'type_id'   => 1,
                    'type'      => 'IN',
                    'firstname' => 'Owner',
                    'lastname'  => 'Access Test',
                    'lang_id'   => 2
                ])
                    ->first();

                $user = User::create([
                    'login'         => 'owner_access_test@example.com',
                    'password'      => 'abcd1234',
                    'identity_id'   => $owner_identity['id']
                ])
                    ->first();

                Owner::create([
                    'condo_id'      => $condo_1['id'],
                    'identity_id'   => $owner_identity['id']
                ]);

                return [$condo_1, $condo_2, $user];
            },
            'act'           => function($data) use($providers) {
                /**
                 * @var \equal\orm\ObjectManager        $orm
                 * @var \fmt\access\AccessController    $am
                 */
                ['orm' => $orm, 'access' => $am] = $providers;

                [$condo_1, $condo_2, $user] = $data;

                $flatten = function(array $array) {
                    $res = [];
                    array_walk_recursive($array, function($a) use (&$res) { $res[] = $a; });
                    return $res;
                };

                $map_classes = [
                    'realestate' => [
                        'property' => [
                            Apportionment::getType(),
                            CommonArea::getType(),
                            Condominium::getType(),
                            NotaryOffice::getType(),
                            OwnershipTransfer::getType(),
                            OwnershipTransferAdjustmentLine::getType(),
                            OwnershipTransferBankLoanLine::getType(),
                            OwnershipTransferContact::getType(),
                            OwnershipTransferFee::getType(),
                            OwnershipTransferFundBalanceLine::getType(),
                            OwnershipTransferFundRequestLine::getType(),
                            PropertyEntrance::getType(),
                            PropertyLot::getType(),
                            PropertyLotApportionmentShare::getType(),
                            PropertyLotOwnership::getType(),
                            PropertyLotStatutoryQuota::getType(),
                            PropertyLotSuppliershipReference::getType(),
                            Tenancy::getType(),
                            TenancyTransfer::getType(),
                            Tenant::getType()
                        ]
                    ]
                ];

                $access_results = [
                    'condo_1' => [],
                    'condo_2' => []
                ];
                foreach($flatten($map_classes) as $class) {
                    $map_condos_objects_ids = [
                        'condo_1' => $orm->create($class, ['condo_id' => $condo_1['id']]),
                        'condo_2' => $orm->create($class, ['condo_id' => $condo_2['id']])
                    ];
                    foreach($map_condos_objects_ids as $condo_key => $object_id) {
                        foreach([EQ_R_READ, EQ_R_UPDATE] as $right) {
                            $access_results[$condo_key][$class][$right] = $am->userIsAllowed($user['id'], $right, $class, [], [$object_id]);
                        }
                    }

                    $orm->delete($class, [$map_condos_objects_ids['condo_1'], $map_condos_objects_ids['condo_2']]);
                }

                return $access_results;
            },
            'assert'        => function($access_results) {
                foreach($access_results['condo_1'] as $class => $right_result) {
                    if(!$right_result[EQ_R_READ]) {
                        // Supposed to be able to read
                        return false;
                    }
                    if($right_result[EQ_R_UPDATE]) {
                        // Not supposed to be able to update
                        return false;
                    }
                }

                foreach($access_results['condo_2'] as $class => $right_result) {
                    /*
                    # todo - uncomment check when access check on condo implemented
                    if($right_result[EQ_R_READ]) {
                        // Not supposed to be able to read
                        return false;
                    }
                    */
                    if($right_result[EQ_R_UPDATE]) {
                        // Not supposed to be able to update
                        return false;
                    }
                }

                return true;
            },
            'rollback'      => function() {
                Condominium::search(['name', 'in', ['test condo 1 for owner access test', 'test condo 2 for owner access test']])->delete(true);

                $users = User::search(['login', '=', 'owner_access_test@example.com'])->read(['identity_id']);
                $identity_ids = [];
                foreach($users as $user) {
                    if(isset($user['identity_id'])) {
                        $identity_ids[] = $user['identity_id'];
                    }
                }

                User::search(['login', '=', 'owner_access_test@example.com'])->delete(true);
                foreach($identity_ids as $identity_id) {
                    Owner::search(['identity_id', '=', $identity_id])->delete(true);
                    Identity::id($identity_id)->delete(true);
                }
            }
        ],
    '0213' => [
            'description'   => "Test owner access to object configured with a ownership_id",
            'help'          => "",
            'arrange'       => function() {
                $condo_1 = Condominium::create([
                    'name'              => 'test condo 1 for owner access test',
                    'managing_agent_id' => 1
                ])
                    ->read(['id'])
                    ->first();

                $owner_identity = Identity::create([
                    'type_id'   => 1,
                    'type'      => 'IN',
                    'firstname' => 'Owner',
                    'lastname'  => 'Access Test',
                    'lang_id'   => 2
                ])
                    ->first();

                $user = User::create([
                    'login'         => 'owner_access_test@example.com',
                    'password'      => 'abcd1234',
                    'identity_id'   => $owner_identity['id']
                ])
                    ->first();

                Owner::create([
                    'condo_id'      => $condo_1['id'],
                    'identity_id'   => $owner_identity['id']
                ]);

                $ownership_1 = Ownership::create([
                    'condo_id'          => $condo_1['id'],
                    'description'       => 'test ownership 1 for owner access test',
                    'date_from'         => time(),
                    'address_recipient' => 'Address ownership 1'
                ])
                    ->first();

                $ownership_2 = Ownership::create([
                    'condo_id'          => $condo_1['id'],
                    'description'       => 'test ownership 2 for owner access test',
                    'date_from'         => time(),
                    'address_recipient' => 'Address ownership 2'
                ])
                    ->first();

                return [$condo_1, $ownership_1, $ownership_2, $user];
            },
            'act'           => function($data) use($providers) {
                /**
                 * @var \equal\orm\ObjectManager        $orm
                 * @var \fmt\access\AccessController    $am
                 */
                ['orm' => $orm, 'access' => $am] = $providers;

                [$condo_1, $ownership_1, $ownership_2, $user] = $data;

                $flatten = function(array $array) {
                    $res = [];
                    array_walk_recursive($array, function($a) use (&$res) { $res[] = $a; });
                    return $res;
                };

                $map_classes = [
                    'realestate' => [
                        'property' => [
                            OwnershipTransferAdjustmentLine::getType(),
                            PropertyLotOwnership::getType()
                        ]
                    ]
                ];

                $access_results = [
                    'ownership_1' => [],
                    'ownership_2' => []
                ];
                foreach($flatten($map_classes) as $class) {
                    $map_condos_objects_ids = [
                        'ownership_1' => $orm->create($class, ['condo_id' => $condo_1['id'], 'ownership_id' => $ownership_1['id']]),
                        'ownership_2' => $orm->create($class, ['condo_id' => $condo_1['id'], 'ownership_id' => $ownership_2['id']])
                    ];
                    foreach($map_condos_objects_ids as $condo_key => $object_id) {
                        foreach([EQ_R_READ, EQ_R_UPDATE] as $right) {
                            $access_results[$condo_key][$class][$right] = $am->userIsAllowed($user['id'], $right, $class, [], [$object_id]);
                        }
                    }

                    $orm->delete($class, [$map_condos_objects_ids['ownership_1'], $map_condos_objects_ids['ownership_2']]);
                }

                return $access_results;
            },
            'assert'        => function($access_results) {
                foreach($access_results['ownership_1'] as $class => $right_result) {
                    if(!$right_result[EQ_R_READ]) {
                        // Supposed to be able to read
                        return false;
                    }
                    if($right_result[EQ_R_UPDATE]) {
                        // Not supposed to be able to update
                        return false;
                    }
                }

                foreach($access_results['ownership_2'] as $class => $right_result) {
                    /*
                    # todo - uncomment check when access check on ownership implemented
                    if($right_result[EQ_R_READ]) {
                        // Not supposed to be able to read
                        return false;
                    }
                    */
                    if($right_result[EQ_R_UPDATE]) {
                        // Not supposed to be able to update
                        return false;
                    }
                }

                return true;
            },
            'rollback'      => function() {
                Condominium::search(['name', 'in', ['test condo 1 for owner access test']])->delete(true);

                Ownership::search(['description', 'in', ['test ownership 1 for owner access test', 'test ownership 2 for owner access test']])->delete(true);

                $users = User::search(['login', '=', 'owner_access_test@example.com'])->read(['identity_id']);
                $identity_ids = [];
                foreach($users as $user) {
                    if(isset($user['identity_id'])) {
                        $identity_ids[] = $user['identity_id'];
                    }
                }

                User::search(['login', '=', 'owner_access_test@example.com'])->delete(true);
                foreach($identity_ids as $identity_id) {
                    Owner::search(['identity_id', '=', $identity_id])->delete(true);
                    Identity::id($identity_id)->delete(true);
                }
            }
        ],
    '0214' => [
            'description'   => "Test access on document by document_visibility.",
            'help'          => "",
            'arrange'       => function() {
                $condo_1 = Condominium::create([
                    'name'              => 'test condo 1 for owner access test',
                    'managing_agent_id' => 1
                ])
                    ->read(['id'])
                    ->first();

                $condo_2 = Condominium::create([
                    'name'              => 'test condo 2 for owner access test',
                    'managing_agent_id' => 1
                ])
                    ->read(['id'])
                    ->first();

                $ownership_1 = Ownership::create([
                    'condo_id'          => $condo_1['id'],
                    'description'       => 'test ownership 1 for owner access test',
                    'date_from'         => time(),
                    'address_recipient' => 'Address ownership 1'
                ])
                    ->first();

                $ownership_2 = Ownership::create([
                    'condo_id'          => $condo_1['id'],
                    'description'       => 'test ownership 2 for owner access test',
                    'date_from'         => time(),
                    'address_recipient' => 'Address ownership 2'
                ])
                    ->first();

                $owner_1_identity = Identity::create([
                    'type_id'   => 1,
                    'type'      => 'IN',
                    'firstname' => 'Owner',
                    'lastname'  => 'Access Test',
                    'lang_id'   => 2
                ])
                    ->first();

                $owner_1_user = User::create([
                    'login'         => 'owner_1_access_test@example.com',
                    'password'      => 'abcd1234',
                    'identity_id'   => $owner_1_identity['id']
                ])
                    ->first();

                $owner_1 = Owner::create([
                    'condo_id'      => $condo_1['id'],
                    'ownership_id'  => $ownership_1['id'],
                    'identity_id'   => $owner_1_identity['id']
                ])
                    ->first();

                $owner_2_identity = Identity::create([
                    'type_id'   => 1,
                    'type'      => 'IN',
                    'firstname' => 'Owner',
                    'lastname'  => 'Access Test',
                    'lang_id'   => 2
                ])
                    ->first();

                User::create([
                    'login'         => 'owner_2_access_test@example.com',
                    'password'      => 'abcd1234',
                    'identity_id'   => $owner_2_identity['id']
                ]);

                $owner_2 = Owner::create([
                    'condo_id'      => $condo_1['id'],
                    'ownership_id'  => $ownership_2['id'],
                    'identity_id'   => $owner_2_identity['id']
                ])
                    ->first();

                return [$condo_1, $condo_2, $ownership_1, $ownership_2, $owner_1_user, $owner_1, $owner_2];
            },
            'act'           => function($data) use($providers) {
                /**
                 * @var \equal\orm\ObjectManager            $orm
                 * @var \equal\auth\AuthenticationManager   $auth
                 */
                ['orm' => $orm, 'auth' => $auth] = $providers;

                [$condo_1, $condo_2, $ownership_1, $ownership_2, $owner_1_user, $owner_1, $owner_2] = $data;

                $orm->create(Document::getType(), ['document_visibility' => 'agency', 'hash' => 'agency']);

                $orm->create(Document::getType(), ['condo_id' => $condo_1['id'], 'document_visibility' => 'condo', 'hash' => 'condo_1']);
                $orm->create(Document::getType(), ['condo_id' => $condo_2['id'], 'document_visibility' => 'condo', 'hash' => 'condo_2']);

                $orm->create(Document::getType(), ['ownership_id' => $ownership_1['id'], 'document_visibility' => 'ownership', 'hash' => 'ownership_1']);
                $orm->create(Document::getType(), ['ownership_id' => $ownership_2['id'], 'document_visibility' => 'ownership', 'hash' => 'ownership_2']);

                $orm->create(Document::getType(), ['owner_id' => $owner_1['id'], 'document_visibility' => 'owner', 'hash' => 'owner_1']);
                $orm->create(Document::getType(), ['owner_id' => $owner_2['id'], 'document_visibility' => 'owner', 'hash' => 'owner_2']);

                $checkAccess = function($document_id, $user_id) use($auth) {
                    $auth->su($user_id);

                    $has_access = true;
                    try {
                        eQual::run('get', 'documents_document', ['id' => $document_id]);
                    }
                    catch(Exception $e) {
                        $has_access = false;
                    }

                    $auth->su();

                    return $has_access;
                };

                return [
                    'agency' => $checkAccess('agency', $owner_1_user['id']),
                    'condo' => [
                        'condo_1' => $checkAccess('condo_1', $owner_1_user['id']),
                        'condo_2' => $checkAccess('condo_2', $owner_1_user['id']),
                    ],
                    'ownership' => [
                        'ownership_1' => $checkAccess('ownership_1', $owner_1_user['id']),
                        'ownership_2' => $checkAccess('ownership_2', $owner_1_user['id']),
                    ],
                    'owner' => [
                        'owner_1' => $checkAccess('owner_1', $owner_1_user['id']),
                        'owner_2' => $checkAccess('owner_2', $owner_1_user['id'])
                    ]
                ];
            },
            'assert'        => function($access_results) {
                file_put_contents(QN_LOG_STORAGE_DIR.'/tmp.log', json_encode($access_results).PHP_EOL, FILE_APPEND | LOCK_EX);

                return
                    // access denied to agency document for owner 1
                    $access_results['agency'] === false
                    // access allowed to condo document, condo 1, for owner 1
                    && $access_results['condo']['condo_1'] === true
                    // access denied to condo document, condo 2, for owner 1
                    && $access_results['condo']['condo_2'] === false
                    // access allowed to ownership document, ownership 1, for owner 1
                    && $access_results['ownership']['ownership_1'] === true
                    // access denied to ownership document, ownership 2, for owner 1
                    && $access_results['ownership']['ownership_2'] === false
                    // access allowed to owner document, owner 1, for owner 1
                    && $access_results['owner']['owner_1'] === true
                    // access denied to owner document, owner 2, for owner 1
                    && $access_results['owner']['owner_2'] === false;
            },
            'rollback'      => function() {
                Condominium::search(['name', 'in', ['test condo 1 for owner access test', 'test condo 2 for owner access test']])->delete(true);

                Ownership::search(['description', 'in', ['test ownership 1 for owner access test', 'test ownership 2 for owner access test']])->delete(true);

                $users = User::search(['login', 'in', ['owner_1_access_test@example.com', 'owner_2_access_test@example.com']])->read(['identity_id']);
                $identity_ids = [];
                foreach($users as $user) {
                    if(isset($user['identity_id'])) {
                        $identity_ids[] = $user['identity_id'];
                    }
                }

                User::search(['login', 'in', ['owner_1_access_test@example.com', 'owner_2_access_test@example.com']])->delete(true);
                foreach($identity_ids as $identity_id) {
                    Owner::search(['identity_id', '=', $identity_id])->delete(true);
                    Identity::id($identity_id)->delete(true);
                }

                Document::search(['hash', 'in', ['agency', 'condo_1', 'condo_2', 'ownership_1', 'ownership_2', 'owner_1', 'owner_2']])->delete(true);
            }
    ]
];
