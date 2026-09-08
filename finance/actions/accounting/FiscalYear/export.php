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
use realestate\ownership\Ownership;

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

$getBalanceSheetDoc = function($expense_statement_ids, $fiscal_year_id, $condo_id) use($getDocumentByHash) {
    if(!count($expense_statement_ids)) {
        return null;
    }

    $balance_sheet = Document::search([
            ['document_type_code', '=', 'balance_sheet'],
            ['expense_statement_id', 'in', $expense_statement_ids],
            ['fiscal_year_id', '=', $fiscal_year_id],
            ['condo_id', '=', $condo_id]
        ])
        ->read(['name', 'extension', 'hash'])
        ->first(true);

    if(!$balance_sheet) {
        return null;
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

$getGeneralBalance = function($fiscal_year_id, $condo_id) use($getDocumentByHash) {
    $generalBalance = Document::search([
            ['document_type_code', '=', 'general_balance'],
            ['fiscal_year_id', '=', $fiscal_year_id],
            ['condo_id', '=', $condo_id]
        ])
        ->read(['name', 'extension', 'hash'])
        ->first(true);

    if(!$generalBalance) {
        return null;
    }

    return [
        'name'      => $generalBalance['name'],
        'extension' => $generalBalance['extension'],
        'data'      => $getDocumentByHash($generalBalance['hash'])
    ];
};

$getLedgerBalance = function($fiscal_year_id, $condo_id) use($getDocumentByHash) {
    $generalLedger = Document::search([
            ['document_type_code', '=', 'general_ledger'],
            ['fiscal_year_id', '=', $fiscal_year_id],
            ['condo_id', '=', $condo_id]
        ])
        ->read(['name', 'extension', 'hash'])
        ->first(true);

    if(!$generalLedger) {
        return null;
    }

    return [
        'name'      => $generalLedger['name'],
        'extension' => $generalLedger['extension'],
        'data'      => $getDocumentByHash($generalLedger['hash'])
    ];
};

$getSupplierInvoices = function($fiscal_year_id, $condo_id) use($getDocumentByHash) {
    $supplierInvoiceDocs = Document::search([
            ['document_type_code', '=', 'supplier_invoice'],
            ['fiscal_year_id', '=', $fiscal_year_id],
            ['condo_id', '=', $condo_id]
        ])
        ->read(['name', 'extension', 'hash'])
        ->get();

    return array_map(
        function($supplierInvoiceDoc) use($getDocumentByHash) {
            return [
                'name'      => $supplierInvoiceDoc['name'],
                'extension' => $supplierInvoiceDoc['extension'],
                'data'      => $getDocumentByHash($supplierInvoiceDoc['hash'])
            ];
        },
        $supplierInvoiceDocs
    );
};

$getBankStatements = function($fiscal_year_id, $condo_id) use($getDocumentByHash) {
    $bankStatementDocs = Document::search([
            ['document_type_code', '=', 'bank_statement'],
            ['fiscal_year_id', '=', $fiscal_year_id],
            ['condo_id', '=', $condo_id]
        ])
        ->read(['name', 'extension', 'hash'])
        ->get();

    return array_map(
        function($bankStatementDoc) use($getDocumentByHash) {
            return [
                'name'      => $bankStatementDoc['name'],
                'extension' => $bankStatementDoc['extension'],
                'data'      => $getDocumentByHash($bankStatementDoc['hash'])
            ];
        },
        $bankStatementDocs
    );
};

$getMiscOperations = function($fiscal_year_id, $condo_id) use($getDocumentByHash) {
    $miscOpDocs = Document::search([
            ['document_type_code', '=', 'misc_operation'],
            ['fiscal_year_id', '=', $fiscal_year_id],
            ['condo_id', '=', $condo_id]
        ])
        ->read(['name', 'extension', 'hash'])
        ->get();

    return array_map(
        function($miscOpDoc) use($getDocumentByHash) {
            return [
                'name'      => $miscOpDoc['name'],
                'extension' => $miscOpDoc['extension'],
                'data'      => $getDocumentByHash($miscOpDoc['hash'])
            ];
        },
        $miscOpDocs
    );
};

$getFundRequests = function($fiscal_year_id, $condo_id) use($getDocumentByHash) {
    $fundRequestDocs = Document::search([
            ['document_type_code', '=', 'fund_request'],
            ['fiscal_year_id', '=', $fiscal_year_id],
            ['condo_id', '=', $condo_id]
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

$getExpenseStatements = function($fiscal_year_id, $condo_id) use($getDocumentByHash) {
    $expenseStatementDocs = Document::search([
            ['document_type_code', '=', 'expense_statement'],
            ['fiscal_year_id', '=', $fiscal_year_id],
            ['condo_id', '=', $condo_id]
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

$createZipArchive = function($map_documents, $missing_documents) {
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

    $map_documents_quantities = [
        "01_Etat_de_cloture:"       => 0,
        "02_Livres_comptables:"     => 0,
        "03_Pieces_justificatives:" => 0,
        "04_Coproprietaires:"       => 0
    ];
    foreach($map_documents as $dir_name => $documents) {
        $cat = explode('/', $dir_name)[0];

        $map_documents_quantities[$cat] += count($documents);
    }

    $export_log = [
        "Date: ".date("Y-m-d H:i"),
        "",
        "01_Etat_de_cloture: {$map_documents_quantities['01_Etat_de_cloture']} documents",
        "02_Livres_comptables: {$map_documents_quantities['02_Livres_comptables']} documents",
        "03_Pieces_justificatives: {$map_documents_quantities['03_Pieces_justificatives']} documents",
        "04_Coproprietaires: {$map_documents_quantities['04_Coproprietaires']} documents"
    ];

    if(count($missing_documents) > 0) {
        $export_log[] = "";
        $export_log[] = "Documents manquants ignorés :";
        foreach($missing_documents as $missing_document) {
            $export_log[] = "- $missing_document";
        }
    }

    $zip->addFromString(
        'rapport_export.txt',
        implode(PHP_EOL, $export_log)
    );

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
$missing_documents = [];


/*
    Fiscal year
*/

$map_documents['01_Etat_de_cloture'][] = $getAccountingChartDoc($fiscalYear['condo_id']['id']);
// #todo - handle compte_de_resultats.pdf when the document generation is handled (related to fiscal period or year?)
// #todo - handle balance_de_cloture.pdf when the document generation is handled (related to fiscal period or year?)

$general_balance = $getGeneralBalance($fiscalYear['id'], $fiscalYear['condo_id']['id']);
if($general_balance) {
    $map_documents['02_Livres_comptables'][] = $general_balance;
}
else {
    $missing_documents[] = 'Balance générale (general_balance)';
}

$general_ledger = $getLedgerBalance($fiscalYear['id'], $fiscalYear['condo_id']['id']);
if($general_ledger) {
    $map_documents['02_Livres_comptables'][] = $general_ledger;
}
else {
    $missing_documents[] = 'Grand livre (general_ledger)';
}

$map_documents['03_Pieces_justificatives/facture_achats'] = $getSupplierInvoices($fiscalYear['id'], $fiscalYear['condo_id']['id']);
$map_documents['03_Pieces_justificatives/extraits_bancaires'] = $getBankStatements($fiscalYear['id'], $fiscalYear['condo_id']['id']);
$map_documents['03_Pieces_justificatives/autres_pieces'] = $getMiscOperations($fiscalYear['id'], $fiscalYear['condo_id']['id']);

$map_documents['04_Coproprietaires/appels_de_fonds'] = $getFundRequests($fiscalYear['id'], $fiscalYear['condo_id']['id']);
$map_documents['04_Coproprietaires/decomptes_de_charges'] = $getExpenseStatements($fiscalYear['id'], $fiscalYear['condo_id']['id']);
$map_documents['04_Coproprietaires/situations_de_compte'] = $getOwnerAccountStatements($fiscalYear['condo_id']['id'], $fiscalYear['date_from'], $fiscalYear['date_to']);


/*
    Fiscal periods
*/

foreach($fiscalYear['fiscal_periods_ids'] as $id => $period) {
    $balance_sheet = $getBalanceSheetDoc(
        array_keys($period['expense_statements_ids']),
        $fiscalYear['id'],
        $fiscalYear['condo_id']['id']
    );
    if($balance_sheet) {
        $map_documents['01_Etat_de_cloture'][] = $balance_sheet;
    }
    else {
        $period_date = date('Y-m-d', $period['date_from']);
        $missing_documents[] = "Bilan de clôture (balance_sheet) - période du $period_date";
    }
}


/*
    Generate document
*/

$condo_name = str_replace(' ', '_', $fiscalYear['condo_id']['name']);
$condo_name = preg_replace('/[^a-zA-Z0-9_%\[().\]\\/-]/s', '', $condo_name);

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
        'data'              => $params['export_type'] === 'consolidated_pdf' ? $createConsolidatedPdf($map_documents) : $createZipArchive($map_documents, $missing_documents),
        'condo_id'          => $fiscalYear['condo_id']['id'],
        'fiscal_year_id'    => $fiscalYear['id'],
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
