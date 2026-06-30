<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\Mail;
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

$domain = [
    ['status', '=', 'sent'],
];
if(isset($params['period_start'])) {
    $domain[] = ['modified', '>=', $params['period_start']];
}
if(isset($params['period_end'])) {
    $domain[] = ['modified', '<=', $params['period_end']];
}

$mails_qty = Mail::search($domain)->count();

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
