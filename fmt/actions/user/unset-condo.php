<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\setting\Setting;
use identity\User;
use infra\server\Instance;
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => 'Limit all requests of current user to a single condominium.',
    'params'        => [
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

$user_id = $auth->userId();

Setting::set_value('fmt', 'organization', 'user.condo_id', null, ['user_id' => $user_id]);

$context
    ->httpResponse()
    ->status(204)
    ->send();
