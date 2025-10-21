<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use equal\text\TextTransformer;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

[$params, $providers] = eQual::announce([
    'description'   => 'Extract raw text from a given Document.Response is given as plain text.  Support PDF, XLS, XLSX',
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

$extractTxtFromPdf = function ($document_data) {
    $result = '';

    try {
        $output_file = tempnam(sys_get_temp_dir(), 'extract_') . '.pdf';
        file_put_contents($output_file, $document_data);

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
            trigger_error("APP::PDF contains no embedded fonts — likely a scanned or image-only document, text extraction not possible.", EQ_REPORT_ERROR);
            throw new Exception('non_extractible_pdf', EQ_ERROR_INVALID_CONFIG);
        }

        $call_shell = shell_exec("pdftotext -v 2>&1");
        if(stripos($call_shell, 'pdftotext version') === false) {
            trigger_error("APP::pdftotext is not available or not in PATH. Output: " . $call_shell, EQ_REPORT_ERROR);
            throw new Exception('missing_mandatory_pdftotext_library', EQ_ERROR_INVALID_CONFIG);
        }

        $command = escapeshellcmd("pdftotext -enc UTF-8 " . escapeshellarg($output_file) . " -");
        $raw_text = shell_exec($command);

        unlink($output_file);

        if($raw_text === null) {
            throw new Exception('document_extract_failed', EQ_ERROR_UNKNOWN);
        }
        $result = TextTransformer::toAscii($raw_text);
    }
    catch(Exception $e) {
        trigger_error("APP::PDF document extraction failed: ".$e->getMessage(), EQ_REPORT_WARNING);
    }

    return $result;
};

$extractTxtFromSpreadsheet = function ($document_data, string $format = 'Xlsx') {
    $result = '';

    try {
        $lines = [];
        $reader = IOFactory::createReader($format);
        $tmp = tempnam(sys_get_temp_dir(), 'xls_');
        file_put_contents($tmp, $document_data);
        $spreadsheet = $reader->load($tmp);
        unlink($tmp);

        $sheet = $spreadsheet->getActiveSheet();

        foreach($sheet->getRowIterator() as $row) {
            $rowData = [];
            foreach($row->getCellIterator() as $cell) {
                $value = $cell->getValue();

                if(Date::isDateTime($cell)) {
                    $timestamp = Date::excelToTimestamp($value);
                    $value = date('Y-m-d', $timestamp);
                }

                if(is_string($value) && (strpos($value, ',') !== false || strpos($value, '"') !== false)) {
                    $value = '"' . str_replace('"', '""', $value) . '"';
                }

                $rowData[] = $value;
            }

            $lines[] = implode(',', $rowData);
        }
        $result = implode("\n", $lines);
    }
    catch(Exception $e) {
        trigger_error("APP:unable to load data from given Excel file with PhpOffice ($format): " . $e->getMessage(), EQ_REPORT_ERROR);
    }

    return $result;
};

// Retrieve document
$collection = Document::id($params['id']);
$document = $collection->read(['content_type', 'uuid'])->first();

if(!$document) {
    throw new Exception("document_unknown", EQ_ERROR_UNKNOWN_OBJECT);
}

$document_data = eQual::run('get', 'documents_document', ['id' => $params['id']]);

$output = '';

switch ($document['content_type']) {
    case 'application/pdf':
        $output = $extractTxtFromPdf($document_data);
        break;

    case 'application/vnd.ms-excel':
        $output = $extractTxtFromSpreadsheet($document_data, 'Xls');
        break;

    case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
        $output = $extractTxtFromSpreadsheet($document_data, 'Xlsx');
        break;

    case 'text/plain':
    default:
        $output = $document_data;
}

$context->httpResponse()
        ->body($output)
        ->send();
