<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\property\Condominium;
use sale\price\Price;
use sale\price\PriceList;

[$params, $providers] = eQual::announce([
    'description'   => "Clone a given price list for the given coming year.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\price\PriceList',
            'description'       => "The PriceList that needs to be cloned for the coming year.",
            'required'          => true
        ],
        'condo_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\price\PriceList',
            'description'       => "The condominium for which the price list is needed.",
            'visible'           => false
        ],
        'target_year' => [
            'type'              => 'int',
            'description'       => "The year to assign to the new price list.",
            'default'           => intval(date('Y')) + 1,
            'required'          => true
        ],
        'indexation_rate' => [
            'type'              => 'float',
            'description'       => "The rate of indexation to increase the prices of the list.",
            'help'              => "If given indexation rate is 2.5, then a price of 10.00 € becomes: 10.00 × 1.025 = 10,25 €.",
            'default'           => 0.0,
            'required'          => true
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
        'date_from',
        'condo_id' => [
            'name'
        ],
        'prices_ids' => [
            'price',
            'accounting_rule_id',
            'product_id'
        ]
    ])
    ->first();

if(!$price_list) {
    throw new Exception("unknown_price_list", EQ_ERROR_UNKNOWN);
}

$price_list_year = intval(date('Y', $price_list['date_from']));
if($params['target_year'] < $price_list_year) {
    throw new Exception("invalid_target_year", EQ_ERROR_INVALID_PARAM);
}

$condo = null;
if(isset($params['condo_id'])) {
    $condo = Condominium::id($params['condo_id'])
        ->read(['name'])
        ->first();
}
else {
    $condo = $price_list['condo_id'];
}

if(!$condo) {
    throw new Exception("unknown_condominium", EQ_ERROR_UNKNOWN);
}


/*
    Check that the wanted price list does not already exist
*/

$new_date_from = mktime(0, 0, 0, 1, 1, $params['target_year']);
$new_date_to = mktime(23, 59, 59, 12, 31, $params['target_year']);

$already_existing_price_list = PriceList::search([
    ['condo_id', '=', $condo['id']],
    ['date_from', '=', $new_date_from],
    ['date_to', '=', $new_date_to]
])
    ->first();

if($already_existing_price_list) {
    throw new Exception("already_existing_price_list", EQ_ERROR_CONFLICT_OBJECT);
}


/*
    Create price list and prices
*/

$new_price_list = PriceList::create([
    'condo_id'      => $condo['id'],
    'name'          => "Default {$params['target_year']}",
    'description'   => "Default {$params['target_year']} price list for condominium {$condo['name']}.",
    'date_from'     => $new_date_from,
    'date_to'       => $new_date_to
])
    ->first();

foreach($price_list['prices_ids'] as $price) {
    $indexation_multiplier = 1 + ($params['indexation_rate'] / 100);
    $new_price = round($price['price'] * $indexation_multiplier, 2);

    Price::create([
        'price_list_id'         => $new_price_list['id'],
        'price'                 => $new_price,
        'accounting_rule_id'    => $price['accounting_rule_id'],
        'product_id'            => $price['product_id']
    ]);
}


$context
    ->httpResponse()
    ->send();
