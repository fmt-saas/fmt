<?php

use finance\bank\BankAccount;
use identity\Identity;
use purchase\supplier\Supplier;
use realestate\management\ManagingAgent;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


$managingAgent = ManagingAgent::create([
        "id"                => 1,
        "supplier_type_id"  => 1,
        "identity_id"       => 1
    ])
    ->first();

BankAccount::create([
        'owner_identity_id' => $identity['id'],
        'description'       => "Principal",
        'bank_account_iban' => "BE08457219881558",
        'bank_account_bic'  => "GKCCBEBB",
        'is_primary'        => true
    ]);

$orm->enableEvents($events);

// sync values from Identities to Suppliers
Supplier::id($managingAgent['id'])->do('sync_from_identity');