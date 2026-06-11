<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\server\Server;
use infra\server\Status;

[$params, $providers] = eQual::announce([
    'description'       => "Fetches and saves statuses for all servers.",
    'help'              => "Calls hosts API to fetch 'instant' statuses and updates servers 'up' field accordingly.",
    'params'            => [
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

$servers = Server::search()
    ->read(['b2_api_url', 'b2_api_password', 'instances_ids'])
    ->get();

foreach($servers as $id => $server) {
    if(!empty($server['b2_api_url']) && !empty($server['b2_api_password'])) {
        eQual::run('do', 'infra_server_Server_sync-status', [
            'id'                => $id,
            'sync_instances'    => true
        ]);
    }
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
