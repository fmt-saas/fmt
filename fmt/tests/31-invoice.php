<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\processing\DocumentProcess;
use realestate\purchase\accounting\invoice\PurchaseInvoiceImport;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

    '3101' => [
        'description' => "Validate purchase invoice import.",
        'help'        => "Convert a purchase invoice to standardized JSON and validate result against `purchase-invoice` schema.",
        'arrange'     => function () use ($providers) {
        },
        'act'         => function () use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'invoice_purchase.json');
            return $data;
        },
        'assert'      => function ($json_invoice) use ($providers) {
            $data = eQual::run('get', 'json-validate', ['json' => $json_invoice, 'schema_id' => 'urn:fmt:json-schema:finance:purchase-invoice']);
            $valid = $data['result'] ?? false;
            return $valid;
        },
        'rollback'    => function () use ($providers) {
        }
    ],

    '3102' => [
        'description' => "Test document processing of purchase invoice document.",
        'help'        => "Create PurchaseInvoiceImport with purchase invoice json to test the creation of the DocumentProcess.",
        'arrange'     => function () use ($providers) {
            $purchaseInvoiceImport = PurchaseInvoiceImport::create([
                'name' => 'Test document processing of Purchase invoice document'
            ])
                ->read(['id'])
                ->first();

            return $purchaseInvoiceImport;
        },
        'act'         => function ($purchaseInvoiceImport) use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'invoice_purchase.txt');

            PurchaseInvoiceImport::id($purchaseInvoiceImport['id'])->update(['data' => $data]);

            return $purchaseInvoiceImport;
        },
        'assert'      => function ($purchaseInvoiceImport) use ($providers) {
            $invoiceDocument = Document::search(['name', '=', 'Test document processing of Purchase invoice document'])
                ->read(['name'])
                ->first();

            $documentProcess = DocumentProcess::search(['name', '=', 'Test document processing of Purchase invoice document'])
                ->read(['status'])
                ->first();

            return $invoiceDocument['name'] === 'Test document processing of Purchase invoice document'
                 && $documentProcess['status'] === 'created';
        },
        'rollback'    => function () use ($providers) {
            $invoiceDocument = Document::search(['name', '=', 'Test document processing of Purchase invoice document'])
                ->read(['document_process_id'])
                ->first();

            /* @var \equal\orm\ObjectManager $orm */
            $orm = $providers['orm'];

            $orm->delete(Document::getType(), $invoiceDocument['id'], true);
            $orm->delete(DocumentProcess::getType(), $invoiceDocument['document_process_id'], true);
        }
    ]
];

