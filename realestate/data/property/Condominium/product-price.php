<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\property\Condominium;
use sale\catalog\Product;
use sale\price\Price;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the active price list for the given date.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\Condominium',
            'description'       => "The condominium for which we want a product price.",
            'required'          => true
        ],
        'date' => [
            'type'              => 'date',
            'description'       => "The date for which we want the product price.",
            'default'           => fn() => time()
        ],
        'product_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\catalog\Product',
            'required'          => true
        ],
        'allow_pending' => [
            'type'              => 'boolean',
            'description'       => "Allow or not the possibility to use a pending list if no published one is found.",
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

$product = Product::id($params['product_id'])->first();

if(!$product) {
    throw new Exception("unknown_product", EQ_ERROR_UNKNOWN_OBJECT);
}

$price_list_id = eQual::run('get', 'realestate_property_Condominium_active-price-list', [
    'id'            => $condo['id'],
    'date'          => $params['date'],
    'allow_pending' => $params['allow_pending']
]);

if(!$price_list_id) {
    throw new Exception("price_list_not_found", EQ_ERROR_UNKNOWN_OBJECT);
}

$price = Price::search([
    ['price_list_id', '=', $price_list_id],
    ['product_id', '=', $product['id']]
])
    ->first();

$result = $price['id'] ?? null;

$context
    ->httpResponse()
    ->body($result)
    ->send();
