<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use finance\accounting\FiscalPeriod;

list($params, $providers) = eQual::announce([
    'description'   => 'Generate a PDF file holding expense statement for a given period, for all owners.',
    'params'        => [
        'fiscal_period_id' => [
            'label'             => 'Fiscal Period',
            'description'       => 'Identifier of the targeted Fiscal Period.',
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalPeriod',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$fiscalPeriod = FiscalPeriod::id($params['fiscal_period_id'])
    ->read(['condo_id' => ['ownerships_ids' => ['date_to']]])
    ->first();

if(!$fiscalPeriod) {
    throw new Exception('unknown_fiscal_year', EQ_ERROR_UNKNOWN_OBJECT);
}

// ensure qpdf compliance
$call_qpdf = shell_exec("qpdf --version 2>&1");
if(stripos($call_qpdf, 'qpdf version') === false) {
    trigger_error("APP::qpdf is not available or not in PATH. Output: " . $call_qpdf, EQ_REPORT_ERROR);
    throw new Exception('missing_mandatory_qpdf_library', EQ_ERROR_INVALID_CONFIG);
}

$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'merged_') . '.pdf';

try {

    foreach($fiscalPeriod['condo_id']['ownerships_ids'] as $ownership_id => $ownership) {
        if(!($ownership['date_to']) || $ownership['date_to'] > $fiscalPeriod['date_to']) {
            try {
                $pdf = eQual::run('get', 'realestate_funding_fiscalperiod_expensestatement_single-pdf', [
                        'fiscal_period_id'  => $params['fiscal_period_id'],
                        'ownership_id'      => $ownership_id
                    ]);
                $temp = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
                file_put_contents($temp, $pdf);
                $temp_files[] = $temp;
            }
            catch(Exception $e) {
                // ignore (ownership with no expense ?)
            }
        }
    }

    $escaped_files = array_map('escapeshellarg', $temp_files);
    $escaped_output = escapeshellarg($output_file);
    $cmd = 'qpdf --empty --pages ' . implode(' ', $escaped_files) . ' -- ' . $escaped_output . ' 2>&1';

    exec($cmd, $output_lines, $result_code);

    if ($result_code !== 0 || !file_exists($output_file)) {
        trigger_error("APP::qpdf merge failed:\n" . implode("\n", $output_lines), EQ_REPORT_ERROR);
        throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
    }

    $output = file_get_contents($output_file);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_ERROR_INVALID_CONFIG);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}
finally {
    foreach ($temp_files as $file) {
        @unlink($file);
    }
    @unlink($output_file);
}

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();