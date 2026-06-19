<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use core\Group;
use hr\employee\Employee;
use hr\role\Role;
use hr\role\RoleAssignment;
use identity\Identity;
use identity\User;
use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => "Initializes an agency instance, and synchronizes it with global instance.",
    'params'        => [
        'instance_uuid' => [
            'type'          => 'string',
            'description'   => "The UUID of the agency instance, it is generated on global instance."
        ],
        'sync' => [
            'type'          => 'boolean',
            'description'   => "Must the new agency instance be synchronized with global instance ?",
            'help'          => "Default true because it'll mostly be used with synchronisation.",
            'default'       => true
        ],
        'global_access_token' => [
            'type'          => 'string',
            'description'   => "If sync, the token to access global instance's API."
        ],
        'global_instance_url' => [
            'type'          => 'string',
            'description'   => "If sync, the url of the global instance's API."
        ],
        'level' => [
            'type'              => 'string',
            'description'       => "Synchronisation level of the policy.",
            'selection'         => [
                'required',
                'recommended',
                'optional',
                'demo'
            ],
            'default'           => 'recommended'
        ],
        'create_users' => [
            'type'              => 'boolean',
            'description'       => "Create default users.",
            'default'           => true
        ]
    ],
    'access' => [
        'visibility'    => 'private'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

/**
 * Methods
 */

$createUser = function($user) {
    $employee = Employee::create()->first();

    $identity = Identity::create([
        'type_id'           => 1,
        'type'              => 'IN',
        'firstname'         => $user['firstname'],
        'lastname'          => $user['lastname'],
        'email'             => $user['email'],
        'has_parent'        => false,
        'nationality'       => 'BE',
        'lang_id'           => 2,
        'address_country'   => 'BE',
        'has_vat'           => false,
        'is_active'         => true,
        'employee_id'       => $employee['id']
    ])
        ->read(['name', 'email'])
        ->first();

    Employee::id($employee['id'])
        ->update(['identity_id' => $identity['id']])
        ->do('sync_from_identity');

    $user_data = [
        'login'         => $identity['email'],
        'language'      => 'fr',
        'validated'     => true,
        'instance_id'   => 1,
        'groups_ids'    => $user['groups_ids'],
    ];

    if($user['role_id']) {
        $role_assignment = RoleAssignment::create([
            'employee_id'   => $employee['id'],
            'role_id'       => $user['role_id'],
            'is_primary'    => true
        ])
            ->read(['id'])
            ->first();

        $user_data['role_assignments_ids'] = [$role_assignment['id']];
    }

    User::create($user_data)
        ->update(['identity_id' => $identity['id']])
        ->do('sync_from_identity');
};

/**
 * Action
 */

if($params['sync']) {
    if(empty($params['instance_uuid'])) {
        throw new Exception('uuid_must_be_provided_to_sync_with_global', EQ_ERROR_INVALID_PARAM);
    }

    if(empty($params['global_access_token'])) {
        throw new Exception('global_access_token_needed_to_sync_with_global', EQ_ERROR_NOT_ALLOWED);
    }

    if(empty($params['global_instance_url'])) {
        throw new Exception('global_instance_url_must_be_provided_to_sync_with_global', EQ_ERROR_INVALID_PARAM);
    }
}

if(constant('FMT_INSTANCE_TYPE') !== 'agency') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$map_init_packages = [
    'core'          => false,
    'identity'      => false,
    'communication' => false,
    'fmt'           => false
];
if(file_exists(EQ_BASEDIR."/log/packages.json")) {
    $json = file_get_contents(EQ_BASEDIR."/log/packages.json");
    $packages = json_decode($json, true);
    foreach($map_init_packages as $package => $value) {
        $map_init_packages[$package] = isset($packages[$package]);
    }
}

if($map_init_packages['fmt']) {
    throw new Exception('fmt_already_initialized', EQ_ERROR_NOT_ALLOWED);
}

// init core
eQual::run('do', 'init_package', [
    'package'           => 'core',
    'ignore_platform'   => true,
    'force'             => $map_init_packages['core']
]);

// init identity
eQual::run('do', 'init_package', [
    'package'           => 'identity',
    'import'            => true,
    'import_cascade'    => false,
    'ignore_platform'   => true,
    'force'             => $map_init_packages['identity']
]);

# init communication
eQual::run('do', 'init_package', [
    'package'           => 'communication',
    'import'            => true,
    'import_cascade'    => false,
    'ignore_platform'   => true,
    'force'             => $map_init_packages['communication']
]);

// init fmt (with data)
eQual::run('do', 'init_package', [
    'package'           => 'fmt',
    'import'            => true,
    'import_cascade'    => false,
    'ignore_platform'   => true
]);

// add fmt specific Collection and AccessController classes if they're missing from configuration
$config_json = file_get_contents(EQ_BASEDIR.'/config/config.json');
$config = json_decode($config_json, true);
if(!isset($config['SERVICE_ORM_COLLECTION_CLASS'], $config['SERVICE_ACCESS_ACCESSCONTROLLER'])) {
    $config['SERVICE_ORM_COLLECTION_CLASS'] = "fmt\\orm\\Collection";
    $config['SERVICE_ACCESS_ACCESSCONTROLLER'] = "fmt\\access\\AccessController";

    $new_config_json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents(EQ_BASEDIR.'/config/config.json', $new_config_json);
}

if(!empty($params['instance_uuid'])) {
    // set uuid to instance
    Instance::id(1)->update(['uuid' => $params['instance_uuid']]);

    if($params['sync']) {
        $global_instance_name = parse_url($params['global_instance_url'], PHP_URL_HOST);

        $global_instance = Instance::create([
                'server_id'     => 1,
                'instance_type' => 'global',
                'name'          => $global_instance_name,
                'url'           => $params['global_instance_url'],
                'access_token'  => $params['global_access_token']
            ])
            ->do('create_user')
            ->first();

        // fetch the sync policies from global and overwrite the existing ones
        eQual::run('do', 'fmt_sync_SyncPolicy_pull-from-global', ['reset' => true, 'level' => $params['level']]);

        // pull data from global depending on the sync policies
        eQual::run('do', 'fmt_sync_pull-from-global', ['accept' => true, 'level' => $params['level']]);
    }
}

if($params['create_users']) {
    $instance = Instance::id(1)
        ->read(['name'])
        ->first();

    $group_names = ['operators', 'users'];
    $groups = Group::search(['name', 'in', $group_names])
        ->read(['name'])
        ->get();
    $map_name_groups_ids = [];
    foreach($groups as $id => $role) {
        $map_name_groups_ids[$role['name']] = $id;
    }

    $role_codes = ['director', 'manager', 'accountant', 'condo_manager', 'assistant'];
    $roles = Role::search(['code', 'in', $role_codes])
        ->read(['code'])
        ->get();
    $map_codes_roles_ids = [];
    foreach($roles as $id => $role) {
        $map_codes_roles_ids[$role['code']] = $id;
    }

    $users_data = [
        [
            'firstname'             => 'First',
            'lastname'              => 'Operator',
            'email'                 => "operator@{$instance['name']}",
            'groups_ids'            => [$map_name_groups_ids['operators'], $map_name_groups_ids['users']]
        ],
        [
            'firstname'             => 'First',
            'lastname'              => 'Director',
            'email'                 => "director@{$instance['name']}",
            'groups_ids'            => [$map_name_groups_ids['users']],
            'role_id'               => $map_codes_roles_ids['director']
        ],
        [
            'firstname'             => 'First',
            'lastname'              => 'Manager',
            'email'                 => "manager@{$instance['name']}",
            'groups_ids'            => [$map_name_groups_ids['users']],
            'role_id'               => $map_codes_roles_ids['manager']
        ],
        [
            'firstname'             => 'First',
            'lastname'              => 'Accountant',
            'email'                 => "accountant@{$instance['name']}",
            'groups_ids'            => [$map_name_groups_ids['users']],
            'role_id'               => $map_codes_roles_ids['accountant']
        ],
        [
            'firstname'             => 'First',
            'lastname'              => 'Condo Manager',
            'email'                 => "condo-manager@{$instance['name']}",
            'groups_ids'            => [$map_name_groups_ids['users']],
            'role_id'               => $map_codes_roles_ids['condo_manager']
        ],
        [
            'firstname'             => 'First',
            'lastname'              => 'Assistant',
            'email'                 => "assistant@{$instance['name']}",
            'groups_ids'            => [$map_name_groups_ids['users']],
            'role_id'               => $map_codes_roles_ids['assistant']
        ]
    ];

    foreach($users_data as $user_data) {
        $createUser($user_data);
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->send();
