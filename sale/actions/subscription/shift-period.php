<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
