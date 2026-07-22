<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use realestate\property\PropertyLot;
use realestate\property\PropertyLotNature;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the quantity of property lots for metering use.",
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


$property_lot_natures_ids = PropertyLotNature::search(['hierarchy', '=', 1])
    ->ids();

$main_lots_qty = PropertyLot::search(['nature_id', 'in', $property_lot_natures_ids])->count();

$result = [
    'value'     => $main_lots_qty,
    'unit'      => 'count',
    'logs'      => [],
    'errors'    => [],
    'warnings'  => []
];

$context
    ->httpResponse()
    ->body($result)
    ->send();
