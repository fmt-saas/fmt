<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;
use realestate\sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => "Checks if the Funding relates to an Ownership for which a property transfer is in progress.",
    'help'          => "This controller schedules is a deferred Task for generating an archive with SEPA from candidates fundings.",
    'extends'       => 'core_model_check',
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\sale\pay\Funding',
            'description'      => 'Identifier of the Funding.',
        ],
        'ids' =>  [
            'type'             => 'one2many',
            'foreign_object'   => 'realestate\sale\pay\Funding',
            'description'      => 'List of Funding identifiers.',
            'default'          => []
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

if(!isset($params['id']) && !isset($params['ids'])) {
    throw new Exception("missing_id_or_ids", EQ_ERROR_INVALID_PARAM);
}

/*
    This controller is a check: an empty response means that no alert was raised
*/
$result = [];

$fundings_ids = $params['ids'];

if(isset($params['id']) && $params['id']) {
    $fundings_ids[] = $params['id'];
}

// ensure booking object exists and is readable
$fundings = Funding::ids($fundings_ids)
    ->read(['name', 'condo_id', 'sepa_document_id', 'bank_account_id' => ['name'], 'due_amount', 'has_mandate', 'is_sent', 'is_exported'])
    ->get();

if(count($fundings) <= 0) {
    throw new Exception("no_fundings", EQ_ERROR_INVALID_PARAM);
}

$exportingTask = ExportingTask::create([
        'name'          => "Export SEPA - " . date('Y-m-d_H-i-s'),
        'object_class'  => 'realestate\sale\pay\Funding'
    ])
    ->first();

$count_exporting_lines = 0;

// #memo - by convention we create a SEPA file for each Funding (instead of grouping them)
foreach($fundings as $funding_id => $funding) {

    if($funding['is_exported']) {
        // sepa_already_exported
        continue;
    }
    if(!$funding['is_sent']) {
        // sepa_not_generated
        continue;
    }
    if(!$funding['sepa_document_id']) {
        // missing_sepa_document
        continue;
    }
    if($funding['due_amount'] >= 0) {
        // sepa_only_for_outgoing_funding
        continue;
    }
    if($funding['has_mandate']) {
        // sepa_only_for_manual_funding
        continue;
    }

    ExportingTaskLine::create([
            'exporting_task_id' => $exportingTask['id'],
            'name'              => "Export enveloppe SEPA - {$funding['bank_account_id']['name']}",
            'controller'        => 'sale_pay_Funding_export-sepa',
            'params'            => json_encode([
                    'id' => $funding_id
                ])
        ]);

    Funding::id($funding_id)->update(['is_exported' => true]);

    ++$count_exporting_lines;
}

if($count_exporting_lines <= 0) {
    ExportingTask::id($exportingTask['id'])->delete(true);
}

$context->httpResponse()
        ->body($result)
        ->send();
