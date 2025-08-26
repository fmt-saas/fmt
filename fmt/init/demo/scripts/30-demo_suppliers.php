<?php

use identity\Identity;
use purchase\supplier\Supplier;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();

// #todo
/* 
$identity = Identity::create([
    "id" => 1001,
    "supplier_id" => 1001,
    "type_id" => 3,
    "bank_account_iban" => "BE59001835397826",
    "bank_account_bic" => "GEBABEBB",
    "legal_name" => "ABC TECHNICS",
    "short_name" => "ABC",
    "has_vat" => true,
    "vat_number" => "BE0455160721",
    "registration_number" => "0455160721",
    "nationality" => "BE",
    "lang_id" => 2,
    "address_street" => "Avenue E. Lambrecht, 40",
    "address_city" => "Wemmel",
    "address_zip" => "1780",
    "address_country" => "BE",
    "email" => "info@abctechnics.be",
    "phone" => "+3224655210",
    "mobile" => null,
    "website" => null,
    "is_active" => true
    ])
    ->do('refresh_bank_accounts')
    ->first();

Supplier::create([
    "id" => 1001,
    "identity_id" => $identity['id'],
    "is_active" => true
    ])
    ->do('sync_from_identity');
*/

// [autres fournisseurs]

$orm->enableEvents($events);

