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
        'condo_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\Condominium',
            'description'    => 'Condominium of the account to duplicate.',
            'required'       => true,
            'visible'        => false
        ],
        'apportionment_id' => [
            'type'           => 'many2one',
            'label'          => 'Apportionment',
            'foreign_object' => 'realestate\property\Apportionment',
            'description'    => 'Apportionment assigned to every duplicated account.',
            'domain'         => [
                ['condo_id', '=', 'object.condo_id'],
                ['is_statutory', '=', false]
            ],
            'required'       => true
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
    ->do('duplicate_tree', [
        'apportionment_id' => $params['apportionment_id']
    ]);

$context->httpResponse()
    ->status(201)
    ->send();
