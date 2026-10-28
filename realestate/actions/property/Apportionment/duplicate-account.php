<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\property\Apportionment;

[$params, $providers] = eQual::announce([
    'description' => 'Duplicate an account hierarchy using a selected apportionment.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\Apportionment',
            'description'    => 'Apportionment assigned to every duplicated account.',
            'required'       => true,
            'visible'        => false
        ],
        'condo_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\Condominium',
            'description'    => 'Condominium of the account to duplicate.',
            'required'       => true,
            'visible'        => false
        ],
        'account_id' => [
            'type'           => 'many2one',
            'label'          => 'Account',
            'foreign_object' => 'finance\accounting\Account',
            'description'    => 'Account hierarchy to duplicate.',
            'domain'         => [
                ['condo_id', '=', 'object.condo_id'],
                ['level', '=', 6],
                ['is_control_account', '=', true],
                ['operation_assignment', '=', ''],
                ['code', 'like', '%0']
            ],
            'required'       => true
        ],
        'suffix' => [
            'type'        => 'string',
            'label'       => 'Suffix',
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
    'providers'   => ['context', 'self']
]);

/**
 * @var \equal\php\Context $context
 */
$context = $providers['context'];

Apportionment::id($params['id'])
    ->do('duplicate_account_tree', [
        'account_id' => $params['account_id'],
        'suffix'     => $params['suffix']
    ]);

$context->httpResponse()
    ->status(201)
    ->send();
