<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\DocumentSubtype;
use documents\DocumentType;
use finance\accounting\AccountChart;
use finance\accounting\FiscalYear;
use finance\bank\BankStatement;
use realestate\ownership\Ownership;
use realestate\purchase\accounting\invoice\PurchaseInvoice;

[$params, $providers] = eQual::announce([
    'description'   => "Create a ZIP/PDF file that contains accounting documents of a fiscal year for a statutory auditor review.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'description'       => "The fiscal year the accounting export is needed for.",
            'help'              => "One of fiscal year or period dates is mandatory."
        ],
        'export_type' => [
            'type'              => 'string',
            'description'       => "Choose the export type, all documents in an archive or a consolidated PDF to ease printing.",
            'help'              => "Csv files aren't handled in case of a consolidated PDF export.",
            'selection'         => [
                'archive',
                'consolidated_pdf'
            ],
            'default'          => 'archive'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

/**
 * Methods
 */

$getDocumentByHash = function($hash) use($auth) {
    $user_id = $auth->userId();
    $auth->su();

    $document_data = eQual::run('get', 'documents_document', ['id' => $hash]);

    $auth->su($user_id);

    return $document_data;
};

$getBalanceSheetDoc = function($period_id) use($getDocumentByHash) {
    $balance_sheet = Document::search([
        ['document_type_code', '=', 'balance_sheet'],
        ['expense_statement_id.fiscal_period_id', '=', $period_id]
    ])
        ->read(['name', 'extension', 'hash'])
        ->first(true);

    if(!$balance_sheet) {
        throw new Exception('balance_sheet_doc_missing', EQ_ERROR_UNKNOWN_OBJECT);
    }

    return [
        'name'      => $balance_sheet['name'],
        'extension' => $balance_sheet['extension'],
        'data'      => $getDocumentByHash($balance_sheet['hash'])
    ];
};

$getAccountingChartDoc = function($condo_id) {
    $accountingChart = AccountChart::search([
        ['condo_id', '=', $condo_id],
        ['status', '=', 'active']
    ])
        ->read([
            'accounts_ids' => [
                'code',
                'parent_account_id' => ['code'],
                'description',
                'account_class',
                'account_type',
                'account_nature'
            ]
        ])
        ->first();

    if(!$accountingChart) {
        throw new Exception('accounting_chart_missing', EQ_ERROR_UNKNOWN_OBJECT);
    }

    $header = ['Code', 'Parent', 'Description', 'Classe', 'Type', 'Nature'];

    $tmp_file = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . uniqid('csv_', true)
        . '.csv';

    $fp = fopen($tmp_file, 'w');
    fputcsv($fp, $header, ',', '"', '');
    foreach($accountingChart['accounts_ids'] as $fields) {
        $line_data = [
            'code'              => $fields['code'],
            'parent'            => $fields['parent_account_id']['code'],
            'description'       => $fields['description'],
            'account_class'     => $fields['account_class'],
            'account_type'      => $fields['account_type'],
            'account_nature'    => $fields['account_nature']
        ];

        fputcsv($fp, $line_data, ',', '"', '');
    }
    fclose($fp);

    return [
        'name'      => 'plan_comptable',
        'extension' => 'csv',
        'data'      => file_get_contents($tmp_file)
    ];
};

$getGeneralBalance = function($fiscal_year_id) use($getDocumentByHash) {
    $generalBalance = Document::search([
        ['document_type_code', '=', 'general_balance'],
        ['fiscal_year_id', '=', $fiscal_year_id]
    ])
        ->read(['name', 'extension', 'hash'])
        ->first(true);

    if(!$generalBalance) {
        throw new Exception('general_balance_doc_missing', EQ_ERROR_UNKNOWN_OBJECT);
    }

    return [
        'name'      => $generalBalance['name'],
        'extension' => $generalBalance['extension'],
        'data'      => $getDocumentByHash($generalBalance['hash'])
    ];
};

$getLedgerBalance = function($fiscal_year_id) use($getDocumentByHash) {
    $generalLedger = Document::search([
        ['document_type_code', '=', 'general_ledger'],
        ['fiscal_year_id', '=', $fiscal_year_id]
    ])
        ->read(['name', 'extension', 'hash'])
        ->first(true);

    if(!$generalLedger) {
        throw new Exception('general_ledger_doc_missing', EQ_ERROR_UNKNOWN_OBJECT);
    }

    return [
        'name'      => $generalLedger['name'],
        'extension' => $generalLedger['extension'],
        'data'      => $getDocumentByHash($generalLedger['hash'])
    ];
};

$getSupplierInvoices = function($fiscal_year_id) use($getDocumentByHash) {
    $purchaseInvoices = PurchaseInvoice::search(['fiscal_year_id', '=', $fiscal_year_id])
        ->read(['document_id' => ['name', 'extension', 'hash']])
        ->get();

    return array_map(
        function($invoice) use($getDocumentByHash) {
            return [
                'name'      => $invoice['document_id']['name'],
                'extension' => $invoice['document_id']['extension'],
                'data'      => $getDocumentByHash($invoice['document_id']['hash'])
            ];
        },
        $purchaseInvoices
    );
};

$getBankStatements = function($fiscal_year_id) use($getDocumentByHash) {
    $bankStatements = BankStatement::search([
        ['fiscal_year_id', '=', $fiscal_year_id]
    ])
        ->read(['document_id' => ['name', 'extension', 'hash']])
        ->get();

    return array_map(
        function($bankStatement) use($getDocumentByHash) {
            return [
                'name'      => $bankStatement['document_id']['name'],
                'extension' => $bankStatement['document_id']['extension'],
                'data'      => $getDocumentByHash($bankStatement['document_id']['hash'])
            ];
        },
        $bankStatements
    );
};

$getMiscOperations = function($fiscal_year_id) use($getDocumentByHash) {
    $miscOpDocs = Document::search([
        ['document_type_code', '=', 'misc_operation'],
        ['misc_operation_id.fiscal_year_id', '=', $fiscal_year_id]
    ])
        ->read(['name', 'extension', 'hash'])
        ->get();

    return array_map(
        function($miscOpDoc) use($getDocumentByHash) {
            return [
                'name'      => $miscOpDoc['name'],
                'extension' => $miscOpDoc['extension'],
                'data'      => $getDocumentByHash($miscOpDoc['document_id']['hash'])
            ];
        },
        $miscOpDocs
    );
};

$getFundRequests = function($fiscal_year_id) use($getDocumentByHash) {
    $fundRequestDocs = Document::search([
        ['document_type_code', '=', 'fund_request'],
        ['fund_request_id.fiscal_year_id', '=', $fiscal_year_id]
    ])
        ->read(['name', 'extension', 'hash'])
        ->get();

    return array_map(
        function($fundRequestDoc) use($getDocumentByHash) {
            return [
                'name'      => $fundRequestDoc['name'],
                'extension' => $fundRequestDoc['extension'],
                'data'      => $getDocumentByHash($fundRequestDoc['hash'])
            ];
        },
        $fundRequestDocs
    );
};

$getExpenseStatements = function($fiscal_year_id) use($getDocumentByHash) {
    $expenseStatementDocs = Document::search([
        ['document_type_code', '=', 'expense_statement'],
        ['expense_statement_id.fiscal_year_id', 'in', $fiscal_year_id]
    ])
        ->read(['name', 'extension', 'hash'])
        ->get();

    return array_map(
        function($expenseStatementDoc) use($getDocumentByHash) {
            return [
                'name'      => $expenseStatementDoc['name'],
                'extension' => $expenseStatementDoc['extension'],
                'data'      => $getDocumentByHash($expenseStatementDoc['hash'])
            ];
        },
        $expenseStatementDocs
    );
};

$getOwnerAccountStatements = function($condo_id, $date_from, $date_to) {
    $ownerships = Ownership::search(['condo_id', '=', $condo_id])
        ->read(['code', 'date_from', 'date_to'])
        ->get();

    $documents = [];
    foreach($ownerships as $ownership_id => $ownership) {
        if(!empty($ownership['date_from']) && $ownership['date_from'] > $date_to) {
            continue;
        }
        if(!empty($ownership['date_to']) && $ownership['date_to'] < $date_from) {
            continue;
        }

        $documents[] = [
            'name'      => "Situation de compte - {$ownership['code']}",
            'extension' => 'pdf',
            'data'      => eQual::run('get', 'finance_accounting_ownerAccountStatement_render-pdf', [
                'ownership_id'  => $ownership_id,
                'date_from'     => $date_from,
                'date_to'       => $date_to
            ])
        ];
    }

    return $documents;
};

$createConsolidatedPdf = function($map_documents) {
    $temp_files = [];
    foreach($map_documents as $dir_name => $documents) {
        foreach($documents as $document) {
            if($document['extension'] === 'pdf') {
                $temp = tempnam(sys_get_temp_dir(), 'pdf_');
                file_put_contents($temp, $document['data']);
                $temp_files[] = $temp;
            }
        }
    }

    $tmp_file = tempnam(sys_get_temp_dir(), 'merged_pdf_');

    $escaped_files = array_map('escapeshellarg', $temp_files);
    $escaped_output = escapeshellarg($tmp_file);
    $cmd = 'qpdf --empty --pages ' . implode(' ', $escaped_files) . ' -- ' . $escaped_output . ' 2>&1';

    exec($cmd, $output_lines, $result_code);

    if ($result_code !== 0 || !file_exists($tmp_file)) {
        trigger_error("APP::qpdf merge failed:\n" . implode("\n", $output_lines), EQ_REPORT_ERROR);
        throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
    }

    return file_get_contents($tmp_file);
};

$createZipArchive = function($map_documents) {
    $tmp_file = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . uniqid('zip_', true)
        . '.zip';

    $zip = new ZipArchive();
    if($zip->open($tmp_file, ZipArchive::CREATE) !== true) {
        throw new Exception("failed_creating_zip_archive", EQ_ERROR_UNKNOWN);
    }

    foreach($map_documents as $dir_name => $documents) {
        $zip->addEmptyDir($dir_name);

        foreach($documents as $document) {
            $doc_name = str_replace(DIRECTORY_SEPARATOR, '-', $document['name']);
            $zip->addFromString(
                "$dir_name/$doc_name.{$document['extension']}",
                $document['data']
            );
        }
    }

    $zip->close();

    return file_get_contents($tmp_file);
};


/**
 * Action
 */

$fiscalYear = FiscalYear::id($params['id'])
    ->read([
        'name',
        'status',
        'date_from',
        'date_to',
        'condo_id' => ['name'],
        'fiscal_periods_ids' => [
            '@sort' => ['date_from' => 'asc'],
            'date_from',
            'expense_statements_ids' => [
                '@domain' => ['status', '=', 'posted']
            ]
        ]
    ])
    ->first(true);

if(!$fiscalYear) {
    throw new Exception("unknown_fiscal_year", EQ_ERROR_UNKNOWN_OBJECT);
}

if($fiscalYear['status'] !== 'closed') {
    throw new Exception("fiscal_year_not_closed", EQ_ERROR_NOT_ALLOWED);
}

$map_documents = [
    '01_Etat_de_cloture'                            => [],
    '02_Livres_comptables'                          => [],
    '03_Pieces_justificatives'                      => [],
    '03_Pieces_justificatives/facture_achats'       => [],
    '03_Pieces_justificatives/extraits_bancaires'   => [],
    '03_Pieces_justificatives/autres_pieces'        => [],
    '04_Coproprietaires'                            => [],
    '04_Coproprietaires/appels_de_fonds'            => [],
    '04_Coproprietaires/decomptes_de_charges'       => [],
    '04_Coproprietaires/situations_de_compte'       => []
];


/*
    Fiscal year
*/

$map_documents['01_Etat_de_cloture'][] = $getAccountingChartDoc($fiscalYear['condo_id']['id']);
// #todo - handle compte_de_resultats.pdf when the document generation is handled (related to fiscal period or year?)
// #todo - handle balance_de_cloture.pdf when the document generation is handled (related to fiscal period or year?)

$map_documents['02_Livres_comptables'][] = $getGeneralBalance($fiscalYear['id']);
$map_documents['02_Livres_comptables'][] = $getLedgerBalance($fiscalYear['id']);

$map_documents['03_Pieces_justificatives/facture_achats'] = $getSupplierInvoices($fiscalYear['id']);
$map_documents['03_Pieces_justificatives/extraits_bancaires'] = $getBankStatements($fiscalYear['id']);
$map_documents['03_Pieces_justificatives/autres_pieces'] = $getMiscOperations($fiscalYear['id']);

$map_documents['04_Coproprietaires/appels_de_fonds'] = $getFundRequests($fiscalYear['id']);
$map_documents['04_Coproprietaires/decomptes_de_charges'] = $getExpenseStatements($fiscalYear['id']);
$map_documents['04_Coproprietaires/situations_de_compte'] = $getOwnerAccountStatements($fiscalYear['condo_id']['id'], $fiscalYear['date_from'], $fiscalYear['date_to']);


/*
    Fiscal periods
*/

foreach($fiscalYear['fiscal_periods_ids'] as $id => $period) {
    $map_documents['01_Etat_de_cloture'][] = $getBalanceSheetDoc($id);
}


/*
    Generate document
*/

$condo_name = str_replace(' ', '_', $fiscalYear['condo_id']['name']);

$year_from = date('Y', $fiscalYear['date_from']);
$year_to = date('Y', $fiscalYear['date_to']);

$year = $year_from;
if($year_to !== $year_from) {
    $year .= '-' . $year_to;
}
$year = $year_from;

$document = Document::create([
    'name'              => "{$condo_name}_EXERCICE_$year".($params['export_type'] === 'consolidated_pdf' ? '_PDF' : ''),
    'content_type'      => $params['content_type'],
    'data'              => $params['export_type'] === 'consolidated_pdf' ? $createConsolidatedPdf($map_documents) : $createZipArchive($map_documents),
    'condo_id'          => $fiscalYear['condo_id']['id'],
    'document_type'     => ($dt = DocumentType::search(['code', '=', 'auditor_document'])->first()) ? $dt['id'] : null,
    'document_subtype'  => ($dt = DocumentSubtype::search(['code', '=', 'auditor_documents'])->first()) ? $dt['id'] : null
])
    ->first();


$context
    ->httpResponse()
    ->body([
        'document_id' => $document['id']
    ])
    ->send();
