<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use realestate\property\PropertyLot;
use realestate\property\PropertyLotNature;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the quantity of parkings for metering use.",
    'params'        => [],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$parking_prop_nature = PropertyLotNature::search(['code', '=', 'PARKING'])
    ->read(['id'])
    ->first();

$parkings_qty = PropertyLot::search(['nature_id', '=', $parking_prop_nature['id']])->count();

$result = [
    'value'     => $parkings_qty,
    'unit'      => 'count',
    'logs'      => [],
    'errors'    => [],
    'warnings'  => []
];

$context
    ->httpResponse()
    ->body($result)
    ->send();
