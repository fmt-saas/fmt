<?php

use infra\server\Instance;
use infra\server\Server;

// constants
$backend_url = \eQual::constant('BACKEND_URL');
$instance_type = \eQual::constant('FMT_INSTANCE_TYPE');

// create server
$server = Server::create([
    'name'  => '127.0.0.1',
])
    ->first();

// create instance
Instance::create([
    'name'          => parse_url($backend_url, PHP_URL_HOST),
    'instance_type' => $instance_type,
    'url'           => $backend_url,
    'server_id'     => $server['id'],
]);
