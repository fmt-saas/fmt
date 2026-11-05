<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use hr\employee\Employee;

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
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['admins', 'operators']
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$ids = array_merge((array) ($params['id'] ?? []), $params['ids'] ?? []);

Employee::ids($ids)->do('create_user_account');

$context->httpResponse()
        ->status(201)
        ->send();
