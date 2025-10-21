<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2024
    Original author(s): Cédric FRANCOYS
    Licensed under GNU LGPL 3 license <http://www.gnu.org/licenses/>
*/
use identity\User;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Attempts to log a user in.",
    'params' 		=>	[
        'login'		=>	[
            'description'   => "user name",
            'type'          => 'string',
            'required'      => true
        ],
        'password' =>  [
            'description'   => "user password",
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'access'      => [
        'visibility' => 'public'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm'],
    'constants'     => ['BACKEND_URL', 'AUTH_ACCESS_TOKEN_VALIDITY', 'AUTH_TOKEN_HTTPS', 'FMT_INSTANCE_TYPE']
]);

/**
 * @var equal\php\Context                   $context
 * @var equal\orm\ObjectManager             $om
 * @var equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;

// we might have received either a login (email) or a username
if(strpos($params['login'], '@') > 0) {
    // cleanup provided email (as login): strip heading and trailing spaces and remove recipient tag, if any
    list($username, $domain) = explode('@', strtolower(trim($params['login'])));
    $username .= '+';
    $login = substr($username, 0, strpos($username, '+')).'@'.$domain;
}
else {
    // find a user that matches the given username (there should be only one)
    $user = User::search(['username', '=', $params['login']])->read(['login'])->first();
    if(!$user) {
        throw new Exception("user_not_found", EQ_ERROR_INVALID_USER);
    }
    $login = $user['login'];
}

// #memo - email might still be invalid (a validation check is made in User class)
$auth->authenticate($login, $params['password']);

$user_id = $auth->userId();

if(!$user_id) {
    // this is a fallback exception, but we should never reach this code, since user has been found by authenticate method
    throw new Exception("user_not_found", EQ_ERROR_INVALID_USER);
}

$user = User::id($user_id)
    ->read([
        'id',
        'validated',
        'uuid',
        'identity_id',
        'instance_id' => ['url']
    ])
    ->first(true);

if(!$user || !$user['validated']) {
    throw new Exception("user_not_validated", EQ_ERROR_NOT_ALLOWED);
}

// generate a JWT access token for current host
$access_token = $auth->token(
        // user identifier
        $user_id,
        // validity of the token
        constant('AUTH_ACCESS_TOKEN_VALIDITY'),
        // authentication method to register to AMR
        [
            'auth_type'  => 'pwd',
            'auth_level' => 1
        ]
    );

$httpResponse = $context->httpResponse();

$httpResponse->cookie('access_token',  $access_token, [
        'expires'   => time() + constant('AUTH_ACCESS_TOKEN_VALIDITY'),
        'httponly'  => true,
        'secure'    => constant('AUTH_TOKEN_HTTPS')
    ]);



/*
Normal authentication flow

If the current instance is the Global:
- Create a 'global_token' cookie for the main domain (e.g. '.fmt.yb.run').
- The cookie value is a JWT token of the following form:

{
  "iss": "https://global.fmt.yb.run",   // issuer: global instance
  "sub": "user.uuid",                   // subject: user UUID
  "user_uuid": "",                      // duplicated for convenience
  "exp": 1739990000                     // expiration timestamp (UNIX)
}

This global cookie will also be received by all instance subdomains.
When an instance receives a login request without its own 'access_token'
cookie, it can send the 'global_token' (along with the current instance name)
to the central server.

If the central server validates the token successfully, it returns the
corresponding user data stored at the global level:

{
  "id": 456,
  "validated": true,
  "instance": "host1"
}

*/


// if on Global instance, create the global_token to be shared amongst all instances
if(constant('FMT_INSTANCE_TYPE') === 'global') {

    $global_token = $auth->createAccessToken([
            'iss'       => rtrim(constant('BACKEND_URL'), '/'),
            'sub'       => "user.uuid",
            'user_uuid' => $user['uuid'],
            'exp'       => time() + constant('AUTH_ACCESS_TOKEN_VALIDITY'),
        ]);

    $baseDomain = parse_url(constant('BACKEND_URL'), PHP_URL_HOST);

    $domainParts = explode('.', $baseDomain);
    if(count($domainParts) > 2) {
        array_shift($domainParts);
        $baseDomain = implode('.', $domainParts);
    }

    $httpResponse->cookie('global_token',  $global_token, [
            'expires'   => time() + constant('AUTH_ACCESS_TOKEN_VALIDITY'),
            'domain'    => $baseDomain,
            'httponly'  => true,
            'secure'    => constant('AUTH_TOKEN_HTTPS')
        ]);

}


$httpResponse
        ->status(204)
        ->send();
