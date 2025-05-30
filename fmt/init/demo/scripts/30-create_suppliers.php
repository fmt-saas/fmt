<?php

use finance\bank\BankAccount;
use identity\Identity;
use purchase\supplier\Supplier;


['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


$identity = Identity::create([
        "name" => "Immo Services",
        "legal_name" => "Immo Services",
        "type_id" => 3,
        "type" => "CO",
        "description" => null,
        "bank_account_iban" => "BE08457219881558",
        "bank_account_bic" => "GKCCBEBB",
        "has_vat" => true,
        "vat_number" => "BE0755885564",
        "registration_number" => null,
        "nationality" => "BE",
        "has_parent" => false,
        "parent_id" => null,
        "lang_id" => 2,
        "address_street" => "Avenue des platanes, 865",
        "address_dispatch" => null,
        "address_city" => "Forest",
        "address_zip" => "1190",
        "address_state" => null,
        "address_country" => "BE",
        "email" => "immo.services@example.com",
        "email_alt" => null,
        "phone" => "+32487654312",
        "is_active" => true
    ])
    ->first();

$supplier = Supplier::create([
        "id" => 1,
        "owner_type" => "full",
        "identity_id" => $identity['id']
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
Supplier::id($supplier['id'])->do('sync_from_identity');