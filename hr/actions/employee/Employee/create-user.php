<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use hr\employee\Employee;
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
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;


$ids = array_merge((array) ($params['id'] ?? []), $params['ids'] ?? []);

$employees = Employee::ids($ids)->read(['identity_id']);

foreach($employees as $employee_id => $employee) {
    $identity = Identity::id($employee['identity_id'])->read(['name', 'email', 'user_id'])->first();

    // #memo in case the user already exists, simply ignore the request
    if(!$identity['user_id']) {
        if(!$identity['email']) {
            trigger_error("APP::ignored user creation for identity {$identity['name']} with no email.", EQ_REPORT_WARNING);
            continue;
        }
        // search for an email address
        User::create([
                'login'         => $identity['email'],
                'language'      => 'fr',
                'validated'     => true,
                // users
                'groups_ids'    => [2]
            ])
            ->update(['identity_id' => $identity['id']])
            ->do('sync_from_identity');
    }
}

$context->httpResponse()
        ->status(201)
        ->send();
