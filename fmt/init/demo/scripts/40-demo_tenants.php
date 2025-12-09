<?php

use identity\Identity;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\property\Tenant;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();

// jacques bonprez
$identity = Identity::create([
        "type_id" => 1,
        "type" => "IN",
        "description" => null,
        "bank_account_iban" => "BE97429842933749",
        "bank_account_bic" => "KREDBEBB",
        "signature" => null,
        "legal_name" => null,
        "short_name" => null,
        "has_vat" => false,
        "vat_number" => null,
        "registration_number" => null,
        "citizen_identification" => "62.12.01-076.99",
        "nationality" => "BE",
        "has_parent" => false,
        "parent_id" => null,
        "firstname" => "Jacques",
        "lastname" => "BONPREZ",
        "gender" => "M",
        "title" => "Mr",
        "date_of_birth" => strtotime("1962-12-01T00:00:00+00:00"),
        "lang_id" => 2,
        "address_street" => "Rue des Hêtres, 65",
        "address_dispatch" => null,
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_state" => null,
        "address_country" => "BE",
        "email" => "jacquesbonprez@example.com",
        "email_alt" => null,
        "phone" => null,
        "is_active" => true
    ])
    ->first();

Tenant::create([
    "id"=> 1,
    "condo_id" => 1,
    "property_lot_id" => 1,
    "tenancy_id" => 1,
    "identity_id"  => $identity['id'],
]);

// suzanne bonprez
$identity = Identity::create([
        "type_id" => 1,
        "type" => "IN",
        "description" => null,
        "bank_account_iban" => null,
        "bank_account_bic" => null,
        "signature" => null,
        "legal_name" => null,
        "short_name" => null,
        "has_vat" => false,
        "vat_number" => null,
        "registration_number" => null,
        "citizen_identification" => "63.12.02-034.47",
        "nationality" => "BE",
        "has_parent" => false,
        "parent_id" => null,
        "firstname" => "Suzanne",
        "lastname" => "BONPREZ",
        "gender" => "F",
        "title" => "Mrs",
        "date_of_birth" => strtotime("1963-12-02T00:00:00+00:00"),
        "lang_id" => 2,
        "address_street" => null,
        "address_dispatch" => null,
        "address_city" => null,
        "address_zip" => null,
        "address_state" => null,
        "address_country" => "BE",
        "email" => "suzannebonprez@example.com",
        "email_alt" => null,
        "phone" => null,
        "is_active" => true
    ])
    ->first();

Tenant::create([
    "id"=> 2,
    "condo_id" => 1,
    "property_lot_id" => 1,
    "tenancy_id" => 1,
    "identity_id"  => $identity['id'],
]);


$orm->enableEvents($events);

// sync values from Identities to Owners
Tenant::search()->do('sync_from_identity');