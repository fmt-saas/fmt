<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\http\HttpRequest;
use fmt\setting\Setting;
use identity\User;
use infra\server\Instance;
use realestate\ownership\Owner;

[$params, $providers] = eQual::announce([
    'description'   => 'Returns descriptor of current User, based on received access_token',
    'access'      => [
        'visibility' => 'public'
    ],
    'constants'     => ['AUTH_TOKEN_HTTPS', 'AUTH_ACCESS_TOKEN_VALIDITY', 'BACKEND_URL', 'FMT_INSTANCE_TYPE'],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'UTF-8',
        'accept-origin'     => '*',
        /*
        // #memo - delay for changing selected_condo might be quite short
        'cacheable'         => true,
        'cache-vary'        => ['user'],
        'expires'           => 60
        */
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

// retrieve current User identifier (HTTP headers lookup through Authentication Manager)
$user_id = $auth->userId();

// check if User is authenticated
if($user_id <= 0) {
    if(constant('FMT_INSTANCE_TYPE') !== 'agency') {
        throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
    }
    else {
        /*
            We're on an agency instance:
            Attempt to retrieve user uuid from the Global instance, based on global_token, if present.
        */

        /** @var \equal\http\HttpRequest  */
        $request = $context->httpRequest();
        $global_jwt = $request->getCookie('global_token');
        if(!$global_jwt) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }

        // #memo - we cannot verify token here (signed with Global private key)
        $token = $auth->decodeToken($global_jwt);

        if(!isset($token['payload']['exp'])) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }

        if($token['payload']['exp'] < time()) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }

        if(!isset($token['payload']['user_uuid']) || strlen($token['payload']['user_uuid']) <= 0) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }

        $global_instance = Instance::search(['instance_type', '=', 'global'])
            ->read(['uuid', 'access_token'])
            ->first();

        if(!$global_instance) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }

        if(empty($global_instance['access_token'])) {
            throw new Exception('missing_access_token', EQ_ERROR_INVALID_CONFIG);
        }

        $user_uuid = $token['payload']['user_uuid'] ?? null;
        $instance_uuid = $global_instance['uuid'] ?? null;

        $request = new HttpRequest('GET ' . rtrim($global_instance['url'], '/') . '/?get=fmt_user_resolve' .
            '&user_uuid=' . $user_uuid .
            '&instance_uuid=' . $instance_uuid .
            '&token=' . $global_jwt
        );

        $request
            ->header('Content-Type', 'application/json')
            ->header('Authorization', 'Bearer ' . $global_instance['access_token']);

        /** @var \equal\http\HttpResponse $response */
        $response = $request->send();
        $data = $response->body();

        if(!is_array($data) || empty($data) || !isset($data['uuid'])) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }

        $user = User::search(['uuid', '=', $data['uuid']])->first();
        if(!$user) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }

        $user_id = $user['id'];
        $auth->su($user_id);
    }
}

// #memo - user has always READ right on its own object
$user = User::id($user_id)
    ->read([
        'id', 'name', 'login', 'validated', 'language',
        'groups_ids' => ['name', 'display_name'],
        'identity_id' => ['firstname', 'lastname', 'owners_ids', 'tenant_id'],
        'instance_id' => ['url'],
        'organisation_id',
        'employee_id',
        'role_assignments_ids' => ['condo_id', 'role_code', 'employee_id', 'is_external'],
        'selected_condo_id' => Setting::get_value('fmt', 'organization', 'user.condo_id', null, ['user_id' => $user_id])
    ])
    ->adapt('json')
    ->first(true);

// #memo `Owner` is a link between an Identity and an Ownership
$ownerships_ids = array_column(
    Owner::search(['identity_id', '=', $user['identity_id']['id']])
        ->read(['ownership_id'])
        ->get(true),
    'ownership_id'
);

$is_employee = false;
$is_owner = false;

$map_roles = [];
$map_condos = [];

if($user['employee_id']) {
    $is_employee = true;
}

foreach($user['role_assignments_ids'] as $role_assignment) {
    if($role_assignment['role_code'] === 'owner') {
        $is_owner = true;
    }
    $map_roles[$role_assignment['role_code']] = true;
    $map_condos[$role_assignment['condo_id']] = true;
}



$result = array_merge($user, [
        'groups'            => array_values(array_map(function ($a) {return $a['name'];}, $user['groups_ids'])),
        'roles'             => array_keys($map_roles),
        'ownerships_ids'    => $ownerships_ids,
        'identity_id'       => $user['identity_id'],
        'organisation_id'   => $user['organisation_id'],
        'employee_id'       => $user['employee_id'],
        'is_owner'          => $is_owner,
        'is_employee'       => $is_employee,
        'condos_ids'        => array_keys($map_condos),
        'selected_condo_id' => Setting::get_value('fmt', 'organization', 'user.condo_id', null, ['user_id' => $user_id])
    ]);

unset($result['groups_ids']);
// unset($result['role_assignments_ids']);

// renew JWT access token
$access_token = $auth->token($user_id, constant('AUTH_ACCESS_TOKEN_VALIDITY'), ['auth_type' => 'pwd', 'auth_level' => 1]);

// send back basic info of the User object
$context->httpResponse()
    ->cookie('access_token',  $access_token, [
        'expires'   => time() + constant('AUTH_ACCESS_TOKEN_VALIDITY'),
        'httponly'  => true,
        'secure'    => constant('AUTH_TOKEN_HTTPS')
    ])
    ->body($result)
    ->send();
