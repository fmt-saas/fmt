<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use finance\accounting\MiscOperation;
use realestate\ownership\Ownership;

[$params, $providers] = eQual::announce([
    'description' => 'Merge consumption statement PDF documents for a given ownership and posting-date range.',
    'params'      => [
        'ownership_id' => [
            'type'           => 'many2one',
            'description'    => 'Ownership for which consumption statement documents are requested.',
            'foreign_object' => 'realestate\ownership\Ownership',
            'required'       => true
        ],

        'date_from' => [
            'type'        => 'date',
            'description' => 'First posting date included in the period.',
            'required'    => true
        ],

        'date_to' => [
            'type'        => 'date',
            'description' => 'Last posting date included in the period.',
            'required'    => true
        ]
    ],
    'access'      => [
        'visibility' => 'protected'
    ],
    'response'    => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

if($params['date_from'] > $params['date_to']) {
    throw new Exception('invalid_date_range', EQ_ERROR_INVALID_PARAM);
}

$ownership = Ownership::id($params['ownership_id'])
    ->read(['condo_id'])
    ->first();

if(!$ownership) {
    throw new Exception('unknown_ownership', EQ_ERROR_INVALID_PARAM);
}

$miscOperations = MiscOperation::search([
        ['condo_id', '=', $ownership['condo_id']],
        ['posting_date', '>=', $params['date_from']],
        ['posting_date', '<=', $params['date_to']],
        ['consumption_statement_id', '<>', null]
    ], [
        'sort' => ['posting_date' => 'asc', 'id' => 'asc']
    ])
    ->read([
        'consumption_statement_id' => [
            'document_id',
            'has_shared_document',
            'consumption_statement_calculation_lines_ids' => [
                'ownership_id',
                'document_id'
            ]
        ]
    ]);

$map_documents = [];

foreach($miscOperations as $miscOperation) {
    $consumptionStatement = $miscOperation['consumption_statement_id'];
    if(!$consumptionStatement || !$consumptionStatement['document_id']) {
        continue;
    }

    if($consumptionStatement['has_shared_document']) {
        $map_documents[$consumptionStatement['document_id']] = true;
        continue;
    }

    foreach($consumptionStatement['consumption_statement_calculation_lines_ids'] as $calculationLine) {
        if(
            $calculationLine['ownership_id'] === $params['ownership_id']
            && $calculationLine['document_id']
        ) {
            $map_documents[$calculationLine['document_id']] = true;
        }
    }
}

$document_ids = array_keys($map_documents);
if(empty($document_ids)) {
    throw new Exception('no_pdf_files_to_merge', EQ_ERROR_UNKNOWN);
}

$documents = Document::ids($document_ids)
    ->read(['id', 'data']);

$map_document_data = [];
foreach($documents as $document) {
    $map_document_data[$document['id']] = $document['data'];
}

$qpdf_output = [];
$qpdf_code = 0;
exec('qpdf --version 2>&1', $qpdf_output, $qpdf_code);

if($qpdf_code !== 0 || stripos(implode("\n", $qpdf_output), 'qpdf version') === false) {
    trigger_error("APP::qpdf is not available or not in PATH. Output: " . implode("\n", $qpdf_output), EQ_REPORT_ERROR);
    throw new Exception('missing_mandatory_qpdf_library', EQ_ERROR_INVALID_CONFIG);
}

$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'merged_pdf_');

if($output_file === false) {
    trigger_error('APP::Unable to create the temporary output file for consumption statement PDF merge.', EQ_REPORT_ERROR);
    throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
}

$output = '';

try {
    foreach($document_ids as $document_id) {
        if(!array_key_exists($document_id, $map_document_data)) {
            trigger_error("APP::Unable to load consumption statement document {$document_id} for PDF merge.", EQ_REPORT_ERROR);
            throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
        }

        $temp_file = tempnam(sys_get_temp_dir(), 'pdf_');
        if($temp_file === false) {
            trigger_error('APP::Unable to create a temporary source file for consumption statement PDF merge.', EQ_REPORT_ERROR);
            throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
        }

        $temp_files[] = $temp_file;
        if(file_put_contents($temp_file, $map_document_data[$document_id]) === false) {
            trigger_error("APP::Unable to write consumption statement document {$document_id} to a temporary file.", EQ_REPORT_ERROR);
            throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
        }
    }

    $escaped_files = array_map('escapeshellarg', $temp_files);
    $command = 'qpdf --empty --pages ' . implode(' ', $escaped_files) . ' -- ' . escapeshellarg($output_file) . ' 2>&1';

    $merge_output = [];
    $merge_code = 0;
    exec($command, $merge_output, $merge_code);

    if($merge_code !== 0 || !is_file($output_file) || filesize($output_file) === 0) {
        trigger_error("APP::qpdf merge failed:\n" . implode("\n", $merge_output), EQ_REPORT_ERROR);
        throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
    }

    $output = file_get_contents($output_file);
    if($output === false) {
        trigger_error('APP::Unable to read the merged consumption statement PDF.', EQ_REPORT_ERROR);
        throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
    }
}
finally {
    foreach($temp_files as $temp_file) {
        if(is_file($temp_file)) {
            @unlink($temp_file);
        }
    }

    if(is_file($output_file)) {
        @unlink($output_file);
    }
}

$context->httpResponse()
    ->header('Content-Disposition', 'inline; filename="consumption-statements.pdf"')
    ->header('Content-Type', 'application/pdf')
    ->body($output, true)
    ->send();
