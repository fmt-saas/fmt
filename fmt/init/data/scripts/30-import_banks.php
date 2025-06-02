<?php

use finance\bank\Bank;
use identity\Identity;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();

Identity::create([
    "id" => 500,
    "type_id" => 3,
    "legal_name" => "Belfius Bank SA",
    "short_name" => "Belfius",
    "has_vat" => true,
    "vat_number" => "BE0403290916",
    "registration_number" => "0403290916",
    "nationality" => "BE",
    "lang_id" => 2,
    "address_street" => "Boulevard Pacheco 44",
    "address_city" => "Bruxelles",
    "address_zip" => "1000",
    "address_country" => "BE",
    "email" => "info@belfius.be",
    "phone" => "+3222221111",
    "bank_account_iban" => null,
    "bank_account_bic" => "GKCCBEBB",
    "is_active" => true,
]);

Bank::create([
    "id" => 500,
    "identity_id" => 500,
    "bic" => "GKCCBEBB",
    "is_active" => true,
]);

Identity::create([
    "id" => 501,
    "type_id" => 3,
    "legal_name" => "Argenta Spaarbank NV",
    "short_name" => "Argenta",
    "has_vat" => true,
    "vat_number" => "BE0404662595",
    "registration_number" => "0404662595",
    "nationality" => "BE",
    "lang_id" => 2,
    "address_street" => "Belgiëlei 49-53",
    "address_city" => "Anvers",
    "address_zip" => "2018",
    "address_country" => "BE",
    "email" => "info@argenta.be",
    "phone" => "+3232855111",
    "bank_account_iban" => null,
    "bank_account_bic" => "ARGO BE BB",
    "is_active" => true,
]);

Bank::create([
    "id" => 501,
    "identity_id" => 501,
    "bic" => "ARGOBEBB",
    "is_active" => true,
]);

Identity::create([
    "id" => 502,
    "type_id" => 3,
    "legal_name" => "Triodos Bank NV",
    "short_name" => "Triodos",
    "has_vat" => true,
    "vat_number" => "BE0458092810",
    "registration_number" => "0458092810",
    "nationality" => "BE",
    "lang_id" => 2,
    "address_street" => "Rue Haute 139/3",
    "address_city" => "Bruxelles",
    "address_zip" => "1000",
    "address_country" => "BE",
    "email" => "info@triodos.be",
    "phone" => "+3225482820",
    "bank_account_iban" => null,
    "bank_account_bic" => "TRIOBEBB",
    "is_active" => true,
]);

Bank::create([
    "id" => 502,
    "identity_id" => 502,
    "bic" => "TRIOBEBB",
    "is_active" => true,
]);

Identity::create([
    "id" => 503,
    "type_id" => 3,
    "legal_name" => "Fintro BNP Paribas Fortis",
    "short_name" => "Fintro",
    "has_vat" => true,
    "vat_number" => "BE0403085769",
    "registration_number" => "0403085769",
    "nationality" => "BE",
    "lang_id" => 2,
    "address_street" => "Montagne du Parc 3",
    "address_city" => "Bruxelles",
    "address_zip" => "1000",
    "address_country" => "BE",
    "email" => "info@fintro.be",
    "phone" => "+3224334334",
    "bank_account_iban" => null,
    "bank_account_bic" => "GEBABEBB",
    "is_active" => true,
]);

Bank::create([
    "id" => 503,
    "identity_id" => 503,
    "bic" => "GEBABEBB",
    "is_active" => true,
]);

Identity::create([
    "id" => 504,
    "type_id" => 3,
    "legal_name" => "Deutsche Bank AG",
    "short_name" => "Deutsche Bank",
    "has_vat" => true,
    "vat_number" => "BE0415362643",
    "registration_number" => "0415362643",
    "nationality" => "BE",
    "lang_id" => 2,
    "address_street" => "Avenue Marnix 13-15",
    "address_city" => "Bruxelles",
    "address_zip" => "1000",
    "address_country" => "BE",
    "email" => "info@db.com",
    "phone" => "+3222221011",
    "bank_account_iban" => null,
    "bank_account_bic" => "DEUTBEBE",
    "is_active" => true,
]);

Bank::create([
    "id" => 504,
    "identity_id" => 504,
    "bic" => "DEUTBEBE",
    "is_active" => true,
]);

Identity::create([
    "id" => 505,
    "type_id" => 3,
    "legal_name" => "Beobank NV/SA",
    "short_name" => "Beobank",
    "has_vat" => true,
    "vat_number" => "BE0404930578",
    "registration_number" => "0404930578",
    "nationality" => "BE",
    "lang_id" => 2,
    "address_street" => "Avenue de Tervuren 72",
    "address_city" => "Bruxelles",
    "address_zip" => "1040",
    "address_country" => "BE",
    "email" => "info@beobank.be",
    "phone" => "+3226222020",
    "bank_account_iban" => null,
    "bank_account_bic" => "CTBKBEBX",
    "is_active" => true,
]);

Bank::create([
    "id" => 505,
    "identity_id" => 505,
    "bic" => "CTBKBEBX",
    "is_active" => true,
]);

Identity::create([
    "id" => 506,
    "type_id" => 3,
    "legal_name" => "Keytrade Bank SA",
    "short_name" => "Keytrade",
    "has_vat" => true,
    "vat_number" => "BE0462566489",
    "registration_number" => "0462566489",
    "nationality" => "BE",
    "lang_id" => 2,
    "address_street" => "Boulevard du Souverain 100",
    "address_city" => "Bruxelles",
    "address_zip" => "1170",
    "address_country" => "BE",
    "email" => "info@keytradebank.be",
    "phone" => "+3226799010",
    "bank_account_iban" => null,
    "bank_account_bic" => "KEYTBEBB",
    "is_active" => true,
]);

Bank::create([
    "id" => 506,
    "identity_id" => 506,
    "bic" => "KEYTBEBB",
    "is_active" => true,
]);



$orm->enableEvents($events);

// sync values from Identities to Suppliers (Banks)
Bank::search()->do('sync_from_identity');