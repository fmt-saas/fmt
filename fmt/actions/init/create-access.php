<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use identity\User;


[$params, $providers] = eQual::announce([
    'description'   => 'Create a admin@hostname users with `operators` privileges.',
    'params'        => [
    ],
    'access' => [
        'visibility'        => 'private'
    ],
    'constants'     => ['BACKEND_URL'],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;


$generateRandomPassword = function() {
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $digits = '0123456789';
    $specials = '+#@_~';
    $password_chars = [
        $lowercase[random_int(0, strlen($lowercase) - 1)],
        $uppercase[random_int(0, strlen($uppercase) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $specials[random_int(0, strlen($specials) - 1)],
    ];
    $password_pool = $lowercase.$uppercase.$digits.$specials;

    for($i = count($password_chars); $i < 12; ++$i) {
        $password_chars[] = $password_pool[random_int(0, strlen($password_pool) - 1)];
    }

    for($i = count($password_chars) - 1; $i > 0; --$i) {
        $j = random_int(0, $i);
        [$password_chars[$i], $password_chars[$j]] = [$password_chars[$j], $password_chars[$i]];
    }

    return implode('', $password_chars);
};

$host = parse_url(constant('BACKEND_URL'), PHP_URL_HOST) ? : 'fmtsolutions.be';
$host = preg_replace('/^www\./', '', $host);

$username = 'admin@' . $host;

$user = User::search(['login', '=', $username])
    ->read(['id', 'identity_id'])
    ->first(true);

if($user) {
    throw new Exception('user_already_exists', EQ_ERROR_CONFLICT_OBJECT);
}

User::create([
        'login'         => $username,
        'password'      => $password,
        'language'      => 'fr',
        'validated'     => true,
        'allow_auth'    => true,
        'groups_ids'    => [3]
    ])
    ->first();

$result = [
    'username' => $username,
    'password' => $password
];

$context
    ->httpResponse()
    ->body($result)
    ->send();
