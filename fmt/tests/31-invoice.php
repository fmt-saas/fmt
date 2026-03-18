<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\DocumentSubtype;
use documents\DocumentType;
use documents\processing\DocumentProcess;
use purchase\accounting\invoice\PurchaseInvoiceLine;
use purchase\supplier\Supplier;
use purchase\supplier\Suppliership;
use purchase\supplier\SupplierType;
use realestate\finance\accounting\AccountingEntry;
use realestate\finance\accounting\AccountingEntryLine;
use realestate\property\Condominium;
use realestate\purchase\accounting\invoice\PurchaseInvoice;
use realestate\purchase\accounting\invoice\PurchaseInvoiceImport;
use realestate\sale\pay\Funding;

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
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'invoice_purchase.json');

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
    ],

    '3103' => [
        'description' => "Test document processing of purchase invoice document.",
        'help'        => "Create PurchaseInvoiceImport with purchase invoice json to test the DocumentProcess workflow to the final integration.
            - Test PurchaseInvoice creation
            - Test RecordingRule application (water invoice)
            - Test AccountingEntry and AccountingEntryLine creation
            - Test AccountingEntry validated
            - Test Funding creation
        ",
        'arrange'     => function () use ($providers) {
            $condo = Condominium::search(['name', '=', 'ACP Résidence Theo 4'])
                ->read(['suppliers_ids'])
                ->first(true);

            $documentType = DocumentType::search(['code', '=', 'invoice'])
                ->read(['id'])
                ->first();

            $documentSubtype = DocumentSubtype::search(['code', '=', 'advance_invoice'])
                ->read(['id'])
                ->first();

            $supplierType = SupplierType::search(['code', '=', 'water'])
                ->read(['id'])
                ->first();

            $purchaseInvoiceImport = PurchaseInvoiceImport::create([
                'name' => 'Test document processing of Purchase invoice document'
            ])
                ->read(['id'])
                ->first();

            $pdf_invoice_data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'invoice_purchase.pdf');
            PurchaseInvoiceImport::id($purchaseInvoiceImport['id'])->update(['data' => $pdf_invoice_data]);

            // bypass extraction for this test and force "document_json" field
            $json_invoice_data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'invoice_purchase.json');
            Document::search(['name', '=', 'Test document processing of Purchase invoice document'])
                ->update(['document_json' => $json_invoice_data]);

            $invoice_data = json_decode($json_invoice_data, true);

            $supplier = Supplier::search(['name', '=', $invoice_data['supplier']['name']])
                ->read(['id', 'supplier_types_ids'])
                ->first();

            // create suppliership if it doesn't exist yet
            if(!in_array($supplier['id'], $condo['suppliers_ids'])) {
                Suppliership::create([
                    'condo_id'      => $condo['id'],
                    'supplier_id'   => $supplier['id']
                ]);
            }

            // link supplier type to supplier if not done yet
            if(!in_array($supplierType['id'], $supplier['supplier_types_ids'])) {
                Supplier::id($supplier['id'])->update(['supplier_types_ids' => [$supplierType['id']]]);
            }

            return compact('condo', 'documentType', 'documentSubtype', 'supplierType', 'purchaseInvoiceImport');
        },
        'act'         => function ($data) use ($providers) {
            [
                'condo'                 => $condo,
                'documentType'          => $documentType,
                'documentSubtype'       => $documentSubtype,
                'supplierType'          => $supplierType,
                'purchaseInvoiceImport' => $purchaseInvoiceImport
            ]
                = $data;

            $documentProcess = DocumentProcess::search(['name', '=', 'Test document processing of Purchase invoice document'])
                ->read(['id'])
                ->first();

            // set condo_id on document (will assign it)
            DocumentProcess::id($documentProcess['id'])->update([
                'condo_id'              => $condo['id'],
                // 'document_type_id'      => $documentType['id'], #memo - is automatically set from PurchaseInvoiceImport
                'document_subtype_id'   => $documentSubtype['id'],
                'supplier_type_id'      => $supplierType['id']
            ]);

            // link document process to existing entities
            DocumentProcess::id($documentProcess['id'])->do('perform_matching');

            DocumentProcess::id($documentProcess['id'])->transition('complete');

            DocumentProcess::id($documentProcess['id'])->transition('validate');

            // create purchase invoice
            DocumentProcess::id($documentProcess['id'])->do('perform_drafting');

            // integrate purchase invoice
            DocumentProcess::id($documentProcess['id'])->transition('integrate');

            return $documentProcess['id'];
        },
        'assert'      => function ($document_process_id) use ($providers) {
            $documentProcess = DocumentProcess::id($document_process_id)
                ->read([
                    'status',
                    'document_invoice_id' => [
                        'invoice_number',
                        'invoice_lines_ids',
                        'funding_id' => [
                            'due_amount'
                        ],
                        'accounting_entries_ids' => [
                            'status',
                            'entry_lines_ids'
                        ]
                    ]
                ])
                ->first(true);

            return $documentProcess['status'] === 'integrated'
                && $documentProcess['document_invoice_id']['invoice_number']
                && !empty($documentProcess['document_invoice_id']['invoice_lines_ids'])
                && $documentProcess['document_invoice_id']['funding_id']
                && $documentProcess['document_invoice_id']['funding_id']['due_amount'] === -1115.00
                && !empty($documentProcess['document_invoice_id']['accounting_entries_ids'])
                && $documentProcess['document_invoice_id']['accounting_entries_ids'][0]['status'] === 'validated'
                && !empty($documentProcess['document_invoice_id']['accounting_entries_ids'][0]['entry_lines_ids']);
        },
        'rollback'    => function () use ($providers) {
            $purchaseInvoiceDocument = Document::search(['name', '=', 'Test document processing of Purchase invoice document'])
                ->read(['document_process_id' => ['document_invoice_id' => ['invoice_lines_ids', 'funding_id', 'accounting_entries_ids' => ['entry_lines_ids']]]])
                ->first();

            $accounting_entries_ids = [];
            $accounting_entries_lines_ids = [];
            foreach($purchaseInvoiceDocument['document_process_id']['document_invoice_id']['accounting_entries_ids'] as $id => $accounting_entry) {
                $accounting_entries_ids[] = $id;
                foreach($accounting_entry['entry_lines_ids'] as $line) {
                    $accounting_entries_lines_ids[] = $line['id'];
                }
            }

            /* @var \equal\orm\ObjectManager $orm */
            $orm = $providers['orm'];

            $orm->delete(Document::getType(), $purchaseInvoiceDocument['id'], true);
            $orm->delete(DocumentProcess::getType(), $purchaseInvoiceDocument['document_process_id']['id'], true);

            $orm->delete(PurchaseInvoice::getType(), $purchaseInvoiceDocument['document_process_id']['document_invoice_id']['id'], true);
            $orm->delete(PurchaseInvoiceLine::getType(), $purchaseInvoiceDocument['document_process_id']['document_invoice_id']['invoice_lines_ids'], true);

            $orm->delete(AccountingEntry::getType(), $accounting_entries_ids, true);
            $orm->delete(AccountingEntryLine::getType(), $accounting_entries_lines_ids, true);

            $orm->delete(Funding::getType(), $purchaseInvoiceDocument['document_process_id']['document_invoice_id']['funding_id']);
        }
    ]
];

