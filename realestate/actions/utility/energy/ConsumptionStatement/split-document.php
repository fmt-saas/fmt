<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use realestate\utility\energy\ConsumptionStatement;
use realestate\utility\energy\ConsumptionStatementCalculationLine;

[$params, $providers] = eQual::announce([
    'description' => 'Split a consumption statement document and attach each page range to its calculation line.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'description'    => 'The consumption statement whose document must be split.',
            'foreign_object' => 'realestate\utility\energy\ConsumptionStatement',
            'required'       => true
        ]
    ],
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$consumptionStatement = ConsumptionStatement::id($params['id'])
    ->read([
        'status',
        'condo_id',
        'document_id' => ['name'],
        'has_shared_document',
        'consumption_statement_calculation_lines_ids' => [
            'page_from',
            'page_to',
            'document_id'
        ]
    ])
    ->first();

if(!$consumptionStatement) {
    throw new Exception('unknown_consumption_statement', EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$consumptionStatement['document_id'] || $consumptionStatement['has_shared_document']) {
    throw new Exception('no_document_to_split', 0);
}

if(!in_array($consumptionStatement['status'], ['to_encode', 'encoded'], true)) {
    throw new Exception('statement_lines_locked', EQ_ERROR_INVALID_PARAM);
}

$map_page_ranges = [];
foreach($consumptionStatement['consumption_statement_calculation_lines_ids'] as $line_id => $calculationLine) {
    $page_from = $calculationLine['page_from'];
    $page_to = $calculationLine['page_to'];

    if(!$page_from || !$page_to || $page_from < 1 || $page_to < $page_from) {
        throw new Exception('invalid_pdf_page_interval', EQ_ERROR_INVALID_PARAM);
    }

    $map_page_ranges[$line_id] = [
        'page_from'   => $page_from,
        'page_to'     => $page_to,
        'document_id' => $calculationLine['document_id']
    ];
}

$splitDocumentsData = [];
foreach($map_page_ranges as $line_id => $pageRange) {
    $splitDocumentsData[$line_id] = eQual::run('get', 'documents_Document_split', [
        'id'        => $consumptionStatement['document_id']['id'],
        'page_from' => $pageRange['page_from'],
        'page_to'   => $pageRange['page_to']
    ]);
}

foreach($splitDocumentsData as $line_id => $data) {
    $current_document_id = $map_page_ranges[$line_id]['document_id'];
    if($current_document_id) {
        Document::id($current_document_id)->delete();
    }

    $document = Document::create([
            'name'     => $consumptionStatement['document_id']['name'],
            'data'     => $data,
            'condo_id' => $consumptionStatement['condo_id']
        ])
        ->first();

    ConsumptionStatementCalculationLine::id($line_id)
        ->update(['document_id' => $document['id']]);
}

$context->httpResponse()
    ->status(204)
    ->send();
