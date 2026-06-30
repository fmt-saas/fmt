<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use infra\metering\MeteringReading;
use infra\metering\MeteringReadingLine;
use infra\metering\MetricDefinition;

[$params, $providers] = eQual::announce([
    'description'   => 'Generates the metering readings using the metering inspect data controllers.',
    'params'        => [
        'period_start' => [
            'type'              => 'datetime',
            'description'       => 'Start of the measured period.',
            // 'required'          => true,
            'default'           => strtotime("First day of this year")
        ],
        'period_end' => [
            'type'              => 'datetime',
            'description'       => 'End of the measured period.',
            // 'required'          => true,
            'default'           => strtotime("Last day of this year")
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'agency') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$measured_at = time();

$metering_reading = MeteringReading::create([
    'instance_id'   => 1,
    'measured_at'   => $measured_at,
    'period_start'  => $params['period_start'],
    'period_end'    => $params['period_start'],
])
    ->read(['id'])
    ->first();

$metric_defs = MetricDefinition::search()
    ->read(['collector'])
    ->get();

foreach($metric_defs as $metric_def) {
    $announce = config\eQual::run('get', $metric_def['collector'], ['announce' => true], false);
    $announcement = json_decode($announce, true);

    $inspect_params = [];
    if(isset($announcement['announcement']['params']['period_start'])) {
        $inspect_params['period_start'] = $params['period_start'];
    }
    if(isset($announcement['announcement']['params']['period_end'])) {
        $inspect_params['period_end'] = $params['period_end'];
    }

    $reading_line_data = [
        'metering_reading_id'   => $metering_reading['id'],
        'metric_definition_id'  => $metric_def['id']
    ];

    try {
        $inspect_res = eQual::run('get', $metric_def['collector'], $inspect_params);

        $value_type = gettype($inspect_res['value']);
        $value = match ($value_type) {
            'string'    => $inspect_res['value'],
            'boolean'   => $inspect_res['value'] ? '1' : '0',
            'integer'   => intval($inspect_res['value']),
            'double'    => floatval($inspect_res['value']),
            default     => json_encode($inspect_res['value']),
        };

        $reading_line_data = array_merge(
            $reading_line_data,
            [
                'value'     => $value,
                'details'   => json_encode($inspect_res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ]
        );
    }
    catch(Exception $e) {
        $reading_line_data = array_merge(
            $reading_line_data,
            [
                'status'    => 'failed',
                'error'     => $e->getMessage()
            ]
        );
    }

    MeteringReadingLine::create($reading_line_data);
}

$context
    ->httpResponse()
    ->status(201)
    ->send();
