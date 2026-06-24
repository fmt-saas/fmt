<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\http\HttpRequest;
use equal\orm\Field;
use infra\server\Instance;
use infra\server\Status;

[$params, $providers] = eQual::announce([
    'description'       => "Fetches and saves statuses for a given instance.",
    'help'              => "Calls hosts API to fetch 'instant' statuses and updates instance 'up' field accordingly.",
    'params'            => [
        'id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'infra\server\Instance',
            'description'       => "Identifier of the targeted instance.",
            'required'          => true
        ]
    ],
    'access'            => [
        'visibility'        => 'protected'
    ],
    'response'          => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'constants'         => ['FMT_INSTANCE_TYPE'],
    'providers'         => ['context', 'adapt']
]);

/**
 * @var \equal\php\Context                      $context
 * @var \equal\data\adapt\DataAdapterProvider   $dap
 */
['context' => $context, 'adapt' => $dap] = $providers;

$adapter = $dap->get('json');

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$instance = Instance::id($params['id'])
    ->read(['url', 'access_token'])
    ->first();

if(!$instance) {
    throw new Exception('unknown_instance', EQ_ERROR_INVALID_PARAM);
}

if($instance['id'] === 1) {
    // if current main instance then simply refresh its status
    eQual::run('do', 'infra_server_refresh-main-instance-status');
}
else {
    if(empty($instance['url'])) {
        throw new Exception('missing_api_url', EQ_ERROR_INVALID_PARAM);
    }

    try {
        $request = new HttpRequest('GET '.rtrim($instance['url'], '/').'/?'.http_build_query(['get' => 'infra_server_main-instance-status']));

        $request
            ->header('Content-Type', 'application/json')
            ->header('Authorization', 'Bearer ' . $instance['access_token']);

        /** @var \equal\http\HttpResponse $response */
        $response = $request->send();

        $data = $response->body();

        if($response->getStatusCode() !== 200 || empty($data)) {
            trigger_error("APP::Error while fetching  instance data" . json_encode($data), EQ_REPORT_ERROR);
        }
        else {
            Instance::id($instance['id'])->update([
                'refreshed'                     => $adapter->adaptIn($data['refreshed'], Field::MAP_TYPE_USAGE['datetime']),
                'refreshed_logs'                => $data['refreshed_logs'],
                'branch_equal'                  => $data['branch_equal'],
                'is_branch_equal_ok'            => $data['is_branch_equal_ok'],
                'is_branch_equal_up_to_date'    => $data['is_branch_equal_up_to_date'],
                'branch_fmt'                    => $data['branch_fmt'],
                'is_branch_fmt_ok'              => $data['is_branch_fmt_ok'],
                'is_branch_fmt_up_to_date'      => $data['is_branch_fmt_up_to_date'],
                'is_config_file_ok'             => $data['is_config_file_ok'],
                'is_required_data_ok'           => $data['is_required_data_ok'],
                'is_tasks_ok'                   => $data['is_tasks_ok']
            ]);
        }
    }
    catch(Exception $e) {
        trigger_error("APP::Error while fetching  instance data", EQ_REPORT_ERROR);
    }
}

try {
    $b2_status = eQual::run('get', 'infra_server_Instance_status', ['id' => $instance['id']]);

    Status::create([
        'instance_id'   => $instance['id'],
        'status_data'   => json_encode($b2_status, JSON_PRETTY_PRINT),
        'dsk_use'       => (float) str_replace(['%', ','], ['', '.'], $b2_status['instant']['dsk_use'] ?? 0) / 100,
        'cpu_use'       => (float) str_replace(['%', ','], ['', '.'], $b2_status['instant']['cpu_use'] ?? 0) / 100,
        'ram_use'       => (float) str_replace(['%', ','], ['', '.'], $b2_status['instant']['ram_use'] ?? 0) / 100,
        'total_proc'    => intval($b2_status['instant']['total_proc'] ?? 0)
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
