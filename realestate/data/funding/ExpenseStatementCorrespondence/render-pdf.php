<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\DocumentType;
use finance\accounting\FiscalPeriod;
use realestate\funding\FundRequestExecutionCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF individual request for a given Expense Statement Correspondence (relates to a single Ownership).',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific FundRequestExecutionCorrespondence to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\FundRequestExecutionCorrespondence',
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

$fundRequestExecutionCorrespondence = FundRequestExecutionCorrespondence::id($params['id'])
    ->read([
        'status', 'condo_id', 'ownership_id', 'owner_id', 'name',
        'expense_statement_id' => ['id', 'fiscal_period_id', 'posting_date', 'is_cutoff_at_period_end']
    ])
    ->first();

if(!$fundRequestExecutionCorrespondence) {
    throw new Exception('unknown_fund_request_execution_correspondence', EQ_ERROR_UNKNOWN_OBJECT);
}

$expenseStatement = $fundRequestExecutionCorrespondence['expense_statement_id'];

$fiscalPeriod = FiscalPeriod::id($expenseStatement['fiscal_period_id'])
    ->read(['date_from', 'date_to', 'condo_id' => ['ownerships_ids' => ['date_to']]])
    ->first();

if(!$fiscalPeriod) {
    throw new Exception('unknown_fiscal_year', EQ_ERROR_UNKNOWN_OBJECT);
}

/*
    Generate or retrieve statement annexes

    If these documents do not exist yet, create them
        - balanceSheet ("bilan")
        - ExpenseSummary ("dépenses courantes")

*/

// generate balance sheet for expense statement, if not generated yet
$balanceSheetDocument = Document::search([
        ['condo_id', '=', $fundRequestExecutionCorrespondence['condo_id']],
        ['expense_statement_id', '=', $expenseStatement['id']],
        ['document_type_code', '=', 'balance_sheet']
    ])
    ->read(['data'])
    ->first();

if(!$balanceSheetDocument) {
    $data = eQual::run('get', 'finance_accounting_balanceSheet_render-pdf', ['params' => [
            'date_from' => date('c', $fiscalPeriod['date_from']),
            'date_to'   => date('c', $fiscalPeriod['date_to']),
            'condo_id'  => $fundRequestExecutionCorrespondence['condo_id']
        ]]);

    $balanceSheetDocument = Document::create([
            'condo_id'              => $fundRequestExecutionCorrespondence['condo_id'],
            'expense_statement_id'  => $expenseStatement['id'],
            'name'                  => 'Bilan du ' . date('d/m/Y', $fiscalPeriod['date_to']),
            'data'                  => $data,
            'is_origin'             => true,
            'is_source'             => true,
            'document_type_id'      => ($dt = DocumentType::search(['code', '=', 'balance_sheet'])->first()) ? $dt['id'] : null
        ])
        ->read(['data'])
        ->first();
}

// generate expense summary for expense statement, if not generated yet
$expenseSummaryDocument = Document::search([
        ['condo_id', '=', $fundRequestExecutionCorrespondence['condo_id']],
        ['expense_statement_id', '=', $expenseStatement['id']],
        ['document_type_code', '=', 'expense_summary']
    ])
    ->read(['data'])
    ->first();

if(!$expenseSummaryDocument) {
    $data = eQual::run('get', 'finance_accounting_expenseSummary_render-pdf', [ 'params' => [
            'date_from' => date('c', $fiscalPeriod['date_from']),
            'date_to'   => date('c', $fiscalPeriod['date_to']),
            'condo_id'  => $fundRequestExecutionCorrespondence['condo_id']
        ]]);

    $expenseSummaryDocument = Document::create([
            'condo_id'              => $fundRequestExecutionCorrespondence['condo_id'],
            'expense_statement_id'  => $expenseStatement['id'],
            'name'                  => 'Dépenses courantes au ' . date('d/m/Y', $fiscalPeriod['date_to']),
            'data'                  => $data,
            'is_origin'             => true,
            'is_source'             => true,
            'document_type_id'      => ($dt = DocumentType::search(['code', '=', 'expense_summary'])->first()) ? $dt['id'] : null
        ])
        ->read(['data'])
        ->first();
}

$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'merged_pdf_');

// merge all PDF documents for given Ownership/Owner
try {

    try {
        $pdf = eQual::run('get', 'realestate_funding_fiscalperiod_expensestatement_single-pdf', [
                'expense_statement_id'  => $expenseStatement['id'],
                'ownership_id'          => $fundRequestExecutionCorrespondence['ownership_id'],
                'owner_id'              => $fundRequestExecutionCorrespondence['owner_id']
            ]);
        $temp = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($temp, $pdf);
        $temp_files[] = $temp;
    }
    catch(Exception $e) {
        // ignore (ownership with no expense ?)
    }
    // append Owner Statement sheet
    try {
        // #todo
        $date_to = $expenseStatement['posting_date'];

        if($expenseStatement['is_cutoff_at_period_end']) {
            $date_to = $fiscalPeriod['date_to'];
        }

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
    // append Balance Sheet & Expense Summary
    try {
        $temp = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($temp, $balanceSheetDocument['data']);
        $temp_files[] = $temp;

        $temp = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($temp, $expenseSummaryDocument['data']);
        $temp_files[] = $temp;
    }
    catch(Exception $e) {
        // merging error
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
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
