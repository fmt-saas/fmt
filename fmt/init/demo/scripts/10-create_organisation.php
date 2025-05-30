<?php

use finance\bank\BankAccount;
use identity\Identity;
use identity\Organisation;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


// Main organisation

$identity = Identity::create([
        "type_id" => 3,
        "type" => "CO",
        "legal_name" => "Perfect Syndic SRL",
        "short_name" => "Perfect Syndic",
        "has_parent" => false,
        "bank_account_iban" => null,
        "bank_account_bic" => null,
        "nationality" => "BE",
        "lang_id" => 2,
		"address_street" => "Rue des Éloges, 27",
		"address_city" => "Watermael-Boitsfort",
		"address_zip" => "1170",
        "address_country" => "BE",
        "has_vat" => true,
        "vat_number" => "BE0123231324"
    ])
    ->first();

Organisation::create([
        "identity_id" => $identity['id']
    ])
    ->first();

BankAccount::create([
        "owner_identity_id" => $identity['id'],
        "description"   =>  "Compte à vue",
        "bank_account_iban" =>  "BE23510349013565"
    ]);

$orm->enableEvents($events);

// sync values from Identities to Suppliers
Organisation::search()->do('sync_from_identity');