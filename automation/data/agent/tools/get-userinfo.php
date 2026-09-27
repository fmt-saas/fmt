<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use identity\User;
use realestate\ownership\Owner;

[$params, $providers] = eQual::announce([
    'description'   => 'Return the authenticated user identity and all related owner contexts.',
    'params'        => [],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\auth\AuthenticationManager $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$user = User::id($auth->userId())
    ->read([
        'identity_id' => [
            'id',
            'name',
            'firstname',
            'lastname',
            'email',
            'phone',
            'mobile',
            'lang_id' => ['code']
        ]
    ])
    ->adapt('json')
    ->first(true);

if(!$user) {
    throw new Exception('unknown_user', EQ_ERROR_UNKNOWN_OBJECT);
}

$identity = $user['identity_id'] ?? null;
if(!$identity) {
    throw new Exception('missing_user_identity', EQ_ERROR_INVALID_CONFIG);
}

// An owner is the link between an identity and an ownership for a condominium.
$owners = Owner::search([
        ['identity_id', '=', $identity['id']],
        ['condo_id', '<>', null],
        ['ownership_id', '<>', null]
    ])
    ->read([
        'id',
        'condo_id' => [
            'id',
            'name',
            'address',
            'current_fiscal_year_id' => [
                'id',
                'date_from',
                'date_to'
            ]
        ],
        'ownership_id' => [
            'id',
            'name',
            'code',
            'status',
            'date_from',
            'date_to',
            'property_lots_ids' => [
                'id',
                'name',
                'property_lot_nature',
                'statutory_shares'
            ]
        ]
    ])
    ->adapt('json')
    ->get(true);

$owners_payload = [];
foreach($owners as $owner) {
    $condominium = $owner['condo_id'] ?? null;
    $ownership = $owner['ownership_id'] ?? null;
    if(!$condominium || !$ownership) {
        continue;
    }

    $current_fiscal_year = null;
    if($condominium['current_fiscal_year_id'] ?? null) {
        $current_fiscal_year = [
            'id'        => $condominium['current_fiscal_year_id']['id'],
            'date_from' => $condominium['current_fiscal_year_id']['date_from'],
            'date_to'   => $condominium['current_fiscal_year_id']['date_to']
        ];
    }

    $property_lots = [];
    foreach($ownership['property_lots_ids'] ?? [] as $property_lot) {
        $property_lots[] = [
            'id'   => $property_lot['id'],
            'name' => $property_lot['name'] ?? '',
            'type' => $property_lot['property_lot_nature'] ?? ''
        ];
    }

    $owners_payload[] = [
        'condominium' => [
            'id'                  => $condominium['id'],
            'name'                => $condominium['name'] ?? '',
            'address'             => $condominium['address'] ?? '',
            'current_fiscal_year' => $current_fiscal_year
        ],
        'ownership' => [
            'id'            => $ownership['id'],
            'name'          => $ownership['name'] ?? '',
            'code'          => $ownership['code'] ?? '',
            'status'        => $ownership['status'] ?? '',
            'date_from'     => $ownership['date_from'] ?? null,
            'date_to'       => $ownership['date_to'] ?? null,
            'shares_total'  => (float) ($ownership['shares_total'] ?? 0),
            'property_lots' => $property_lots
        ]
    ];
}

$context->httpResponse()
    ->status(200)
    ->body([
        'identity' => [
            'id'        => $identity['id'],
            'name'      => $identity['name'] ?? '',
            'firstname' => $identity['firstname'] ?? '',
            'lastname'  => $identity['lastname'] ?? '',
            'email'     => $identity['email'] ?? '',
            'phone'     => $identity['phone'] ?? '',
            'mobile'    => $identity['mobile'] ?? '',
            'language'  => $identity['lang_id']['code'] ?? ''
        ],
        'owners' => $owners_payload
    ])
    ->send();
