<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\http\HttpRequest;
use identity\User;

// announce script and fetch parameters values
[$params, $providers] = eQual::announce([
    'description'	=>	"Callback for receiving confirmation from Google OAuth.",
    'params' 		=>	[
        'code' => [
            'type'          => 'string',
            'usage'         => 'text/plain:3000',
            //'required'      => true
        ],
        'access_token' => [
            'type'          => 'string',
            'usage'         => 'text/plain:3000',
            //'required'      => true
        ],
        'expires_in' => [
            'type'          => 'integer',
            //'required'      => true
        ],
        'refresh_token' => [
            'type'          => 'string',
            'usage'         => 'text/plain:3000',
            //'required'      => true
        ],
        'scope' => [
            'type'          => 'string',
            //'required'      => true
        ],
        'token_type' => [
            'type'          => 'string',
            //'required'      => true
        ],
        'id_token' => [
            'type'          => 'string',
            'usage'         => 'text/plain.small',
            //'required'      => true
        ]
    ],
    'constants'     => ['BACKEND_URL'],
    'access'        => [
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

if($params['code']) {
 // constant('BACKEND_URL')
    $oauthRequest = new HttpRequest('https://oauth2.googleapis.com/token', ['Host' => 'graph.facebook.com:443']);
    $response = $oauthRequest
                ->header('Content-Type', 'application/x-www-form-urlencoded')
                ->setBody([
                    'grant_type' => 'authorization_code',
                    'code' => $params['code'],
                    'redirect_uri' => 'https://'.constant('BACKEND_URL').'/oauth/gmail',
                    'client_id' => '24230475119-6fabc7k3lh9v9u3aa01im86d48bsudp0.apps.googleusercontent.com',
                    'client_secret' => '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'
                ])
                ->send();

    $data = $response->body();
    $status = $response->getStatusCode();
    ob_start();
    var_dump($data);
    $out = ob_get_clean();

    file_put_contents(EQ_BASEDIR.'/log/test.gmail1.txt', $out);
}

$httpResponse = $context->httpResponse();


ob_start();
print_r($params);
$out = ob_get_clean();

file_put_contents(EQ_BASEDIR.'/log/test.gmail.txt', $out);

$httpResponse
        ->status(204)
        ->send();
