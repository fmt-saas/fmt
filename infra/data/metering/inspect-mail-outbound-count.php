<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\metering\MeteringRecord;
use infra\metering\MetricDefinition;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the quantity of mails sent for metering use.",
    'params'        => [
        'period_start' => [
            'type'              => 'datetime',
            'description'       => "Filter to get only records created after the given time."
        ],
        'period_end' => [
            'type'              => 'datetime',
            'description'       => "Filter to get only records created before the given time."
        ]
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

$metric_def = MetricDefinition::search(['code', '=', 'email.outbound.count'])
    ->read(['id'])
    ->first();

if(!$metric_def) {
    throw new Exception("unknow_metric_definition", EQ_ERROR_UNKNOWN_OBJECT);
}

$domain = [
    ['metric_definition_id', '=', $metric_def['id']],
];
if(isset($params['period_start'])) {
    $domain[] = ['record_time', '>=', $params['period_start']];
}
if(isset($params['period_end'])) {
    $domain[] = ['record_time', '<=', $params['period_end']];
}

$records = MeteringRecord::search($domain)
    ->read(['value'])
    ->get();

$mails_qty = 0;
foreach($records as $record) {
    $mails_qty += intval($record['value']);
}

$result = [
    'value'     => $mails_qty,
    'unit'      => 'count',
    'logs'      => [],
    'errors'    => [],
    'warnings'  => []
];

$context
    ->httpResponse()
    ->body($result)
    ->send();
