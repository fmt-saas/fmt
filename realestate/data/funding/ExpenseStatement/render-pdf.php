<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\DocumentType;
use realestate\funding\ExpenseStatement;
use finance\accounting\FiscalPeriod;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of given Exepense Statement ("Décompte de charge").',
    'help'          => 'This action generates a preview of the Expense Statement, with a PDF file merging all individual expense statements for all ownerships in the given fiscal period.',
    // #memo - this controller can take up a long time to generate document and should not be calle
    // in addition concurrent might infer with temporary files
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\ExpenseStatement',
            'description'       => "Identifier of the Expense statement to render.",
            'domain'            => ['condo_id', '=', 'object.condo_id']
        ],

        'debug' => [
            'type'        => 'boolean',
            'default'     => false
        ],

        'view_id' => [
            'description' => 'View id of the template to use.',
            'type'        => 'string',
            'default'     => 'print.default'
        ],

        'lang' =>  [
            'description' => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'        => 'string',
            'default'     => constant('DEFAULT_LANG')
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

/*

    This controller is never used to send a document,
    but only to preview a charge statement before confirming it.

    Therefore, when the statement is not yet posted,
    * the accounting entries integrating the statement into co-owner accounts are not present
    * and the funding entries have not yet been generated

*/

/** @var \equal\php\Context $context */
$context = $providers['context'];

$expenseStatement = ExpenseStatement::id($params['id'])
    ->read(['condo_id', 'fiscal_period_id', 'status', 'posting_date', 'is_cutoff_at_period_end', 'statement_owners_ids'])
    ->first();

if(!$expenseStatement) {
    throw new Exception('unknown_expense_statement', EQ_ERROR_UNKNOWN_OBJECT);
}

$fiscalPeriod = FiscalPeriod::id($expenseStatement['fiscal_period_id'])
    ->read(['date_from', 'date_to', 'condo_id' => ['ownerships_ids' => ['date_to']]])
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


/*
On doit ajouter des annexes au décompte (pour chaque propriétaire)

Si ces documents n'existent pas encore, on les créée

* bilan (balanceSheet)
* dépenses courantes (ExpenseSummary)


*/

// generate balance sheet for expense statement, if not generated yet
$balanceSheetDocument = Document::search([
        ['condo_id', '=', $expenseStatement['condo_id']],
        ['expense_statement_id', '=', $expenseStatement['id']],
        ['document_type_code', '=', 'balance_sheet']
    ])
    ->read(['data'])
    ->first();

if(!$balanceSheetDocument) {
    $data = eQual::run('get', 'finance_accounting_balanceSheet_render-pdf', ['params' => [
            'date_from' => date('c', $fiscalPeriod['date_from']),
            'date_to'   => date('c', $fiscalPeriod['date_to']),
            'condo_id'  => $expenseStatement['condo_id']
        ]]);

    $balanceSheetDocument = Document::create([
            'condo_id'              => $expenseStatement['condo_id'],
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
        ['condo_id', '=', $expenseStatement['condo_id']],
        ['expense_statement_id', '=', $expenseStatement['id']],
        ['document_type_code', '=', 'expense_summary']
    ])
    ->read(['data'])
    ->first();

if(!$expenseSummaryDocument) {
    $data = eQual::run('get', 'finance_accounting_expenseSummary_render-pdf', [ 'params' => [
            'date_from' => date('c', $fiscalPeriod['date_from']),
            'date_to'   => date('c', $fiscalPeriod['date_to']),
            'condo_id'  => $expenseStatement['condo_id']
        ]]);

    $expenseSummaryDocument = Document::create([
            'condo_id'              => $expenseStatement['condo_id'],
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

// merge resulting PDF documents for each Ownership
try {

    foreach($expenseStatement['statement_owners_ids'] as $ownership_id) {
        try {
            $pdf = eQual::run('get', 'realestate_funding_fiscalperiod_expensestatement_single-pdf', [
                    'expense_statement_id'  => $expenseStatement['id'],
                    'ownership_id'          => $ownership_id
                ]);
            $temp = tempnam(sys_get_temp_dir(), 'pdf_');
            file_put_contents($temp, $pdf);
            $temp_files[] = $temp;
        }
        catch(Exception $e) {
            // ignore (ownership with no expense ?)
        }
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
                    'ownership_id'      => $ownership_id
                ]);
            $temp = tempnam(sys_get_temp_dir(), 'pdf_');
            file_put_contents($temp, $pdf);
            $temp_files[] = $temp;
        }
        catch(Exception $e) {
            // ignore (unexpected error while generation account statement)
        }
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
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
