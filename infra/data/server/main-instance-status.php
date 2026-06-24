<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => 'Fetches instant status of a given instance.',
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

$main_instance = Instance::id(1)
    ->read([
        'branch_equal',
        'is_branch_equal_ok',
        'is_branch_equal_up_to_date',
        'branch_fmt',
        'is_branch_fmt_ok',
        'is_branch_fmt_up_to_date',
        'is_config_file_ok',
        'is_required_data_ok',
        'is_tasks_ok'
    ])
    ->adapt('json')
    ->first(true);

if(!$main_instance) {
    throw new Exception('main_instance_missing', EQ_ERROR_INVALID_CONFIG);
}

$context
    ->httpResponse()
    ->body($main_instance)
    ->send();
