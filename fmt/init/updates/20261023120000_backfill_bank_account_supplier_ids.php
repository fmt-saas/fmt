<?php

use finance\bank\BankAccount;
use purchase\supplier\Supplier;

$suppliers = Supplier::search([
        ['identity_id', '<>', null]
    ])
    ->read(['identity_id']);

foreach($suppliers as $supplier_id => $supplier) {
    BankAccount::search([
            ['owner_identity_id', '=', $supplier['identity_id']],
            ['supplier_id', 'is', null]
        ])
        ->write(['supplier_id' => $supplier_id]);
}

BankAccount::search()
    ->update(['bank_id' => null]);


Supplier::search(['identity_id', 'is', null])
    ->delete(true);
