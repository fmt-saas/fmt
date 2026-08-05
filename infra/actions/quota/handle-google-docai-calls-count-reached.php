<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\quota\Quota;

[$params, $providers] = eQual::announce([
    'description'   => "Handles quota reached for 'google.docai.calls.count'.",
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

$quota = Quota::search(['code', '=', 'google.docai.calls.count'])
    ->read(['id'])
    ->first();

if(!$quota) {
    throw new Exception('unknown_quota_usage', EQ_ERROR_INVALID_CONFIG);
}



$context
    ->httpResponse()
    ->send();
