<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => "Returns a copy of the given PDF document containing only the requested page range.",
    'help'          => "The page range is inclusive. This output is a technical copy; the original document remains unchanged.",
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'description'       => "The PDF document to split.",
            'required'          => true
        ],

        'page_from' => [
            'type'              => 'int',
            'description'       => "The first page to include.",
            'required'          => true,
            'min'               => 1
        ],

        'page_to' => [
            'type'              => 'int',
            'description'       => "The last page to include.",
            'required'          => true,
            'min'               => 1
        ],

        'disposition' => [
            'type'              => 'string',
            'selection'         => [
                'inline',
                'attachment'
            ],
            'default'           => 'inline'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$page_from = filter_var($params['page_from'], FILTER_VALIDATE_INT);
$page_to = filter_var($params['page_to'], FILTER_VALIDATE_INT);

if($page_from === false || $page_to === false || $page_from < 1 || $page_to < $page_from) {
    throw new Exception('invalid_pdf_page_interval', EQ_ERROR_INVALID_PARAM);
}

$document = Document::id($params['id'])
    ->read(['content_type', 'data', 'name'])
    ->first();

if(!$document) {
    throw new Exception('unknown_document', EQ_ERROR_UNKNOWN_OBJECT);
}

if($document['content_type'] !== 'application/pdf') {
    throw new Exception('not_pdf_document', EQ_ERROR_INVALID_PARAM);
}

$qpdf_output = [];
$qpdf_code = 0;
exec('qpdf --version 2>&1', $qpdf_output, $qpdf_code);

if($qpdf_code !== 0 || stripos(implode("\n", $qpdf_output), 'qpdf version') === false) {
    trigger_error("APP::qpdf is not available or not in PATH. Output: " . implode("\n", $qpdf_output), EQ_REPORT_ERROR);
    throw new Exception('missing_mandatory_qpdf_library', EQ_ERROR_INVALID_CONFIG);
}

$source_file = tempnam(sys_get_temp_dir(), 'pdf_');
$split_file = tempnam(sys_get_temp_dir(), 'split_pdf_');

if($source_file === false || $split_file === false) {
    if($source_file !== false && is_file($source_file)) {
        @unlink($source_file);
    }
    if($split_file !== false && is_file($split_file)) {
        @unlink($split_file);
    }

    trigger_error("APP::Unable to create temporary files for PDF split.", EQ_REPORT_ERROR);
    throw new Exception('pdf_split_failed', EQ_ERROR_UNKNOWN);
}

$output = '';

try {
    if(file_put_contents($source_file, $document['data']) === false) {
        trigger_error("APP::Unable to write the source PDF temporary file.", EQ_REPORT_ERROR);
        throw new Exception('pdf_split_failed', EQ_ERROR_UNKNOWN);
    }

    $page_range = $page_from . '-' . $page_to;
    $command = sprintf(
        'qpdf --empty --pages %s %s -- %s 2>&1',
        escapeshellarg($source_file),
        escapeshellarg($page_range),
        escapeshellarg($split_file)
    );

    $output_lines = [];
    $result_code = 0;
    exec($command, $output_lines, $result_code);

    if($result_code !== 0 || !is_file($split_file) || filesize($split_file) === 0) {
        trigger_error("APP::qpdf split failed:\n" . implode("\n", $output_lines), EQ_REPORT_ERROR);
        throw new Exception('pdf_split_failed', EQ_ERROR_UNKNOWN);
    }

    $output = file_get_contents($split_file);
    if($output === false) {
        trigger_error("APP::Unable to read the split PDF temporary file.", EQ_REPORT_ERROR);
        throw new Exception('pdf_split_failed', EQ_ERROR_UNKNOWN);
    }
}
finally {
    if(is_file($source_file)) {
        @unlink($source_file);
    }
    if(is_file($split_file)) {
        @unlink($split_file);
    }
}

$filename = str_replace(['"', "\r", "\n"], '', $document['name']);

$context->httpResponse()
    ->header('Content-Disposition', $params['disposition'] . '; filename="' . $filename . '"')
    ->header('Content-Type', 'application/pdf')
    ->body($output, true)
    ->send();
