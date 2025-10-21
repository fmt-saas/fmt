<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use identity\User;
use infra\server\Instance;
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => 'Retrieve the list of current user, using the global token (intended for Global instance).',
    'params'        => [
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'AUTH_SECRET_KEY'],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}


// 1) fetch the instances of the user (based on global_token)
$instances = eQual::run('get', 'fmt_user_instances');


$instances_ids = [];

foreach($instances as $instance) {
    $instances_ids[] = $instance['id'];
}


$result = Condominium::search(['instance_id', 'in', $instances_ids])
    ->read(['id', 'name', 'instance_id' => ['id', 'name', 'url']])
    ->adapt('json')
    ->get(true);


$context
    ->httpResponse()
    ->body($result)
    ->send();
