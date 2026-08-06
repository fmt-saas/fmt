<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use finance\accounting\FiscalYear;

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
    '01_Etat_de_cloture' => [],
    '02_Livres_comptables' => [],
    '03_Pieces_justificatives' => [],
    '04_Coproprietaires' => []
];

foreach($fiscalYear['fiscal_periods_ids'] as $period) {
    $balance_sheet = Document::search([
        ['document_type_code', '=', 'balance_sheet'],
        ['expense_statement_id.fiscal_period_id', '=', $period['id']]
    ])
        ->read(['name', 'extension', 'hash'])
        ->first(true);

    if(!$balance_sheet) {
        throw new Exception('balance_sheet_doc_missing', EQ_ERROR_UNKNOWN_OBJECT);
    }

    $map_documents['01_Etat_de_cloture'][] = [
        'name'      => $balance_sheet['name'],
        'extension' => $balance_sheet['extension'],
        'data'      => eQual::run('get', 'documents_document', ['id' => $balance_sheet['hash']])
    ];
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
