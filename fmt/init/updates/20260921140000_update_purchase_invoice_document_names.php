<?php

use documents\Document;
use realestate\purchase\accounting\invoice\PurchaseInvoice;

$purchaseInvoices = PurchaseInvoice::search([
        ['document_id', '<>', null]
    ])
    ->read([
        'name',
        'document_id',
        'emission_date',
        'suppliership_id' => ['code']
    ]);

foreach($purchaseInvoices as $purchaseInvoice) {
    if(!$purchaseInvoice['document_id']) {
        continue;
    }

    Document::id($purchaseInvoice['document_id'])
        ->update([
            'name' => $purchaseInvoice['name']
                . ' - ' . $purchaseInvoice['suppliership_id']['code']
                . ' - ' . date('Y-m-d', $purchaseInvoice['emission_date'])
        ]);
}
