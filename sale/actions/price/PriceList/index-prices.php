<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use sale\price\Price;
use sale\price\PriceList;

[$params, $providers] = eQual::announce([
    'description'   => "Index the prices of a given price list.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\price\PriceList',
            'description'       => "The PriceList that needs its prices to be indexed.",
            'required'          => true
        ],
        'indexation_rate' => [
            'type'              => 'float',
            'description'       => "The rate of indexation to increase the prices of the list.",
            'help'              => "If given indexation rate is 2.5, then a price of 10.00 € becomes: 10.00 × 1.025 = 10,25 €.",
            'default'           => 0.0
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

/*
    Fetch data and check that given params are valid
*/

$price_list = PriceList::id($params['id'])
    ->read([
        'status',
        'date_from',
        'prices_ids' => [
            'price',
            'accounting_rule_id',
            'product_id'
        ]
    ])
    ->first();

if(!$price_list) {
    throw new Exception("unknown_price_list", EQ_ERROR_UNKNOWN_OBJECT);
}

if($price_list['status'] !== 'pending') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

if($params['indexation_rate'] < -100) {
    throw new Exception("invalid_indexation_rate", EQ_ERROR_INVALID_PARAM);
}

if($params['indexation_rate'] === 0.0) {
    throw new Exception("invalid_indexation_rate", EQ_ERROR_INVALID_PARAM);
}


/*
    Index prices
*/

foreach($price_list['prices_ids'] as $id => $price) {
    $indexation_multiplier = 1 + ($params['indexation_rate'] / 100);
    $new_price = round($price['price'] * $indexation_multiplier, 2);

    Price::id($id)->update(['price' => $new_price]);
}


$context
    ->httpResponse()
    ->send();
