<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\quota\Quota;
use realestate\property\PropertyLotNature;

[$params, $providers] = eQual::announce([
    'description'   => "Checks whether a candidate parking property lot can be created.",
    'params'        => [
        'code' => [
            'type'              => 'string',
            'description'       => 'Quota code.',
            'required'          => true
        ],
        'delta' => [
            'type'              => 'integer',
            'description'       => 'Candidate increment.',
            'default'           => 0
        ],
        'nature_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\PropertyLotNature',
            'description'       => 'Candidate property lot nature.'
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

$is_candidate_counted = intval($params['delta']) > 0;
if(isset($params['nature_id']) && $params['nature_id']) {
    $nature = PropertyLotNature::id($params['nature_id'])
        ->read(['code'])
        ->first();

    $nature_code = strtolower($nature['code'] ?? '');
    $is_candidate_counted = in_array($nature_code, ['garage', 'parking']);
}

$delta = $is_candidate_counted ? max(1, intval($params['delta'])) : 0;
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

if($quota['is_active'] && $is_candidate_counted) {
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
