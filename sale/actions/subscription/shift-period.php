<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use sale\subscription\Subscription;

list($params, $providers) = eQual::announce([
    'description' => 'Shift subscription dates depending on duration.',
    'params'      => [
        'id' =>  [
            'description' => 'ID of the subscription.',
            'type'        => 'integer',
            'required'    => true
        ]
    ],
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
$context = $providers['context'];

$subscription = Subscription::id($params['id'])
    ->read([
        'id',
        'date_from',
        'date_to',
        'duration'
    ])
    ->first();

if(!$subscription) {
    throw new Exception('unknown_subscription', EQ_ERROR_UNKNOWN_OBJECT);
}

Subscription::id($subscription['id'])
    ->update([
        'date_from' => $subscription['date_to'],
        'date_to'   => strtotime(Subscription::MAP_DURATION[$subscription['duration']], $subscription['date_to'])
    ]);

$context->httpResponse()
        ->status(204)
        ->send();
