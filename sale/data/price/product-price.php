<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use sale\catalog\Product;
use sale\price\Price;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the product price for the given condo and date.",
    'params'        => [
        'condo_id' => [
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
            'description'       => "The product for which the price is needed.",
            'required'          => true
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

$product = Product::id($params['product_id'])
    ->read(['sku'])
    ->first();

if(!$product) {
    throw new Exception("unknown_product", EQ_ERROR_UNKNOWN_OBJECT);
}

$price_list_id = eQual::run('get', 'sale_price_active-price-list', [
    'condo_id'      => $params['condo_id'],
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

if(!$price && $params['allow_global']) {
    trigger_error("APP::unable to find the price of product {$product['sku']} for condominium list $price_list_id.", EQ_REPORT_WARNING);

    $price_list_id = eQual::run('get', 'sale_price_active-price-list', [
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
}

$result = $price['id'] ?? null;

$context
    ->httpResponse()
    ->body($result)
    ->send();
