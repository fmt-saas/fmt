<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use hr\employee\Employee;
use hr\role\RoleAssignment;
use identity\Identity;
use identity\User;

[$params, $providers] = eQual::announce([
    'description'   => "Create a User account for a single Employee or a group of Employees.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The specific Employee the requests refers to.",
            'foreign_object'    => 'hr\employee\Employee',
            'required'          => true
        ],
        'ids' => [
            'type'              => 'one2many',
            'foreign_object'    => 'hr\employee\Employee',
            'description'       => 'List of Employees the requests refers to.',
            'default'           => []
        ]
    ],
    'constants'     => ['AUTH_SECRET_KEY'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['admins', 'operators']
    ],
    'providers'     => ['context', 'dispatch', 'auth', 'orm']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\orm\ObjectManager            $orm
 */
['context' => $context, 'dispatch' => $dispatch, 'auth' => $auth, 'orm' => $orm] = $providers;


$user_id = $auth->userId();

// we need root privilege
$auth->su();

$ids = array_merge((array) ($params['id'] ?? []), $params['ids'] ?? []);

$employees = Employee::ids($ids)->read(['identity_id', 'role_assignments_ids']);

foreach($employees as $employee_id => $employee) {
    $identity = Identity::id($employee['identity_id'])
        ->read(['name', 'email', 'user_id'])
        ->first();

    // #memo in case the user already exists, simply ignore the request
    if(!$identity['user_id']) {
        if(!$identity['email']) {
            trigger_error("APP::ignored user creation for identity {$identity['name']} with no email.", EQ_REPORT_WARNING);
            continue;
        }

        $new_user_id = $orm->create(User::getType(), [
                // #memo - Capabilities for EQ_R_UPDATE are based on 'creator' context
                'creator'       => $user_id,
                'login'         => $identity['email'],
                'language'      => 'fr',
                'validated'     => true,
                'is_employee'   => true,
                'is_owner'      => false,
                // users
                'groups_ids'    => [2]
            ]);

        if($new_user_id <= 0) {
            trigger_error("APP::error at new user creation for identity {$identity['name']}.", EQ_REPORT_WARNING);
            continue;
        }

        User::id($new_user_id)
            ->update(['identity_id' => $identity['id']])
            ->do('sync_from_identity');

        Employee::id($employee_id)->update(['user_id' => $new_user_id]);
    }
    // force refreshing role assignments
    RoleAssignment::ids($employee['role_assignments_ids'])->read(['user_id']);
}

$auth->su($user_id);

$context->httpResponse()
        ->status(201)
        ->send();
