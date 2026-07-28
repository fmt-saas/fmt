<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\broadcast\Broadcast;
use documents\Document;
use documents\processing\DocumentProcess;
use hr\employee\Employee;
use identity\Group;
use identity\Identity;
use identity\User;
use core\Permission;
use hr\role\Role;
use hr\role\RoleAssignment;
use realestate\governance\Assembly;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\CommonArea;
use realestate\property\Condominium;
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

$issue_access_token = function(string $validity): string {
    $config_path = EQ_BASEDIR.'/config/config.json';
    $original_config = file_get_contents($config_path);
    $config = json_decode($original_config, true);

    if(!is_array($config)) {
        throw new Exception('invalid_config', EQ_ERROR_INVALID_CONFIG);
    }

    $config['AUTH_ACCESS_TOKEN_VALIDITY'] = $validity;
    $encoded_config = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if($encoded_config === false) {
        throw new Exception('config_encode_failed', EQ_ERROR_INVALID_CONFIG);
    }

    try {
        $written = file_put_contents($config_path, $encoded_config.PHP_EOL, LOCK_EX);
        if($written === false) {
            throw new Exception('config_write_failed', EQ_ERROR_INVALID_CONFIG);
        }

        $script = <<<'PHP'
            require_once getcwd().'/eq.lib.php';
            
            eQual::run('do', 'fmt_user_auth_pwd', [
                'login'     => 'user_test@example.com',
                'password'  => 'abcd1234'
            ], false, true);
            
            $token = eQual::get_last_context()
                ->httpResponse()
                ->cookie('access_token');
            
            if(!$token) {
                fwrite(STDERR, 'missing_access_token');
                exit(1);
            }
            
            echo $token;
            PHP;

        $process = proc_open(
            [
                PHP_BINARY,
                '-r',
                $script
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            EQ_BASEDIR
        );

        if(!is_resource($process)) {
            throw new Exception('token_process_start_failed', EQ_ERROR_UNKNOWN);
        }

        fclose($pipes[0]);
        $token = trim(stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        $error = trim(stream_get_contents($pipes[2]));
        fclose($pipes[2]);

        $status = proc_close($process);
        if($status !== 0) {
            throw new Exception('token_process_failed: '.$error, EQ_ERROR_UNKNOWN);
        }

        if(!$token) {
            throw new Exception('missing_access_token', EQ_ERROR_INVALID_USER);
        }
    }
    finally {
        file_put_contents($config_path, $original_config, LOCK_EX);
    }

    return $token;
};

/**
 * #memo - IMPORTANT - in general config.json, DEFAULT_RIGHTS is expected to be set to 0.
 */
$tests = [
    '0101' => [
            'description'       =>  "Retrieve Access Controller.",
            'help'              =>  "Access Controller service should be overridden by the one present in `fmt/lib` directory. ",
            'return'            =>  ['object'],
            'act'               =>  function () {
                    [$params, $providers] = eQual::announce([
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
                    if($class === Condominium::getType()) {
                        $map_condos_objects_ids = [
                            'condo_1' => $condo_1['id'],
                            'condo_2' => $condo_2['id']
                        ];
                    }
                    else {
                        $map_condos_objects_ids = [
                            'condo_1' => $orm->create($class, ['condo_id' => $condo_1['id'], 'state' => 'draft']),
                            'condo_2' => $orm->create($class, ['condo_id' => $condo_2['id'], 'state' => 'draft'])
                        ];

                        if($map_condos_objects_ids['condo_1'] <= 0 || $map_condos_objects_ids['condo_2'] <= 0) {
                            throw new Exception("fixture_creation_failed: {$class}", EQ_ERROR_UNKNOWN);
                        }
                    }

                    try {
                        foreach($map_condos_objects_ids as $condo_key => $object_id) {
                            foreach([EQ_R_READ, EQ_R_UPDATE] as $right) {
                                $access_results[$condo_key][$class][$right] = $am->userIsAllowed($user['id'], $right, $class, [], [$object_id]);
                            }
                        }
                    }
                    finally {
                        if($class !== Condominium::getType()) {
                            $orm->delete($class, [$map_condos_objects_ids['condo_1'], $map_condos_objects_ids['condo_2']]);
                        }
                    }
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
                    if($right_result[EQ_R_READ]) {
                        // Not supposed to be able to read
                        return false;
                    }
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
                        'ownership_1' => $orm->create($class, ['condo_id' => $condo_1['id'], 'ownership_id' => $ownership_1['id'], 'state' => 'draft']),
                        'ownership_2' => $orm->create($class, ['condo_id' => $condo_1['id'], 'ownership_id' => $ownership_2['id'], 'state' => 'draft'])
                    ];

                    if($map_condos_objects_ids['ownership_1'] <= 0 || $map_condos_objects_ids['ownership_2'] <= 0) {
                        throw new Exception("fixture_creation_failed: {$class}", EQ_ERROR_UNKNOWN);
                    }

                    try {
                        foreach($map_condos_objects_ids as $condo_key => $object_id) {
                            foreach([EQ_R_READ, EQ_R_UPDATE] as $right) {
                                $access_results[$condo_key][$class][$right] = $am->userIsAllowed($user['id'], $right, $class, [], [$object_id]);
                            }
                        }
                    }
                    finally {
                        $orm->delete($class, [$map_condos_objects_ids['ownership_1'], $map_condos_objects_ids['ownership_2']]);
                    }
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
            'description'   => "Test owner access on document by document_visibility.",
            'help'          => "Data controller documents_document is checked, get document by its hash.",
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

                $checkAccess = function($document_hash, $user_id) use($auth) {
                    $auth->su($user_id);

                    $has_access = true;
                    try {
                        eQual::run('get', 'documents_document', ['id' => $document_hash]);
                    }
                    catch(Exception $e) {
                        $has_access = false;
                    }

                    $auth->su();

                    return $has_access;
                };

                $access_results = [
                    'agency'    => null,
                    'condo'     => [],
                    'ownership' => [],
                    'owner'     => []
                ];

                $documents = [
                    'agency' => [
                        'result_group'  => 'agency',
                        'name'          => 'access-test-agency.txt',
                        'data'          => 'access-test-agency',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'agency'
                        ]
                    ],
                    'condo_1' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-1.txt',
                        'data'          => 'access-test-condo-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'condo_2' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-2.txt',
                        'data'          => 'access-test-condo-2',
                        'values'        => [
                            'condo_id'              => $condo_2['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'ownership_1' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-1.txt',
                        'data'          => 'access-test-ownership-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'ownership_2' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-2.txt',
                        'data'          => 'access-test-ownership-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_2['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'owner_1' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-1.txt',
                        'data'          => 'access-test-owner-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_1['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ],
                    'owner_2' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-2.txt',
                        'data'          => 'access-test-owner-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_2['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ]
                ];

                foreach($documents as $key => $document) {
                    $document_id = $orm->create(Document::getType(), array_merge([
                        'name'  => $document['name'],
                        'data'  => $document['data']
                    ], $document['values']));

                    if($document_id <= 0) {
                        throw new Exception("document_fixture_creation_failed: {$key}", EQ_ERROR_UNKNOWN);
                    }

                    $document_hash = Document::id($document_id)
                        ->read(['hash'])
                        ->first()['hash'] ?? null;

                    if(!$document_hash) {
                        throw new Exception("document_fixture_hash_missing: {$key}", EQ_ERROR_UNKNOWN);
                    }

                    if($document['result_group'] === 'agency') {
                        $access_results['agency'] = $checkAccess($document_hash, $owner_1_user['id']);
                    }
                    else {
                        $access_results[$document['result_group']][$key] = $checkAccess($document_hash, $owner_1_user['id']);
                    }

                    Document::id($document_id)->delete(true);
                }

                return $access_results;            },
            'assert'        => function($access_results) {
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
                Document::search(['name', 'in', [
                        'access-test-agency.txt',
                        'access-test-condo-1.txt',
                        'access-test-condo-2.txt',
                        'access-test-ownership-1.txt',
                        'access-test-ownership-2.txt',
                        'access-test-owner-1.txt',
                        'access-test-owner-2.txt'
                    ]])
                    ->delete(true);
            }
    ],
    '0215' => [
            'description'   => "Test owner access on document by document_visibility.",
            'help'          => "Data controller model_collect is checked, get document by its id.",
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

                $checkAccess = function($document_id, $user_id) use($auth) {
                    $auth->su($user_id);

                    try {
                        $documents = eQual::run('get', 'model_collect', [
                            'entity'    => 'documents\Document',
                            'domain'    => ['id', '=', $document_id]
                        ]);

                        $has_access = count($documents) === 1;
                    }
                    catch(Exception $e) {
                        $has_access = false;
                    }

                    $auth->su();

                    return $has_access;
                };

                $access_results = [
                    'agency'    => null,
                    'condo'     => [],
                    'ownership' => [],
                    'owner'     => []
                ];

                $documents = [
                    'agency' => [
                        'result_group'  => 'agency',
                        'name'          => 'access-test-agency.txt',
                        'data'          => 'access-test-agency',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'agency'
                        ]
                    ],
                    'condo_1' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-1.txt',
                        'data'          => 'access-test-condo-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'condo_2' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-2.txt',
                        'data'          => 'access-test-condo-2',
                        'values'        => [
                            'condo_id'              => $condo_2['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'ownership_1' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-1.txt',
                        'data'          => 'access-test-ownership-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'ownership_2' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-2.txt',
                        'data'          => 'access-test-ownership-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_2['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'owner_1' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-1.txt',
                        'data'          => 'access-test-owner-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_1['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ],
                    'owner_2' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-2.txt',
                        'data'          => 'access-test-owner-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_2['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ]
                ];

                foreach($documents as $key => $document) {
                    $document_id = $orm->create(Document::getType(), array_merge([
                        'name'  => $document['name'],
                        'data'  => $document['data']
                    ], $document['values']));

                    if($document_id <= 0) {
                        throw new Exception("document_fixture_creation_failed: {$key}", EQ_ERROR_UNKNOWN);
                    }

                    if($document['result_group'] === 'agency') {
                        $access_results['agency'] = $checkAccess($document_id, $owner_1_user['id']);
                    }
                    else {
                        $access_results[$document['result_group']][$key] = $checkAccess($document_id, $owner_1_user['id']);
                    }

                    Document::id($document_id)->delete(true);
                }

                return $access_results;
            },
            'assert'        => function($access_results) {
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
                Document::search(['name', 'in', [
                    'access-test-agency.txt',
                    'access-test-condo-1.txt',
                    'access-test-condo-2.txt',
                    'access-test-ownership-1.txt',
                    'access-test-ownership-2.txt',
                    'access-test-owner-1.txt',
                    'access-test-owner-2.txt'
                ]])
                    ->delete(true);
            }
        ],
    '0216' => [
            'description'   => "Test owner access on document by document_visibility.",
            'help'          => "Data controller documents_Document_collect is checked, get document by its id.",
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

                $checkAccess = function($document_id, $user_id) use($auth) {
                    $auth->su($user_id);

                    try {
                        $documents = eQual::run('get', 'documents_Document_collect', [
                            'entity'    => 'documents\Document',
                            'domain'    => ['id', '=', $document_id]
                        ]);

                        if(is_string($documents)) {
                            $documents = json_decode($documents);
                        }

                        $has_access = count($documents) === 1;
                    }
                    catch(Exception $e) {
                        $has_access = false;
                    }

                    $auth->su();

                    return $has_access;
                };

                $access_results = [
                    'agency'    => null,
                    'condo'     => [],
                    'ownership' => [],
                    'owner'     => []
                ];

                $documents = [
                    'agency' => [
                        'result_group'  => 'agency',
                        'name'          => 'access-test-agency.txt',
                        'data'          => 'access-test-agency',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'agency'
                        ]
                    ],
                    'condo_1' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-1.txt',
                        'data'          => 'access-test-condo-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'condo_2' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-2.txt',
                        'data'          => 'access-test-condo-2',
                        'values'        => [
                            'condo_id'              => $condo_2['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'ownership_1' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-1.txt',
                        'data'          => 'access-test-ownership-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'ownership_2' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-2.txt',
                        'data'          => 'access-test-ownership-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_2['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'owner_1' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-1.txt',
                        'data'          => 'access-test-owner-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_1['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ],
                    'owner_2' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-2.txt',
                        'data'          => 'access-test-owner-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_2['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ]
                ];

                foreach($documents as $key => $document) {
                    $document_id = $orm->create(Document::getType(), array_merge([
                        'name'  => $document['name'],
                        'data'  => $document['data']
                    ], $document['values']));

                    if($document_id <= 0) {
                        throw new Exception("document_fixture_creation_failed: {$key}", EQ_ERROR_UNKNOWN);
                    }

                    if($document['result_group'] === 'agency') {
                        $access_results['agency'] = $checkAccess($document_id, $owner_1_user['id']);
                    }
                    else {
                        $access_results[$document['result_group']][$key] = $checkAccess($document_id, $owner_1_user['id']);
                    }

                    Document::id($document_id)->delete(true);
                }

                return $access_results;
            },
            'assert'        => function($access_results) {
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
                Document::search(['name', 'in', [
                    'access-test-agency.txt',
                    'access-test-condo-1.txt',
                    'access-test-condo-2.txt',
                    'access-test-ownership-1.txt',
                    'access-test-ownership-2.txt',
                    'access-test-owner-1.txt',
                    'access-test-owner-2.txt'
                ]])
                    ->delete(true);
            }
        ],
    '0217' => [
            'description'   => "Test owner delete document restricted by document_visibility.",
            'help'          => "For the moment owners cannot delete documents.",
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

                $checkDelete = function($document_id, $user_id) use($auth) {
                    $documents = eQual::run('get', 'model_collect', [
                        'entity'    => 'documents\Document',
                        'domain'    => ['id', '=', $document_id]
                    ]);

                    if(count($documents) !== 1) {
                        throw new Exception("document_does_not_exist", EQ_ERROR_UNKNOWN);
                    }

                    $auth->su($user_id);

                    try {
                        Document::id($document_id)->delete();
                    }
                    catch(Exception $e) {
                    }

                    $auth->su();

                    $documents = eQual::run('get', 'model_collect', [
                        'entity'    => 'documents\Document',
                        'domain'    => ['id', '=', $document_id]
                    ]);

                    $can_delete = false;
                    if(empty($documents)) {
                        $can_delete = true;
                    }

                    return $can_delete;
                };

                $delete_results = [
                    'agency'    => null,
                    'condo'     => [],
                    'ownership' => [],
                    'owner'     => []
                ];

                $documents = [
                    'agency' => [
                        'result_group'  => 'agency',
                        'name'          => 'access-test-agency.txt',
                        'data'          => 'access-test-agency',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'agency'
                        ]
                    ],
                    'condo_1' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-1.txt',
                        'data'          => 'access-test-condo-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'condo_2' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-2.txt',
                        'data'          => 'access-test-condo-2',
                        'values'        => [
                            'condo_id'              => $condo_2['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'ownership_1' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-1.txt',
                        'data'          => 'access-test-ownership-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'ownership_2' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-2.txt',
                        'data'          => 'access-test-ownership-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_2['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'owner_1' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-1.txt',
                        'data'          => 'access-test-owner-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_1['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ],
                    'owner_2' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-2.txt',
                        'data'          => 'access-test-owner-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_2['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ]
                ];

                foreach($documents as $key => $document) {
                    $document_id = $orm->create(Document::getType(), array_merge([
                        'name'  => $document['name'],
                        'data'  => $document['data']
                    ], $document['values']));

                    if($document_id <= 0) {
                        throw new Exception("document_fixture_creation_failed: {$key}", EQ_ERROR_UNKNOWN);
                    }

                    if($document['result_group'] === 'agency') {
                        $delete_results['agency'] = $checkDelete($document_id, $owner_1_user['id']);
                    }
                    else {
                        $delete_results[$document['result_group']][$key] = $checkDelete($document_id, $owner_1_user['id']);
                    }

                    Document::id($document_id)->delete(true);
                }

                return $delete_results;
            },
            'assert'        => function($delete_results) {
                return
                    // delete denied to agency document for owner 1
                    $delete_results['agency'] === false
                    // delete denied to condo document, condo 1, for owner 1
                    && $delete_results['condo']['condo_1'] === false
                    // delete denied to condo document, condo 2, for owner 1
                    && $delete_results['condo']['condo_2'] === false
                    // delete denied to ownership document, ownership 1, for owner 1
                    && $delete_results['ownership']['ownership_1'] === false
                    // delete denied to ownership document, ownership 2, for owner 1
                    && $delete_results['ownership']['ownership_2'] === false
                    // delete denied to owner document, owner 1, for owner 1
                    && $delete_results['owner']['owner_1'] === false
                    // delete denied to owner document, owner 2, for owner 1
                    && $delete_results['owner']['owner_2'] === false;
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
                Document::search(['name', 'in', [
                    'access-test-agency.txt',
                    'access-test-condo-1.txt',
                    'access-test-condo-2.txt',
                    'access-test-ownership-1.txt',
                    'access-test-ownership-2.txt',
                    'access-test-owner-1.txt',
                    'access-test-owner-2.txt'
                ]])
                    ->delete(true);
            }
        ],
    '0218' => [
            'description'   => "Test owner delete document restricted by document_visibility.",
            'help'          => "For the moment owners cannot delete documents.",
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

                $checkArchive = function($document_id, $user_id) use($auth) {
                    $documents = eQual::run('get', 'model_collect', [
                        'entity'    => 'documents\Document',
                        'domain'    => ['id', '=', $document_id]
                    ]);

                    if(count($documents) !== 1) {
                        throw new Exception("document_does_not_exist", EQ_ERROR_UNKNOWN);
                    }

                    $auth->su($user_id);

                    try {
                        Document::id($document_id)->archive();
                    }
                    catch(Exception $e) {
                    }

                    $auth->su();

                    $documents = eQual::run('get', 'model_collect', [
                        'entity'    => 'documents\Document',
                        'domain'    => ['id', '=', $document_id]
                    ]);

                    $can_delete = false;
                    if(empty($documents)) {
                        $can_delete = true;
                    }

                    return $can_delete;
                };

                $archive_results = [
                    'agency'    => null,
                    'condo'     => [],
                    'ownership' => [],
                    'owner'     => []
                ];

                $documents = [
                    'agency' => [
                        'result_group'  => 'agency',
                        'name'          => 'access-test-agency.txt',
                        'data'          => 'access-test-agency',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'agency'
                        ]
                    ],
                    'condo_1' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-1.txt',
                        'data'          => 'access-test-condo-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'condo_2' => [
                        'result_group'  => 'condo',
                        'name'          => 'access-test-condo-2.txt',
                        'data'          => 'access-test-condo-2',
                        'values'        => [
                            'condo_id'              => $condo_2['id'],
                            'document_visibility'   => 'condo'
                        ]
                    ],
                    'ownership_1' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-1.txt',
                        'data'          => 'access-test-ownership-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'ownership_2' => [
                        'result_group'  => 'ownership',
                        'name'          => 'access-test-ownership-2.txt',
                        'data'          => 'access-test-ownership-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_2['id'],
                            'document_visibility'   => 'ownership'
                        ]
                    ],
                    'owner_1' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-1.txt',
                        'data'          => 'access-test-owner-1',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_1['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ],
                    'owner_2' => [
                        'result_group'  => 'owner',
                        'name'          => 'access-test-owner-2.txt',
                        'data'          => 'access-test-owner-2',
                        'values'        => [
                            'condo_id'              => $condo_1['id'],
                            'ownership_id'          => $ownership_1['id'],
                            'owner_id'              => $owner_2['id'],
                            'document_visibility'   => 'owner'
                        ]
                    ]
                ];

                foreach($documents as $key => $document) {
                    $document_id = $orm->create(Document::getType(), array_merge([
                        'name'  => $document['name'],
                        'data'  => $document['data']
                    ], $document['values']));

                    if($document_id <= 0) {
                        throw new Exception("document_fixture_creation_failed: {$key}", EQ_ERROR_UNKNOWN);
                    }

                    if($document['result_group'] === 'agency') {
                        $archive_results['agency'] = $checkArchive($document_id, $owner_1_user['id']);
                    }
                    else {
                        $archive_results[$document['result_group']][$key] = $checkArchive($document_id, $owner_1_user['id']);
                    }

                    Document::id($document_id)->delete(true);
                }

                return $archive_results;
            },
            'assert'        => function($archive_results) {
                return
                    // archive denied to agency document for owner 1
                    $archive_results['agency'] === false
                    // archive denied to condo document, condo 1, for owner 1
                    && $archive_results['condo']['condo_1'] === false
                    // archive denied to condo document, condo 2, for owner 1
                    && $archive_results['condo']['condo_2'] === false
                    // archive denied to ownership document, ownership 1, for owner 1
                    && $archive_results['ownership']['ownership_1'] === false
                    // archive denied to ownership document, ownership 2, for owner 1
                    && $archive_results['ownership']['ownership_2'] === false
                    // archive denied to owner document, owner 1, for owner 1
                    && $archive_results['owner']['owner_1'] === false
                    // archive denied to owner document, owner 2, for owner 1
                    && $archive_results['owner']['owner_2'] === false;
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
                Document::search(['name', 'in', [
                    'access-test-agency.txt',
                    'access-test-condo-1.txt',
                    'access-test-condo-2.txt',
                    'access-test-ownership-1.txt',
                    'access-test-ownership-2.txt',
                    'access-test-owner-1.txt',
                    'access-test-owner-2.txt'
                ]])
                    ->delete(true);
            }
        ],

    '0219' => [
            'description'   => "Test employee access to object configured with a condo_id",
            'help'          => "",
            'return'        => ['boolean'],
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

                $employee_identity = Identity::create([
                    'type_id'   => 1,
                    'type'      => 'IN',
                    'firstname' => 'Employee',
                    'lastname'  => 'Access Test',
                    'lang_id'   => 2
                ])
                    ->first();

                $user = User::create([
                    'login'         => 'employee_access_test@example.com',
                    'password'      => 'abcd1234',
                    'identity_id'   => $employee_identity['id']
                ])
                    ->first();

                Employee::create([
                    'identity_id'   => $employee_identity['id']
                ]);

                RoleAssignment::create([
                    'condo_id'  => $condo_1['id'],
                    'user_id'   => $user['id'],
                    'role_id'   => 1
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
                    if($class === Condominium::getType()) {
                        $map_condos_objects_ids = [
                            'condo_1' => $condo_1['id'],
                            'condo_2' => $condo_2['id']
                        ];
                    }
                    else {
                        $map_condos_objects_ids = [
                            'condo_1' => $orm->create($class, ['condo_id' => $condo_1['id'], 'state' => 'draft']),
                            'condo_2' => $orm->create($class, ['condo_id' => $condo_2['id'], 'state' => 'draft'])
                        ];

                        if($map_condos_objects_ids['condo_1'] <= 0 || $map_condos_objects_ids['condo_2'] <= 0) {
                            throw new Exception("fixture_creation_failed: {$class}", EQ_ERROR_UNKNOWN);
                        }
                    }

                    try {
                        foreach($map_condos_objects_ids as $condo_key => $object_id) {
                            foreach([EQ_R_READ, EQ_R_UPDATE] as $right) {
                                $access_results[$condo_key][$class][$right] = $am->userIsAllowed($user['id'], $right, $class, [], [$object_id]);
                            }
                        }
                    }
                    finally {
                        if($class !== Condominium::getType()) {
                            $orm->delete($class, [$map_condos_objects_ids['condo_1'], $map_condos_objects_ids['condo_2']]);
                        }
                    }
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
                    # todo - uncomment check when access check on employee implemented
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

                $users = User::search(['login', '=', 'employee_access_test@example.com'])->read(['identity_id']);
                $identity_ids = [];
                foreach($users as $user) {
                    if(isset($user['identity_id'])) {
                        $identity_ids[] = $user['identity_id'];
                    }
                }

                User::search(['login', '=', 'employee_access_test@example.com'])->delete(true);
                foreach($identity_ids as $identity_id) {
                    Employee::search(['identity_id', '=', $identity_id])->delete(true);
                    Identity::id($identity_id)->delete(true);
                }
            }
        ],

    '0220' => [
            'description'   => "Employee access on documents.",
            'help'          => "Verify an employee user can access documents whatever document_visibility value is set.",
            'return'        => ['array'],
            'arrange'       => function() use($providers) {
                /**
                 * @var \equal\orm\ObjectManager $orm
                 */
                ['orm' => $orm] = $providers;

                $employee = Employee::create([
                        'firstname' => 'Employee',
                        'lastname'  => 'Document Access Test'
                    ])
                    ->read(['identity_id'])
                    ->first();

                if(!$employee || empty($employee['identity_id'])) {
                    throw new Exception('employee_fixture_creation_failed', EQ_ERROR_UNKNOWN);
                }

                $user = User::create([
                        'login'       => 'employee_document_access_test@example.com',
                        'password'    => 'abcd1234',
                        'identity_id' => $employee['identity_id']
                    ])
                    ->read(['id', 'employee_id'])
                    ->first();

                if(!$user || empty($user['employee_id'])) {
                    throw new Exception('employee_user_fixture_creation_failed', EQ_ERROR_UNKNOWN);
                }

                $documents = [];
                foreach(['agency', 'condo', 'ownership', 'owner', 'suppliership'] as $visibility) {
                    $document_id = $orm->create(Document::getType(), [
                        'name'                => "access-test-employee-{$visibility}.txt",
                        'data'                => "access-test-employee-{$visibility}",
                        'document_visibility' => $visibility
                    ]);

                    if($document_id <= 0) {
                        throw new Exception("document_fixture_creation_failed: {$visibility}", EQ_ERROR_UNKNOWN);
                    }

                    $document_hash = Document::id($document_id)
                        ->read(['hash'])
                        ->first()['hash'] ?? null;

                    if(!$document_hash) {
                        throw new Exception("document_fixture_hash_missing: {$visibility}", EQ_ERROR_UNKNOWN);
                    }

                    $documents[$visibility] = [
                        'id'   => $document_id,
                        'hash' => $document_hash
                    ];
                }

                return [$user['id'], $documents];
            },
            'act'           => function($data) use($providers) {
                /**
                 * @var \equal\auth\AuthenticationManager $auth
                 */
                ['auth' => $auth] = $providers;

                [$user_id, $documents] = $data;
                $document_ids = array_column($documents, 'id');
                $results = [
                    'download' => [],
                    'read'     => false,
                    'search'   => false
                ];

                $auth->su($user_id);

                try {
                    foreach($documents as $visibility => $document) {
                        try {
                            eQual::run('get', 'documents_document', ['id' => $document['hash']]);
                            $results['download'][$visibility] = true;
                        }
                        catch(Exception $e) {
                            $results['download'][$visibility] = false;
                        }
                    }

                    try {
                        $read_ids = Document::ids($document_ids)
                            ->read(['id'])
                            ->ids();
                        $results['read'] = count(array_intersect($document_ids, $read_ids)) === count($document_ids);
                    }
                    catch(Exception $e) {
                        $results['read'] = false;
                    }

                    try {
                        $search_ids = Document::search(['id', 'in', $document_ids])->ids();
                        $results['search'] = count(array_intersect($document_ids, $search_ids)) === count($document_ids);
                    }
                    catch(Exception $e) {
                        $results['search'] = false;
                    }
                }
                finally {
                    $auth->su();
                }

                return $results;
            },
            'assert'        => function($results) {
                return
                    $results['read'] === true
                    && $results['search'] === true
                    && !in_array(false, $results['download'], true);
            },
            'rollback'      => function() use($providers) {
                /** @var \equal\auth\AuthenticationManager $auth */
                $auth = $providers['auth'];
                $auth->su();

                Document::search(['name', 'in', [
                        'access-test-employee-agency.txt',
                        'access-test-employee-condo.txt',
                        'access-test-employee-ownership.txt',
                        'access-test-employee-owner.txt',
                        'access-test-employee-suppliership.txt'
                    ]])
                    ->delete(true);

                $users = User::search(['login', '=', 'employee_document_access_test@example.com'])->read(['identity_id']);
                $identity_ids = [];
                foreach($users as $user) {
                    if(isset($user['identity_id'])) {
                        $identity_ids[] = $user['identity_id'];
                    }
                }

                User::search(['login', '=', 'employee_document_access_test@example.com'])->delete(true);
                Employee::search(['lastname', '=', 'Document Access Test'])->delete(true);

                foreach($identity_ids as $identity_id) {
                    Identity::id($identity_id)->delete(true);
                }
            }
    ],

    '0221' => [
            'description'   => "Test access token expiration.",
            'help'          => "Verify that access token expires after defined time.",
            'return'        => ['array'],
            'arrange'       => function() {
                User::create([
                    'login'     => 'user_test@example.com',
                    'password'  => 'abcd1234',
                    'validated' => true
                ])
                    ->first();
            },
            'act'           => function() use($providers, $issue_access_token) {
                /** @var \equal\auth\AuthenticationManager $auth */
                $auth = $providers['auth'];
                $token = $issue_access_token('1s');
                $decoded_token = $auth->decodeToken($token)['payload'];

                sleep(2);

                return [
                    'validity'  => $decoded_token['exp'] - $decoded_token['iat'],
                    'expired'   => $auth->retrieveAccessToken($token) === null
                ];
            },
            'assert'        => function($data) {
                return $data['validity'] === 1
                    && $data['expired'] === true;
            },
            'rollback'      => function() {
                User::search(['login', '=', 'user_test@example.com'])->delete(true);
            }
        ],
    '0222' => [
            'description'   => "Test access token expiration.",
            'help'          => "Verify that access token is not expired.",
            'return'        => ['array'],
            'arrange'       => function() {
                User::create([
                    'login'     => 'user_test@example.com',
                    'password'  => 'abcd1234',
                    'validated' => true
                ])
                    ->first();
            },
            'act'           => function() use($providers, $issue_access_token) {
                /** @var \equal\auth\AuthenticationManager $auth */
                $auth = $providers['auth'];
                $token = $issue_access_token('1s');
                $decoded_token = $auth->decodeToken($token)['payload'];

                return [
                    'validity'  => $decoded_token['exp'] - $decoded_token['iat'],
                    'valid'     => is_array($auth->retrieveAccessToken($token))
                ];
            },
            'assert'        => function($data) {
                return $data['validity'] === 1
                    && $data['valid'] === true;
            },
            'rollback'      => function() {
                User::search(['login', '=', 'user_test@example.com'])->delete(true);
            }
    ],

    '0223' => [
            'description'   => "Test that document cannot be delete if linked to assembly.",
            'help'          => "A document linked to an assembly cannot be deleted.",
            'return'        => ['array'],
            'arrange'       => function() {
                $condo = Condominium::create([
                    'name'              => 'test condo for document cannot delete test',
                    'managing_agent_id' => 1
                ])
                    ->read(['id'])
                    ->first();

                $assembly = Assembly::create([
                    'condo_id'              => $condo['id'],
                    'name'                  => "test assembly for document cannot delete test",
                    'assembly_template_id'  => 1
                ])
                    ->first();

                $document = Document::create([
                    'condo_id'      => $condo['id'],
                    'name'          => 'test document for document cannot delete test',
                    'assembly_id'   => $assembly['id']
                ])
                    ->first(true);

                return [$document];
            },
            'act'           => function($data) {
                [$document] = $data;

                try {
                    Document::id($document['id'])->delete(true);
                }
                catch(Exception $e) {
                }

                return [$document];
            },
            'assert'        => function($data) {
                [$document] = $data;

                $del_document = Document::id($document['id'])->first();

                return $del_document !== null;
            },
            'rollback'      => function() {
                Condominium::search(['name', 'in', ['test condo for document cannot delete test']])->delete(true);

                Assembly::search(['name', '=', 'test assembly for document cannot delete test'])->delete(true);
                Document::search(['name', '=', 'test document for document cannot delete test'])->delete(true);
            }
        ],
    '0224' => [
            'description'   => "Test that document cannot be delete if linked to broadcast.",
            'help'          => "A document linked to a broadcast cannot be deleted.",
            'return'        => ['array'],
            'arrange'       => function() {
                $condo = Condominium::create([
                    'name'              => 'test condo for document cannot delete test',
                    'managing_agent_id' => 1
                ])
                    ->first();

                $broadcast = Broadcast::create([
                    'condo_id'              => $condo['id'],
                    'name'                  => "test broadcast for document cannot delete test"
                ])
                    ->first();

                $document = Document::create([
                    'condo_id'          => $condo['id'],
                    'name'              => 'test document for document cannot delete test',
                    'broadcasts_ids'    => [$broadcast['id']]
                ])
                    ->first(true);

                return [$document];
            },
            'act'           => function($data) {
                [$document] = $data;

                try {
                    Document::id($document['id'])->delete(true);
                }
                catch(Exception $e) {
                }

                return [$document];
            },
            'assert'        => function($data) {
                [$document] = $data;

                $del_document = Document::id($document['id'])->first();

                return $del_document !== null;
            },
            'rollback'      => function() {
                Condominium::search(['name', 'in', ['test condo for document cannot delete test']])->delete(true);

                Broadcast::search(['name', '=', 'test broadcast for document cannot delete test'])->delete(true);
                Document::search(['name', '=', 'test document for document cannot delete test'])->delete(true);
            }
        ],
    '0225' => [
            'description'   => "Test that document cannot be delete if linked to pending process.",
            'help'          => "A document linked to a process cannot be deleted.",
            'arrange'       => function() {
                $condo = Condominium::create([
                    'name'              => 'test condo for document cannot delete test',
                    'managing_agent_id' => 1
                ])
                    ->first();

                $process = DocumentProcess::create([
                    'condo_id'  => $condo['id'],
                    'name'      => "test process for document cannot delete test"
                ])
                    ->first();

                $document = Document::create([
                    'condo_id'              => $condo['id'],
                    'name'                  => 'test document for document cannot delete test',
                    'document_process_id'   => $process['id']
                ])
                    ->first(true);

                return [$document];
            },
            'act'           => function($data) {
                [$document] = $data;

                try {
                    Document::id($document['id'])->delete(true);
                }
                catch(Exception $e) {
                }

                return [$document];
            },
            'assert'        => function($data) {
                [$document] = $data;

                $del_document = Document::id($document['id'])->first();

                return $del_document !== null;
            },
            'rollback'      => function() {
                Condominium::search(['name', 'in', ['test condo for document cannot delete test']])->delete(true);

                DocumentProcess::search(['name', '=', 'test process for document cannot delete test'])->delete(true);
                Document::search(['name', '=', 'test document for document cannot delete test'])->delete(true);
            }
        ],
    '0226' => [
            'description'   => "Test that document cannot be delete if linked to transfer.",
            'help'          => "A document linked to a transfer cannot be deleted.",
            'arrange'       => function() {
                $condo = Condominium::create([
                    'name'              => 'test condo for document cannot delete test',
                    'managing_agent_id' => 1
                ])
                    ->first();

                $transfer = OwnershipTransfer::create([
                    'condo_id'  => $condo['id']
                ])
                    ->first();

                $document = Document::create([
                    'condo_id'              => $condo['id'],
                    'name'                  => 'test document for document cannot delete test',
                    'ownership_transfer_id' => $transfer['id']
                ])
                    ->first(true);

                return [$document];
            },
            'act'           => function($data) {
                [$document] = $data;

                try {
                    Document::id($document['id'])->delete(true);
                }
                catch(Exception $e) {
                }

                return [$document];
            },
            'assert'        => function($data) {
                [$document] = $data;

                $del_document = Document::id($document['id'])->first();

                return $del_document !== null;
            },
            'rollback'      => function() {
                Condominium::search(['name', 'in', ['test condo for document cannot delete test']])->delete(true);

                OwnershipTransfer::search(['name', '=', 'test transfer for document cannot delete test'])->delete(true);
                Document::search(['name', '=', 'test document for document cannot delete test'])->delete(true);
            }
        ]
];
