<?php

use finance\bank\BankAccount;
use realestate\ownership\Owner;

$owners = Owner::search([
        ['ownership_id', 'is not', null],
        ['bank_account_iban', 'is not', null]
    ])
    ->read(['ownership_id', 'bank_account_iban']);

$ownership_ids_by_iban = [];
foreach($owners as $owner) {
    $iban = $owner['bank_account_iban'];
    if(!$iban || isset($ownership_ids_by_iban[$iban])) {
        continue;
    }

    $ownership_ids_by_iban[$iban] = $owner['ownership_id'];
}

$bank_accounts = BankAccount::search([
        ['ownership_id', 'is', null],
        ['owner_identity_id', 'is not', null],
        ['bank_account_iban', 'is not', null]
    ])
    ->read(['bank_account_iban']);

$bank_account_ids_by_ownership_id = [];
foreach($bank_accounts as $bank_account_id => $bank_account) {
    $iban = $bank_account['bank_account_iban'];
    $ownership_id = $ownership_ids_by_iban[$iban] ?? null;
    if(!$ownership_id) {
        continue;
    }

    $bank_account_ids_by_ownership_id[$ownership_id][] = $bank_account_id;
}

foreach($bank_account_ids_by_ownership_id as $ownership_id => $bank_account_ids) {
    BankAccount::ids($bank_account_ids)
        ->write(['ownership_id' => $ownership_id]);
}
