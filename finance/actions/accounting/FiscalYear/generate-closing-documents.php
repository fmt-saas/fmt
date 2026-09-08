<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\DocumentType;
use finance\accounting\FiscalYear;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate the closing documents of a fiscal year.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'description'       => 'Identifier of the fiscal year whose closing documents must be generated.',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$fiscalYear = FiscalYear::id($params['id'])
    ->read(['condo_id', 'name', 'status'])
    ->first();

if(!$fiscalYear) {
    throw new Exception('unknown_fiscal_year', EQ_ERROR_UNKNOWN_OBJECT);
}

if($fiscalYear['status'] !== 'closed') {
    throw new Exception('fiscal_year_not_closed', EQ_ERROR_INVALID_PARAM);
}

$general_balance_doc = eQual::run(
    'get',
    'finance_accounting_generalBalance_render-pdf',
    [
        'params' => [
            'condo_id'          => $fiscalYear['condo_id'],
            'fiscal_year_id'    => $fiscalYear['id']
        ]
    ]
);

Document::create([
    'name'                  => "Balance Générale Des Comptes - {$fiscalYear['name']}",
    'condo_id'              => $fiscalYear['condo_id'],
    'fiscal_year_id'        => $fiscalYear['id'],
    'document_type_id'      => ($dt = DocumentType::search(['code', '=', 'general_balance'])->first()) ? $dt['id'] : null,
    'document_visibility'   => 'agency',
    'is_origin'             => true,
    'data'                  => $general_balance_doc
]);

$general_ledger_doc = eQual::run(
    'get',
    'finance_accounting_generalLedger_render-pdf',
    [
        'params' => [
            'condo_id'          => $fiscalYear['condo_id'],
            'fiscal_year_id'    => $fiscalYear['id']
        ]
    ]
);

Document::create([
    'name'                  => "Grand Livre - {$fiscalYear['name']}",
    'condo_id'              => $fiscalYear['condo_id'],
    'fiscal_year_id'        => $fiscalYear['id'],
    'document_type_id'      => ($dt = DocumentType::search(['code', '=', 'general_ledger'])->first()) ? $dt['id'] : null,
    'document_visibility'   => 'agency',
    'is_origin'             => true,
    'data'                  => $general_ledger_doc
]);

$context->httpResponse()
        ->status(201)
        ->send();
