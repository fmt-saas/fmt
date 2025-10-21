packages/fmt/actions/sync/push-from-local.php<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use identity\User;
use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => 'Request a resolution of a User on the  GLOBAL instance, based on user UUID, for a given instance.',
    'params'        => [
        'user_uuid' => [
            'type'              => 'string',
            'description'       => 'UUID of the user to be resolved.',
            'required'          => true
        ],
        'instance_uuid' => [
            'type'              => 'integer',
            'description'       => 'Instance for which the User is to be resolved.',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $orm] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$user = User::search(['uuid', '=', $params['user_uuid']])
    ->read(['identity_id'])
    ->first();

if(!$user) {
    throw new Exception('unknown_user_uuid', EQ_ERROR_UNKNOWN_OBJECT);
}

$instance = Instance::search(['uuid', '=', $params['instance_uuid']])->first();

if(!$instance) {
    throw new Exception('unknown_instance_uuid', EQ_ERROR_UNKNOWN_OBJECT);
}

$user = User::search([
        ['identity_id', '=', $user['identity_id']],
        ['instance_id', '=', $instance['id']]
    ])
    ->read(['uuid'])
    ->adapt('json')
    ->first(true);

if(!$user) {
    throw new Exception('user_resolution_failed', EQ_ERROR_UNKNOWN_OBJECT);
}

$context
    ->httpResponse()
    ->status(200)
    ->body($user)
    ->send();
