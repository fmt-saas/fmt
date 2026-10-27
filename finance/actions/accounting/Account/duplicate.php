<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\Account;

[$params, $providers] = eQual::announce([
    'description' => 'Duplicate an account and all its sub-accounts.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'foreign_object' => 'finance\accounting\Account',
            'description'    => 'Account to duplicate.',
            'required'       => true
        ],
        'suffix' => [
            'type'        => 'string',
            'description' => 'Suffix appended to every duplicated account name.',
            'required'    => true
        ]
    ],
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access'      => [
        'visibility' => 'protected'
    ],
    'providers'   => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
$context = $providers['context'];

Account::id($params['id'])
    ->do('duplicate_tree', ['suffix' => $params['suffix']]);

$context->httpResponse()
    ->status(201)
    ->send();
