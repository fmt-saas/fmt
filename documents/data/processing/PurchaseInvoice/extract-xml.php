<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => 'Extract given UBL document data, and return the result as a JSON descriptor.',
    'params'        => [
        'document_id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'description'       => 'Identifier of the document to parse.',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$document = Document::id($params['document_id'])
    ->read(['data'])
    ->first();

if(!$document) {
    throw new Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
}

$ubl_data = eQual::run('get', 'finance_accounting_invoice_extract-ubl', ['xml' => $document['data']]);

if(!in_array($ubl_data['document_type'], ['Invoice', 'CreditNote'])) {
    throw new Exception('invalid_ubl_data', EQ_ERROR_INVALID_PARAM);
}

// #memo - Invoices and credit notes are considered supplier invoices
$ubl_data['document_type'] = 'supplier_invoice';

$context
    ->httpResponse()
    ->body($ubl_data)
    ->send();
