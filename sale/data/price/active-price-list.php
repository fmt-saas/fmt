<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use sale\price\PriceList;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the active price list for the given date.",
    'params'        => [
        'condo_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\Condominium',
            'description'       => "The condominium for which we want the price list."
        ],
        'date' => [
            'type'              => 'date',
            'description'       => "The date for which we want the active price list.",
            'default'           => fn() => time()
        ],
        'allow_pending' => [
            'type'              => 'boolean',
            'description'       => "Allow fallback on pending price list if published not found.",
            'default'           => true
        ],
        'allow_global' => [
            'type'              => 'boolean',
            'description'       => "Allow fallback on global price list if condo specific list not found.",
            'default'           => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$base_domain = [
    ['date_from', '<=', $params['date']],
    ['date_to', '>=', $params['date']]
];
if(isset($params['condo_id'])) {
    $base_domain[] = ['condo_id', '=', $params['condo_id']];
}

$price_list = PriceList::search([
    ['condo_id', '=', $params['condo_id']],
    ['date_from', '<=', $params['date']],
    ['date_to', '>=', $params['date']],
    ['status', '=', 'published']
])
    ->first();

if(!$price_list && $params['allow_pending']) {
    $price_list = PriceList::search([
        ['condo_id', '=', $params['condo_id']],
        ['date_from', '<=', $params['date']],
        ['date_to', '>=', $params['date']],
        ['status', '=', 'pending']
    ])
        ->first();
}

if(!$price_list && $params['allow_global']) {
    $price_list = PriceList::search([
        ['condo_id', '=', null],
        ['date_from', '<=', $params['date']],
        ['date_to', '>=', $params['date']],
        ['status', '=', 'published']
    ])
        ->first();
}

if(!$price_list && $params['allow_global'] && $params['allow_pending']) {
    $price_list = PriceList::search([
        ['condo_id', '=', null],
        ['date_from', '<=', $params['date']],
        ['date_to', '>=', $params['date']],
        ['status', '=', 'pending']
    ])
        ->first();
}

$result = $price_list['id'] ?? null;

$context
    ->httpResponse()
    ->body($result)
    ->send();
