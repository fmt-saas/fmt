<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use documents\Document;
use equal\text\TextTransformer;

[$params, $providers] = eQual::announce([
    'description'   => 'Extract raw text from a given Document.',
    'help'          => 'This controller is meant to be used on EDMS instance having direct access to document data.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the document.',
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'text/plain'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

// search for documents matching given hash code (should be only one match)
$collection = Document::id($params['id']);
$document = $collection->read(['content_type', 'uuid'])->first();

if(!$document) {
    throw new Exception("document_unknown", EQ_ERROR_UNKNOWN_OBJECT);
}

// #todo - check content-type & limit to supported document formats

$document_data = eQual::run('get', 'documents_document', ['id' => $params['id']]);

$output_file = tempnam(sys_get_temp_dir(), 'extract_') . '.pdf';
file_put_contents($output_file, $document_data);


// 1) check if document is extractible

$call_shell = shell_exec("pdffonts -v 2>&1");
if(stripos($call_shell, 'pdffonts version') === false) {
    trigger_error("APP::pdffonts is not available or not in PATH. Output: " . $call_shell, EQ_REPORT_ERROR);
    throw new Exception('missing_mandatory_pdffonts_library', EQ_ERROR_INVALID_CONFIG);
}


$output = shell_exec('pdffonts ' . escapeshellarg($output_file));

if(strpos($output, 'Error') !== false) {
    throw new Exception('invalid_pdf', EQ_ERROR_INVALID_CONFIG);
}
$output_lines = explode("\n", trim($output));
if(max(count($output_lines) - 2, 0) === 0) {
    throw new Exception('non_extractible_pdf', EQ_ERROR_INVALID_CONFIG);
}


// 2) extract text

$call_shell = shell_exec("pdftotext -v 2>&1");
if(stripos($call_shell, 'pdftotext version') === false) {
    trigger_error("APP::pdftotext is not available or not in PATH. Output: " . $call_shell, EQ_REPORT_ERROR);
    throw new Exception('missing_mandatory_pdftotext_library', EQ_ERROR_INVALID_CONFIG);
}

$command = escapeshellcmd("pdftotext -enc UTF-8 " . escapeshellarg($output_file) . " -");

$raw_text = shell_exec($command);

unlink($output_file);

if ($output === null) {
    throw new Exception('document_extract_failed', EQ_ERROR_UNKNOWN);
}

// 3) transform special chars

$output = TextTransformer::toAscii($raw_text);


$context->httpResponse()
        ->body($output)
        ->send();
