
<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use identity\User;
use infra\server\Instance;

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

/** @var \equal\http\HttpRequest  */
$request = $context->httpRequest();
$global_jwt = $request->getCookie('global_token');

if(!$global_jwt) {
    throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
}

$check = $auth->verifyToken($global_jwt, constant('AUTH_SECRET_KEY'));

if($check === false || $check <= 0) {
    throw new Exception('invalid_token', EQ_ERROR_NOT_ALLOWED);
}

$token = $auth->decodeToken($global_jwt);

if(!isset($token['payload']['user_uuid']) || strlen($token['payload']['user_uuid']) <= 0) {
    throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
}

$user = User::search(['uuid', '=', $token['payload']['user_uuid']])
    ->read(['identity_id'])
    ->first();

if(!$user) {
    throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
}

// search for all User accounts in order to retrieve instances
$users = User::search([
        ['identity_id', '=', $user['identity_id']]
    ])
    ->read(['instance_id']);

$instances_ids = [];

foreach($users as $user_id => $user) {
    $instances_ids[] = $user['instance_id'];
}

$result = Instance::ids($instances_ids)
    ->read(['id', 'uuid', 'name', 'url', 'managing_agent_id' => ['id', 'name']])
    ->get(true);

$context
    ->httpResponse()
    ->body($result)
    ->send();
