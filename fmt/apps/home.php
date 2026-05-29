<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use identity\User;

[$params, $providers] = eQual::announce([
    'description'   => 'Redirect authenticated user to default application.',
    'params'        => [],
    'access' => [
        'visibility'        => 'public'
    ],
    'providers'     => ['auth', 'context']
]);

/**
 * @var \equal\auth\AuthenticationManager $auth
 * @var \equal\php\Context $context
 */
['auth' => $auth, 'context' => $context] = $providers;

$location = '/app/#/';

$user_id = $auth->userId();
$user = User::id($user_id)->read(['is_owner'])->first();

if($user) {
    if($user['is_owner']) {
        $location = '/portal/#/';
    }
    /*
    elseif($user->hasGroup('managers')) {
        $location = '/app/#/';
    }
    elseif($user->hasGroup('accountants')) {
        $location = '/accounting/#/';
    }
    */
}
else {
    $location = '/auth/?redirect_to=' . urlencode('/?show=fmt_home');
}

// Exact API may vary depending on how eQual exposes the HTTP response.
$context->httpResponse()
    ->header('Location', $location)
    ->status(302)
    ->send();