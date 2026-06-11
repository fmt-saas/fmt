<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\server\Instance;
use infra\server\Status;

[$params, $providers] = eQual::announce([
    'description'       => "Fetches and saves statuses for a given instance.",
    'help'              => "Calls hosts API to fetch 'instant' statuses and updates instance 'up' field accordingly.",
    'params'            => [
        'id' =>  [
            'description'       => 'Identifier of the targeted instance',
            'type'              => 'many2one',
            'foreign_object'    => 'infra\server\Instance',
            'required'          => true
        ]
    ],
    'access'            => [
        'visibility'        => 'private'
    ],
    'response'          => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'constants'         => ['FMT_INSTANCE_TYPE'],
    'providers'         => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$instance = Instance::id($params['id'])
    ->read(['id'])
    ->first();

if(!$instance) {
    throw new Exception('unknown_instance', EQ_ERROR_INVALID_PARAM);
}

try {
    $status = eQual::run('get', 'infra_server_Instance_status', ['id' => $instance['id']]);

    Status::create([
        'instance_id'   => $instance['id'],
        'status_data'   => json_encode($status, JSON_PRETTY_PRINT),
        'dsk_use'       => (float) str_replace(['%', ','], ['', '.'], $status['instant']['dsk_use'] ?? 0) / 100,
        'cpu_use'       => (float) str_replace(['%', ','], ['', '.'], $status['instant']['cpu_use'] ?? 0) / 100,
        'ram_use'       => (float) str_replace(['%', ','], ['', '.'], $status['instant']['ram_use'] ?? 0) / 100,
        'total_proc'    => intval($status['instant']['total_proc'] ?? 0)
    ]);

    // instance is up
    Instance::id($instance['id'])->update(['up' => true, 'synced' => time()]);
}
catch(Exception $e) {
    // instance is down
    Instance::id($instance['id'])->update(['up' => false, 'synced' => time()]);
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
