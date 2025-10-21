<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\http\HttpRequest;
use identity\User;
use infra\server\Instance;

[$params, $providers] = eQual::announce([
    'description'   => 'Returns descriptor of current User, based on received access_token',
    'access'      => [
        'visibility' => 'public'
    ],
    'constants'     => ['AUTH_ACCESS_TOKEN_VALIDITY', 'BACKEND_URL', 'FMT_INSTANCE_TYPE', 'FMT_API_URL_GLOBAL', 'FMT_API_INTERNAL_TOKEN'],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'UTF-8',
        'accept-origin'     => '*'
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
        /** @var \equal\http\HttpRequest  */
        $request = $context->httpRequest();
        $global_token = $request->getCookie('global_token');
        if(!$global_token) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }
        $jwt = $auth->decodeToken($global_token);

        if(!isset($jwt['payload']['user_uuid']) || strlen($jwt['payload']['user_uuid']) <= 0) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }

        // #memo - on local instances there is a single Managing Agent object
        $instance = Instance::search()->read(['uuid'])->first();

        if(!$instance) {
            throw new Exception('protected_operation', EQ_ERROR_NOT_ALLOWED);
        }

        $user_uuid = $jwt['payload']['user_uuid'];
        $instance_uuid = $instance['uuid'];

        $request = new HttpRequest('GET ' . rtrim(constant('FMT_API_URL_GLOBAL'), '/') . '/?get=fmt_user_resolve' .
            '&user_uuid=' . $user_uuid .
            '&instance_uuid=' . $instance_uuid);

        $request
            ->header('Content-Type', 'application/json')
            ->header('Authorization', 'Bearer ' . constant('FMT_API_INTERNAL_TOKEN'));

        /** @var HttpResponse */
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
    }

}

// #memo - user has always READ right on its own object
$user = User::id($user_id)
    ->read([
        'id', 'name', 'login', 'validated', 'language',
        'groups_ids' => ['name', 'display_name'],
        'identity_id' => ['firstname', 'lastname'],
        'organisation_id'
    ])
    ->adapt('json')
    ->first(true);

$result = array_merge($user, [
        'groups'            => array_values(array_map(function ($a) {return $a['name'];}, $user['groups_ids'])),
        'identity_id'       => $user['identity_id'],
        'organisation_id'   => $user['organisation_id']
    ]);

// renew JWT access token
$access_token = $auth->renewedToken(constant('AUTH_ACCESS_TOKEN_VALIDITY'));

// send back basic info of the User object
$context->httpResponse()
    ->cookie('access_token',  $access_token, [
        'expires'   => time() + constant('AUTH_ACCESS_TOKEN_VALIDITY'),
        'httponly'  => true,
        'secure'    => constant('AUTH_TOKEN_HTTPS')
    ])
    ->body($result)
    ->send();
