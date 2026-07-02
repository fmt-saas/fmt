<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\quota\QuotaDefinition;
use infra\quota\QuotaUsage;

[$params, $providers] = eQual::announce([
    'description'   => "Shifts the start and end dates of all periodical quota usages.",
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

$startOf = function(string $period) {
    $map = [
        'week'  => 'Monday this week',
        'month' => 'first day of this month',
        'year'  => 'first day of January this year',
        'day'   => 'today',
    ];

    if(!isset($map[$period])) {
        throw new InvalidArgumentException("Unknown period: $period");
    }

    return strtotime($map[$period], time());
};

$endOf = function(string $period) {
    $map = [
        'week'  => 'Sunday this week 23:59:59',
        'month' => 'last day of this month 23:59:59',
        'year'  => 'last day of December this year 23:59:59',
        'day'   => 'today 23:59:59',
    ];

    if(!isset($map[$period])) {
        throw new InvalidArgumentException("Unknown period: $period");
    }

    return strtotime($map[$period], time());
};

$periodical_quota_defs_ids = QuotaDefinition::search(['quota_type', '=', 'period'])->ids();

$periodical_quota_usages = QuotaUsage::search(['definition_id', 'in', $periodical_quota_defs_ids])
    ->read(['period_start', 'period_end', 'definition_id' => ['period_type']])
    ->get();

foreach($periodical_quota_usages as $id => $quota_usage) {
    if($quota_usage['period_end'] > time()) {
        continue;
    }

    QuotaUsage::id($id)
        ->update([
            'period_start'  => $startOf($quota_usage['definition_id']['period_type']),
            'period_end'    => $endOf($quota_usage['definition_id']['period_type']),
            'is_reached'    => false
        ]);
}

$context
    ->httpResponse()
    ->send();
