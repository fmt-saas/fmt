<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use identity\User;

list($params, $providers) = eQual::announce([
    'description'   => 'Returns descriptor of current User, based on received access_token',
    'constants'     => ['AUTH_ACCESS_TOKEN_VALIDITY', 'BACKEND_URL'],
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
    /** @var \equal\http\HttpRequest  */
    $request = $context->httpRequest();
    $global_token = $request->getCookie('global_token');
    if(!$global_token) {
        throw new Exception('user_unknown', EQ_ERROR_INVALID_USER);
    }
    $jwt = $auth->decodeToken($global_token);
    print_r($jwt);
}

// user has always READ right on its own object
$user = User::id($user_id)
    ->read([
        'identity_id' => ['firstname', 'lastname'],
        'organisation_id',
        'id', 'name', 'login', 'validated', 'language',
        'groups_ids' => ['name', 'display_name']
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
