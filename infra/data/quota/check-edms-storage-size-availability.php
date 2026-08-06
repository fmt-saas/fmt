<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\quota\Quota;

[$params, $providers] = eQual::announce([
    'description'   => "Checks whether a candidate EDMS storage increment is allowed.",
    'params'        => [
        'code' => [
            'type'              => 'string',
            'description'       => 'Quota code.',
            'required'          => true
        ],
        'delta' => [
            'type'              => 'integer',
            'description'       => 'Candidate storage increment.',
            'default'           => 0
        ],
        'content_size' => [
            'type'              => 'integer',
            'description'       => 'Candidate document size.'
        ],
        'data' => [
            'type'              => 'binary',
            'description'       => 'Candidate document binary data.'
        ]
    ],
    'access'        => [
        'visibility'    => 'protected'
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

$is_threshold_reached = function(int $value, array $threshold): bool {
    if($value < intval($threshold['value'])) {
        return false;
    }

    if(isset($threshold['max_value']) && !is_null($threshold['max_value'])) {
        return $value <= intval($threshold['max_value']);
    }

    return true;
};

$quota = Quota::search(['code', '=', $params['code']])
    ->read(['value', 'is_active', 'thresholds_ids' => ['id', 'value', 'max_value', 'threshold_type']])
    ->first();

if(!$quota) {
    throw new Exception('unknown_quota_usage', EQ_ERROR_UNKNOWN_OBJECT);
}

$delta = intval($params['delta']);
if(isset($params['content_size'])) {
    $delta = max($delta, intval($params['content_size']));
}
if(isset($params['data']) && is_string($params['data'])) {
    $delta = max($delta, strlen($params['data']));
}

$value = intval($quota['value']);
$projected_value = $value + $delta;

$result = [
    'allowed'           => true,
    'reason'            => null,
    'code'              => $params['code'],
    'value'             => $value,
    'delta'             => $delta,
    'projected_value'   => $projected_value,
    'threshold_id'      => null
];

if($quota['is_active']) {
    foreach($quota['thresholds_ids'] as $threshold) {
        if($threshold['threshold_type'] !== 'blocking') {
            continue;
        }
        if($is_threshold_reached($projected_value, $threshold)) {
            $result['allowed'] = false;
            $result['reason'] = 'quota_unavailable';
            $result['threshold_id'] = $threshold['id'] ?? null;
            break;
        }
    }
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
