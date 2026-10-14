<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => 'Extract given UBL XML document data, and return the result as a JSON descriptor.',
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

/**
 * Methods
 */

$generateGenericInvoicePdf = function($data) {
    $invoice_number = trim($data['invoice_number']);
    $filename = preg_replace('/[^\pL\pN._-]+/u', '-', $invoice_number);
    $filename = trim($filename, '-.');
    $filename = ($filename === '') ? 'invoice.pdf' : $filename . '.pdf';

    $pdf = eQual::run('get', 'purchase_accounting_invoice_ubl_render-pdf', [
        'invoice_data'  => $data,
        'filename'      => $filename
    ]);

    return compact('filename', 'pdf');
};

/**
 * Action
 */

$document = Document::id($params['document_id'])
    ->read(['data'])
    ->first();

if(!$document) {
    throw new Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
}

$data = eQual::run('get', 'finance_accounting_invoice_extract-ubl', ['xml' => $document['data']]);

if(!in_array($data['document_type'], ['Invoice', 'CreditNote'])) {
    throw new Exception('invalid_ubl_data', EQ_ERROR_INVALID_PARAM);
}

$generic_invoice = $generateGenericInvoicePdf($data);

Document::create([
    'name'                  => $generic_invoice['filename'],
    'content_type'          => 'application/pdf',
    'data'                  => $generic_invoice['pdf'],
    'origin_document_id'    => $document['id'],
    'origin_document_role'  => 'rendering'
]);

foreach($data['attachments'] ?? [] as $attachment) {
    if($attachment['content_type'] !== 'application/pdf') {
        continue;
    }

    Document::create([
        'name'                  => $attachment['name'],
        'content_type'          => $attachment['content_type'],
        'data'                  => base64_decode($attachment['data']),
        'origin_document_id'    => $document['id'],
        'origin_document_role'  => 'attachment'
    ]);
}

// #memo - Invoices and credit notes are considered as supplier invoices
$data['document_type'] = 'supplier_invoice';

$context
    ->httpResponse()
    ->body($data)
    ->send();
