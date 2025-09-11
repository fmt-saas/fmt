<?php

use finance\bank\CondominiumBankAccount;
use identity\Identity;
use realestate\property\Condominium;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


// ACP Résidence Theo 4

$identity = Identity::create([
        "type_id" => 3,
        "type" => "CO",
        "description" => null,
        "bank_account_iban" => null,
        "bank_account_bic" => null,
        "legal_name" => "ACP Résidence Theo 4",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue Theo Van Pe, 4",
        "address_city" => "Ixelles",
        "address_zip" => "1050",
        "address_country" => "BE"
    ])
    ->first();

$condominium = Condominium::create([
        "total_shares" => 1000,
        "construction_permit_date" => strtotime("1978-12-15T00:00:00+00:00"),
        "construction_start_date" => strtotime("1979-01-01T00:00:00+00:00"),
        "construction_compliance_date" => strtotime("1981-02-21T00:00:00+00:00"),
        "construction_completion_date" => strtotime("1980-10-13T00:00:00+00:00"),
        "condo_creation_date" => strtotime("2024-01-01T00:00:00+00:00"),
        "condo_regulations_date" => strtotime("2024-01-01T00:00:00+00:00"),
        "cadastral_number" => "12345A0567/00D000",
        "fiscal_year_start" => strtotime("2024-01-01T00:00:00+00:00"),
        "fiscal_year_end" => strtotime("2024-12-31T00:00:00+00:00"),
        "fiscal_period_frequency" => "Q",
        "account_chart_id" => 2,
        "current_fiscal_year_id" => null,
        "identity_id" => $identity['id']
    ])
    ->first();


CondominiumBankAccount::create([
        'condo_id'          => $condominium['id'],
        'owner_identity_id' => $identity['id'],
        'description'       => "Compte à vue",
        'bank_account_type' => 'bank_current',
        'bank_account_iban' => "BE04233241973931",
        'is_primary'        => true
    ]);

CondominiumBankAccount::create([
        'condo_id'          => $condominium['id'],
        'owner_identity_id' => $identity['id'],
        'description'       => "Compte épargne",
        'bank_account_type' => 'bank_savings',
        'bank_account_iban' => "BE04456595434922"
    ]);


// ACP Forge 43

$identity = Identity::create([
        "type_id" => 3,
        "type" => "CO",
        "description" => null,
        "bank_account_iban" => null,
        "bank_account_bic" => null,
        "legal_name" => "ACP Forge 43",
        "nationality" => "BE",
        "lang_id" => 2,
        "address_street" => "Rue de la Forge, 43",
        "address_city" => "Woluwe-Saint-Lambert",
        "address_zip" => "1200",
        "address_country" => "BE"
    ])
    ->first();


$condominium = Condominium::create([
        "total_shares" => 1000,
        "condo_creation_date" => strtotime("2001-02-13T00:00:00+00:00"),
        "condo_regulations_date" => strtotime("2015-03-29T00:00:00+00:00"),
        "cadastral_number" => "67890B0123/02F000",
        "fiscal_year_start" => strtotime("2024-01-01T00:00:00+00:00"),
        "fiscal_year_end" => null,
        "fiscal_period_frequency" => "Q",
        "account_chart_id" => 3,
        "current_fiscal_year_id" => null,
        "identity_id" => $identity['id']
    ])
    ->first();

CondominiumBankAccount::create([
        'condo_id'          => $condominium['id'],
        'owner_identity_id' => $identity['id'],
        'description'       => "Compte à vue",
        'bank_account_type' => 'bank_current',
        'bank_account_iban' => "BE02068937205640",
        'is_primary'        => true
    ]);

CondominiumBankAccount::create([
        'condo_id'          => $condominium['id'],
        'owner_identity_id' => $identity['id'],
        'description'       => "Compte épargne",
        'bank_account_type' => 'bank_savings',
        'bank_account_iban' => "BE05373234451279"
    ]);



$orm->enableEvents($events);

// sync values from Identities to Suppliers
Condominium::search()->do('sync_from_identity');