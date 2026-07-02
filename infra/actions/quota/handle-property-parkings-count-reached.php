<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\quota\QuotaUsage;

[$params, $providers] = eQual::announce([
    'description'   => "Handles quota reached for 'property.parkings.count'.",
    'params'        => [
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$quota_usage = QuotaUsage::search(['code', '=', 'property.parkings.count'])
    ->read(['id'])
    ->first();

if(!$quota_usage) {
    throw new Exception('unknown_quota_usage', EQ_ERROR_INVALID_CONFIG);
}

QuotaUsage::id($quota_usage['id'])->update(['is_reached' => true]);

$context
    ->httpResponse()
    ->send();
