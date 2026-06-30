<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\User;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the quantity users for metering use.",
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

$users_qty = User::search([
    ['is_system', '=', false],
    ['validated', '=', true]
])
    ->count();

$result = [
    'value'     => $users_qty,
    'unit'      => 'count',
    'logs'      => [],
    'errors'    => [],
    'warnings'  => []
];

$context
    ->httpResponse()
    ->body($result)
    ->send();
