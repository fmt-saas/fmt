<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\bank\BankAccount;
use identity\Address;
use identity\Identity;
use purchase\supplier\Supplier;

$supplierTwoStepRegistrationNumber = 'TESTSUPPLIER1001';
$supplierTwoStepIban = 'BE85999999000001';

$cleanupSupplierTwoStepTest = function() use($supplierTwoStepRegistrationNumber, $supplierTwoStepIban) {
    $registration_number = $supplierTwoStepRegistrationNumber;
    $bank_account_iban = $supplierTwoStepIban;

    $identities_ids = Identity::search(['registration_number', '=', $registration_number])->ids();

    if(count($identities_ids)) {
        Address::search(['owner_identity_id', 'in', $identities_ids])->delete(true);
        BankAccount::search(['owner_identity_id', 'in', $identities_ids])->update(['state' => 'archive']);
    }

    BankAccount::search(['bank_account_iban', '=', $bank_account_iban])->update(['state' => 'archive']);
    Supplier::search(['registration_number', '=', $registration_number])->delete(true);
    Identity::search(['registration_number', '=', $registration_number])->delete(true);
};

$tests = [
    '1001' => [
        'description'       => 'Create supplier identity lists in two steps.',
        'help'              => 'Create a draft supplier, instantiate it in a second update, and assert that the related Identity has primary Address and BankAccount rows.',
        'return'            => ['array'],
        'arrange'           => function() use($cleanupSupplierTwoStepTest, $supplierTwoStepRegistrationNumber, $supplierTwoStepIban) {
            $cleanupSupplierTwoStepTest();

            $supplier = Supplier::create([
                    'state' => 'draft'
                ])
                ->read(['id'])
                ->first();

            return $supplier['id'];
        },
        'act'               => function($supplier_id) use($supplierTwoStepIban) {
            Supplier::id($supplier_id)->update([
                'state'               => 'instance',
                'source'              => 'manual',
                'type_id'             => 3,
                'legal_name'          => 'Two-step Supplier Test',
                'registration_number' => 'TESTSUPPLIER1001',
                'bank_account_iban'   => $supplierTwoStepIban,
                'address_street'      => 'Test Street 1',
                'address_zip'         => '1000',
                'address_city'        => 'Brussels',
                'address_country'     => 'BE'
            ]);

            $supplier = Supplier::id($supplier_id)
                ->read([
                    'id',
                    'identity_id',
                    'addresses_ids' => ['address_street', 'address_zip', 'address_city', 'address_country'],
                    'bank_accounts_ids' => ['bank_account_iban', 'supplier_id']
                ])
                ->first(true);

            $identity_id = $supplier['identity_id'] ?? null;

            return [
                'supplier_id'       => $supplier_id,
                'identity_id'       => $identity_id,
                'addresses_ids'     => $supplier['addresses_ids'] ?? [],
                'bank_accounts_ids' => $supplier['bank_accounts_ids'] ?? []
            ];
        },
        'assert'            => function($result) use($supplierTwoStepIban) {
            if(!$result['identity_id']) {
                return false;
            }

            if(count($result['addresses_ids']) < 1 || count($result['bank_accounts_ids']) < 1) {
                return false;
            }

            $has_address = false;
            foreach($result['addresses_ids'] as $address) {
                if($address['address_street'] === 'Test Street 1'
                    && (string) $address['address_zip'] === '1000'
                    && $address['address_city'] === 'Brussels'
                    && $address['address_country'] === 'BE') {
                    $has_address = true;
                    break;
                }
            }

            $has_bank_account = false;
            foreach($result['bank_accounts_ids'] as $bank_account) {
                if($bank_account['bank_account_iban'] === $supplierTwoStepIban
                    && (int) $bank_account['supplier_id'] === (int) $result['supplier_id']) {
                    $has_bank_account = true;
                    break;
                }
            }

            return $has_address && $has_bank_account;
        },
        'rollback'          => function() use($cleanupSupplierTwoStepTest) {
            $cleanupSupplierTwoStepTest();
        }
    ]
];
