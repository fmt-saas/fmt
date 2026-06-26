<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => 'Fetches instant status of the main instance and initializes checks when missing.',
    'params'        => [
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$fields = [
    'refreshed',
    'refreshed_logs',
    'branch_equal',
    'branch_fmt',
    'checks_ids' => [
        'name',
        'description',
        'value'
    ]
];

$main_instance = Instance::id(1)
    ->read($fields)
    ->adapt('json')
    ->first(true);

if(!$main_instance) {
    throw new Exception('main_instance_missing', EQ_ERROR_INVALID_CONFIG);
}

if(empty($main_instance['checks_ids'])) {
    eQual::run('do', 'infra_server_refresh-self-status');

    $main_instance = Instance::id(1)
        ->read($fields)
        ->adapt('json')
        ->first(true);

    if(!$main_instance) {
        throw new Exception('main_instance_missing', EQ_ERROR_INVALID_CONFIG);
    }
}

$context
    ->httpResponse()
    ->body($main_instance)
    ->send();
