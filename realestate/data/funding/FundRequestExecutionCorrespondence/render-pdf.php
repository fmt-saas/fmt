<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalPeriod;
use realestate\funding\FundRequestExecutionCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF document for a fund request execution correspondence.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific FundRequestExecutionCorrespondence to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\FundRequestExecutionCorrespondence',
            'required'          => true
        ],
        'debug' => [
            'type'              => 'boolean',
            'default'           => false
        ],
        'view_id' => [
            'type'              => 'string',
            'default'           => 'print.default'
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$fundRequestExecutionCorrespondence = FundRequestExecutionCorrespondence::id($params['id'])
    ->read(['ownership_id', 'owner_id', 'fund_request_execution_id' => ['id', 'fiscal_period_id', 'posting_date']])
    ->first();

if(!$fundRequestExecutionCorrespondence) {
    throw new Exception('unknown_fund_request_execution_correspondence', EQ_ERROR_UNKNOWN_OBJECT);
}

$fundRequestExecution = $fundRequestExecutionCorrespondence['fund_request_execution_id'];

$fiscalPeriod = FiscalPeriod::id($fundRequestExecution['fiscal_period_id'])
    ->read(['date_from', 'date_to'])
    ->first();

if(!$fiscalPeriod) {
    throw new Exception('unknown_fiscal_year', EQ_ERROR_UNKNOWN_OBJECT);
}

$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'merged_pdf_');

try {
    try {
        $pdf = eQual::run('get', 'realestate_funding_fiscalperiod_fundrequestexecution_single-pdf', [
            'fund_request_execution_id' => $fundRequestExecution['id'],
            'ownership_id'              => $fundRequestExecutionCorrespondence['ownership_id'],
            'owner_id'                  => $fundRequestExecutionCorrespondence['owner_id']
        ]);

        $temp = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($temp, $pdf);
        $temp_files[] = $temp;
    }
    catch(Exception $e) {
    }

    // append Owner Statement sheet
    try {
        // #todo
        $date_to = $fundRequestExecution['posting_date'];

        $pdf = eQual::run('get', 'finance_accounting_ownerAccountStatement_render-pdf', [
                'date_from'         => $fiscalPeriod['date_from'],
                'date_to'           => $fiscalPeriod['date_to'],
                // 'date_to'           => $date_to,
                'ownership_id'      => $fundRequestExecutionCorrespondence['ownership_id']
            ]);
        $temp = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($temp, $pdf);
        $temp_files[] = $temp;
    }
    catch(Exception $e) {
        // ignore (unexpected error while generation account statement)
    }

    $escaped_files = array_map('escapeshellarg', $temp_files);
    $escaped_output = escapeshellarg($output_file);
    $cmd = 'qpdf --empty --pages ' . implode(' ', $escaped_files) . ' -- ' . $escaped_output . ' 2>&1';

    exec($cmd, $output_lines, $result_code);

    if($result_code !== 0 || !file_exists($output_file)) {
        trigger_error("APP::qpdf merge failed:\n" . implode("\n", $output_lines), EQ_REPORT_ERROR);
        throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
    }

    $output = file_get_contents($output_file);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template: ' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}
finally {
    foreach($temp_files as $file) {
        if(isset($file) && is_file($file)) {
            @unlink($file);
        }
    }
    if(isset($output_file) && is_file($output_file)) {
        @unlink($output_file);
    }
}


$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output ?? '')
        ->send();
