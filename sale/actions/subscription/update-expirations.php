<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use sale\subscription\Subscription;

list($params, $providers) = eQual::announce([
    'description' => 'Update subscriptions expiration columns.',
    'params'      => [
        'ids' => [
            'description'       => 'List of Subscription identifiers the check against emptyness.',
            'type'              => 'array'
        ]
    ],
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$should_be_expired_ids  = [];
$should_be_upcoming_expiry_ids = [];

if($params['ids']){
    $should_be_expired_ids = Subscription::search([
            ['id', 'in', $params['ids']],
            ['date_to', '<', date('Y-m-d', time())],
            ['is_expired', '=', false]
        ])
        ->ids();

    $should_be_upcoming_expiry_ids = Subscription::search([
            ['id', 'in', $params['ids']],
            ['date_to', '<', date('Y-m-d', strtotime('+30 days'))],
            ['has_upcoming_expiry', '=', false]
        ])
        ->ids();
}


if(!empty($should_be_expired_ids)) {
    Subscription::ids($should_be_expired_ids)
        ->update(['is_expired' => null]);
}

if(!empty($should_be_upcoming_expiry_ids)) {
    Subscription::ids($should_be_upcoming_expiry_ids)
        ->update(['has_upcoming_expiry' => null]);
}

$context->httpResponse()
        ->status(204)
        ->send();
