<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\quota\Quota;
use realestate\property\PropertyLotNature;

[$params, $providers] = eQual::announce([
    'description'   => "Checks whether a candidate main property lot can be created.",
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
        ],
        'is_primary' => [
            'type'              => 'boolean',
            'description'       => 'Whether the candidate property lot is primary.'
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

$quota = Quota::search(['code', '=', $params['code']])
    ->read(['value', 'is_active', 'thresholds_ids' => ['id', 'value', 'max_value', 'threshold_type']])
    ->first();

if(!$quota) {
    throw new Exception('unknown_quota_usage', EQ_ERROR_UNKNOWN_OBJECT);
}

$is_candidate_counted = intval($params['delta']) > 0;
if(isset($params['nature_id']) && $params['nature_id']) {
    $nature = PropertyLotNature::id($params['nature_id'])
        ->read(['hierarchy'])
        ->first();

    $is_candidate_counted = $nature && intval($nature['hierarchy']) === 1;
}
elseif(array_key_exists('is_primary', $params)) {
    $is_candidate_counted = (bool) $params['is_primary'];
}
else {
    $is_candidate_counted = true;
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
        if($projected_value > $threshold['value']) {
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
