<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Export assembly minutes: generate per-invitation documents (if missing), merge them into a single PDF, store the result as a non-EDMS document, and return its id.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The Fund Request Execution the export refers to.",
            'foreign_object'    => 'realestate\funding\FundRequestExecution',
            'required'          => true
        ],

        'communication_method' => [
            'type'              => 'string',
            'description'       => 'Method of sending.',
            'selection'         => [
                'postal',
                'postal_registered',
                'postal_registered_receipt'
            ]
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
 * @var \equal\php\Context                 $context
 */
['context' => $context] = $providers;


$fundRequestExecution = FundRequestExecution::id($params['id'])
    ->read(['status', 'condo_id', 'name'])
    ->first();

if(!$fundRequestExecution) {
    throw new Exception("unknown_assembly", EQ_ERROR_UNKNOWN_OBJECT);
}

// fetch invitations relating to given communication_method
$fundRequestCorrespondences = FundRequestCorrespondence::search([
        [ 'fund_request_execution_id', '=', $fundRequestExecution['id'] ],
        [ 'communication_method', '=', $params['communication_method'] ]
    ])
    ->read(['is_sent', 'document_id']);

// merge all generated documents (for each ownership) into a single PDF
$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'merged_pdf_');

foreach($fundRequestCorrespondences as $fund_request_correspondence_id => $fundRequestCorrespondence) {

    // #memo - `export-invitation` and `send-invitation` are the only controllers where documents are generated for Assembly invites
    if(!$fundRequestCorrespondence['document_id']) {
        // generate document, add it to EDMS, and attach it to invitation
        eQual::run('do', 'realestate_funding_FundRequestCorrespondence_generate-document', ['id' => $fund_request_correspondence_id]);
    }

    $fundRequestCorrespondence = FundRequestCorrespondence::id($fund_request_correspondence_id)
        ->read(['document_id' => ['data']])
        ->first();

    if(!$fundRequestCorrespondence['document_id']) {
        continue;
    }

    $temp = tempnam(sys_get_temp_dir(), 'pdf_');
    file_put_contents($temp, $fundRequestCorrespondence['document_id']['data'] ?? '');
    $temp_files[] = $temp;
}

// merge all generated documents
try {
    if(!count($temp_files)) {
        throw new Exception('no_files_generated', EQ_ERROR_UNKNOWN);
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
    trigger_error('APP::Error while merging documents ' . $e->getMessage(), EQ_REPORT_ERROR);
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

// store final result as a document (not visible through EDMS)
$document = Document::create([
        'name'          => 'Export - ' . $fundRequestExecution['name'] . ' (' . $params['communication_method'] . ')',
        'content_type'  => 'application/pdf',
        'data'          => $output,
        'condo_id'      => $fundRequestExecution['condo_id']
    ])
    ->first();

$context->httpResponse()
        ->body([
            'document_id' => $document['id']
        ])
        ->send();
