<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\subscription\Subscription;

list($params, $providers) = eQual::announce([
    'description' => 'Update expiration and verify subscription expiration.',
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
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

$subscription = Subscription::id($params['id'])
    ->read([
        'id',
        'is_expired',
        'has_upcoming_expiry',
    ])
    ->first(true);

$httpResponse = $context->httpResponse()->status(200);

if($subscription['is_expired'] || $subscription['has_upcoming_expiry']) {
    $result = $subscription['id'];
    $dispatch->dispatch('sale.subscription.check.expiration', 'sale\subscription\Subscription', $subscription['id'], 'important', 'sale_subscription_check-expiration', ['id' => $params['id']], [], null, null);
    $httpResponse->status(qn_error_http(QN_ERROR_NOT_ALLOWED));
}
else {
    $dispatch->cancel('sale.subscription.check.expiration', 'sale\subscription\Subscription', $subscription['id']);
}

$httpResponse->body($result)
             ->send();

