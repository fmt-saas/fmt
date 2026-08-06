<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use finance\accounting\AccountChart;
use finance\accounting\FiscalYear;
use finance\bank\BankStatement;
use realestate\purchase\accounting\invoice\PurchaseInvoice;

[$params, $providers] = eQual::announce([
    'description'   => "Create a ZIP/PDF file that contains accounting documents of a fiscal year for a statutory auditor review.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'description'       => "The fiscal year the accounting export is needed for.",
            'help'              => "One of fiscal year or period dates is mandatory."
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
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$getBalanceSheetDoc = function($period_id) {
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
        'data'      => eQual::run('get', 'documents_document', ['id' => $balance_sheet['hash']])
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

$getGeneralBalance = function($fiscal_year_id) {
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
        'data'      => eQual::run('get', 'documents_document', ['id' => $generalBalance['hash']])
    ];
};

$getLedgerBalance = function($fiscal_year_id) {
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
        'data'      => eQual::run('get', 'documents_document', ['id' => $generalLedger['hash']])
    ];
};

$getSupplierInvoices = function($fiscal_year_id) {
    $purchaseInvoices = PurchaseInvoice::search(['fiscal_year_id', '=', $fiscal_year_id])
        ->read(['document_id' => ['name', 'extension', 'data']])
        ->get();

    return array_map(
        function($invoice) {
            return [
                'name'      => $invoice['document_id']['name'],
                'extension' => $invoice['document_id']['extension'],
                'data'      => $invoice['document_id']['data']
            ];
        },
        $purchaseInvoices
    );
};

$getBankStatements = function($fiscal_year_id) {
    $bankStatements = BankStatement::search([
        ['fiscal_year_id', '=', $fiscal_year_id]
    ])
        ->read(['document_id' => ['name', 'extension', 'data']])
        ->get();

    return array_map(
        function($bankStatement) {
            return [
                'name'      => $bankStatement['document_id']['name'],
                'extension' => $bankStatement['document_id']['extension'],
                'data'      => $bankStatement['document_id']['data']
            ];
        },
        $bankStatements
    );
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

$fiscalYear = FiscalYear::id($params['id'])
    ->read([
        'condo_id',
        'name',
        'status',
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
    '01_Etat_de_cloture'                        => [],
    '02_Livres_comptables'                      => [],
    '03_Pieces_justificatives'                  => [],
    '03_Pieces_justificatives/facture_achats'   => [],
    '04_Coproprietaires'                        => []
];


/*
    Fiscal year
*/

$map_documents['01_Etat_de_cloture'][] = $getAccountingChartDoc($fiscalYear['condo_id']);

$map_documents['02_Livres_comptables'][] = $getGeneralBalance($fiscalYear['id']);
$map_documents['02_Livres_comptables'][] = $getLedgerBalance($fiscalYear['id']);

$map_documents['03_Pieces_justificatives/facture_achats'] = $getSupplierInvoices($fiscalYear['id']);
$map_documents['03_Pieces_justificatives/extraits_bancaires'] = $getBankStatements($fiscalYear['id']);

/*
    Fiscal periods
*/

foreach($fiscalYear['fiscal_periods_ids'] as $id => $period) {
    $map_documents['01_Etat_de_cloture'][] = $getBalanceSheetDoc($id);
    // #todo - handle compte_de_resultats.pdf when the document generation is handled
    // #todo - handle balance_de_cloture.pdf when the document generation is handled
}

$document = Document::create([
    'name'          => "{$fiscalYear['name']} - Export des documents comptable - Tous",
    'content_type'  => $params['content_type'],
    'data'          => $createZipArchive($map_documents),
    'condo_id'      => $fiscalYear['condo_id']
])
    ->first();

$context
    ->httpResponse()
    ->body([
        'document_id' => $document['id']
    ])
    ->send();
