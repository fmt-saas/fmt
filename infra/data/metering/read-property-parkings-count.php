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

$property_lot_natures_ids = PropertyLotNature::search(['code', 'in', ['garage', 'parking']])
    ->ids();

$parkings_qty = PropertyLot::search(['nature_id', 'in', $property_lot_natures_ids])->count();

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
