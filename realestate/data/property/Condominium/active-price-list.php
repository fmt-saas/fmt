<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\property\Condominium;
use sale\price\PriceList;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the active price list for the given date.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\Condominium',
            'description'       => "The condominium for which we want the price list.",
            'required'          => true
        ],
        'date' => [
            'type'              => 'date',
            'description'       => "The date for which we want the active price list.",
            'default'           => fn() => time()
        ],
        'allow_pending' => [
            'type'              => 'boolean',
            'description'       => "Allow or not the possibility to return a pending list if no published one is found.",
            'default'           => false
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

$condo = Condominium::id($params['id'])->first();

if(!$condo) {
    throw new Exception("unknown_condo", EQ_ERROR_UNKNOWN_OBJECT);
}

$price_list = PriceList::search([
    ['date_from', '<=', $params['date']],
    ['date_to', '>=', $params['date']],
    ['status', '=', 'published']
])
    ->first();

if($params['allow_pending'] && !$price_list) {
    $price_list = PriceList::search([
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
