<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\import\DataImport;
use hr\employee\Employee;
use identity\Identity;
use purchase\supplier\Supplier;
use realestate\property\Condominium;
use realestate\property\PropertyLotNature;

[$params, $providers] = eQual::announce([
    'description'   => "Returns a JSON structure describing the import.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "Identifier of the targeted DataImport object.",
            'foreign_object'    => 'fmt\import\DataImport',
            'required'          => true
        ],
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context', 'orm']
]);

['context' => $context, 'orm' => $orm] = $providers;


$result = [
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'errors'    => 0,
    'processed' => 0,
    'logs'      => []
];


// fetch DataImport object
$dataImport = DataImport::id($params['id'])->read(['name', 'import_type'])->first();

if(!$dataImport) {
    throw new Exception("unknown_data_import", EQ_ERROR_UNKNOWN_OBJECT);
}

// fetch parsed JSON
$data = eQual::run('get', 'fmt_import_DataImport_parse', ['id' => $params['id']]);


if($dataImport['import_type'] == 'condominium_import') {
    /*
    if(preg_match_all('/\d+/', $dataImport['name'], $matches)) {
        $condo_code = $matches[0];
        $condominium = Condominium::id((int) $condo_code)->first();
        if(!$condominium) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown condominium_code {$condo_code} retrieved from file name: '" . $dataImport['name'] . "'";
        }
    }
    */

    // 1) map existing codes amongst sheets

    $map_owners_codes = [];
    foreach($data['Owners'] as $owner) {
        if(isset($owner['code'])) {
            $map_owners_codes[$owner['code']] = true;
        }
    }

    $map_ownerships_codes = [];
    foreach($data['Ownerships'] as $ownership) {
        if(isset($ownership['code'])) {
            $map_ownerships_codes[$ownership['code']] = true;
        }
    }

    $map_property_entrances_codes = [];
    foreach($data['Entrances'] as $property_entrance) {
        if(isset($property_entrance['code'])) {
            $map_property_entrances_codes[$property_entrance['code']] = true;
        }
    }

    $map_property_lots_codes = [];
    foreach($data['Lots'] as $property_lot) {
        if(isset($property_lot['code'])) {
            $map_property_lots_codes[$property_lot['code']] = true;
        }
    }

    $map_apportionment_keys_codes = [];
    foreach($data['Apport_keys'] as $apportionment_key) {
        if(isset($apportionment_key['code'])) {
            $map_apportionment_keys_codes[$apportionment_key['code']] = $apportionment_key;
        }
    }

    $map_suppliers_codes = [];
    foreach($data['Supplierships'] as $suppliership) {
        if(isset($suppliership['supplier_code'])) {
            $map_suppliers_codes[$suppliership['supplier_code']] = true;
        }
    }

    // 2) - check mandatory fields & cross-references consistency

    foreach($data['Condominium'] as $index => $condo) {
        if(isset($condo['fiscal_period']) && !in_array(strtolower($condo['fiscal_period']), ['quarterly', 'tertially', 'semi-annually', 'annually'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `fiscal_period` {$condo['fiscal_period']} in Condominium sheet at row " . ($index + 2);
        }
        if(!isset($condo['code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `code` in Condominium sheet at row " . ($index + 2);
        }
        if($index > 0) {
            ++$result['errors'];
            $result['logs'][] = "ERR - more than one Condominium found in Condominium sheet at row " . ($index + 2);
        }
        $duplicate = Condominium::search([
                ['code', 'ilike', $condo['code']]
            ])
            ->first();
        if($duplicate) {
            ++$result['errors'];
            $result['logs'][] = "ERR - existing Condominium found for `code` {$condo['code']} in Condominium sheet at row " . ($index + 2);
        }
        if(isset($condo['registration_number']) && strlen($condo['registration_number']) > 0) {
            $duplicate = Condominium::search([
                    ['registration_number', '=', $condo['registration_number']]
                ])
                ->first();
            if($duplicate) {
                ++$result['errors'];
                $result['logs'][] = "ERR - existing Condominium found for `registration_number` {$condo['registration_number']} in Condominium sheet at row " . ($index + 2);
            }
            $duplicate = Identity::search([
                    ['registration_number', '=', $condo['registration_number']]
                ])
                ->first();
            if($duplicate) {
                ++$result['errors'];
                $result['logs'][] = "ERR - existing Identity found for `registration_number` {$condo['registration_number']} in Condominium sheet at row " . ($index + 2);
            }
        }
        if(isset($condo['manager_code'])) {
            $managerEmployee = Employee::search(['id', '=', $condo['manager_code']])->first();
            if(!$managerEmployee) {
                ++$result['errors'];
                $result['logs'][] = "ERR - referenced manager employee with code {$condo['manager_code']} in Condominium sheet at row " . ($index + 2);
            }
        }
        if(isset($condo['accountant_code'])) {
            $accountantEmployee = Employee::search(['id', '=', $condo['accountant_code']])->first();
            if(!$accountantEmployee) {
                ++$result['errors'];
                $result['logs'][] = "ERR - referenced accountant employee with code {$condo['accountant_code']} in Condominium sheet at row " . ($index + 2);
            }
        }

    }

    $map_bank_accounts_primary = [];
    foreach($data['Bank_accounts'] as $index => $bank_account) {
        /*
        if(!isset($bank_account['code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `code` in Bank_accounts sheet at row " . ($index + 2);
        }
        */
        if($bank_account['is_primary']) {
            if(isset($map_bank_accounts_primary[$bank_account['type']])) {
                ++$result['errors'];
                $result['logs'][] = "ERR - duplicate `is_primary` in Bank_accounts sheet at row " . ($index + 2);
                continue;
            }
            $map_bank_accounts_primary[$bank_account['type']] = true;
        }
        if(!isset($bank_account['iban'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `iban` in Bank_accounts sheet at row " . ($index + 2);
        }
    }

    foreach($data['Owners'] as $index => $owner) {
        if(!isset($owner['code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `code` in Owner sheet at row " . ($index + 2);
        }

        // #todo - perform checks based on target schema constraints and fields types

        // allow letters (Unicode), space, apostrophe, hyphen
        $owner_firstname = trim($owner['firstname'] ?? '');
        if($owner_firstname !== '' && !preg_match('/^[\p{L}\'\- ]+$/u', $owner_firstname)) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid chars for `firstname` ({$owner['firstname']}) in Owner sheet at row " . ($index + 2);
        }

        if($owner_firstname !== '' && strlen($owner_firstname) < 2) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid length (<2) for `firstname` ({$owner['firstname']}) in Owner sheet at row " . ($index + 2);
        }

        // allow letters (Unicode), space, apostrophe, hyphen
        if(!preg_match('/^[\p{L}\'\- ]+$/u', $owner['lastname'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid chars for `lastname` ({$owner['lastname']}) in Owner sheet at row " . ($index + 2);
        }

        if(!preg_match('/^[a-z]{2}$/', $owner['lang'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `lang` ({$owner['lang']}) in Owner sheet at row " . ($index + 2);

        }

        if(!preg_match('/^[A-Z]{2}$/', $owner['country'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `country` ({$owner['country']}) in Owner sheet at row " . ($index + 2);

        }

        if(!preg_match('/^\+?[0-9]*$/', $owner['phone_1'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `phone_1` ({$owner['phone_1']}) in Owner sheet at row " . ($index + 2);

        }

        if(!preg_match('/^\+?[0-9]*$/', $owner['phone_2'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `phone_2` ({$owner['phone_2']}) in Owner sheet at row " . ($index + 2);

        }

        if(!preg_match('/^\+?[0-9]*$/', $owner['mobile_1'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `mobile_1` ({$owner['mobile_1']}) in Owner sheet at row " . ($index + 2);
        }

        if(!preg_match('/^\+?[0-9]*$/', $owner['mobile_2'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `mobile_2` ({$owner['mobile_2']}) in Owner sheet at row " . ($index + 2);
        }

    }

    foreach($data['Ownerships'] as $index => $ownership) {
        if(!isset($owner['code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `code` in Owner sheet at row " . ($index + 2);
        }
        if(!isset($ownership['owner_code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `owner_code` in Ownership sheet at row " . ($index + 2);
        }
        if(!isset($map_owners_codes[$ownership['owner_code']])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `owner_code` '" . $ownership['owner_code'] . "' in Ownership sheet at row " . ($index + 2);
        }
    }

    foreach($data['Ownerships_com_prefs'] as $index => $ownership_communication) {
        if(!isset($ownership_communication['ownership_code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `ownership_code` in Ownership_com sheet at row " . ($index + 2);
        }
        if(!isset($map_ownerships_codes[$ownership_communication['ownership_code']])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `ownership_code` '" . $ownership['ownership_code'] . "' in Ownership_com sheet at row " . ($index + 2);
        }
    }

    foreach($data['Entrances'] as $index => $owner) {
        if(!isset($owner['code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `code` in Entrances sheet at row " . ($index + 2);
        }
    }

    foreach($data['Lots'] as $index => $property_lot) {
        if(!isset($property_lot['code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `code` in `Lots` sheet at row " . ($index + 2);
        }
        if(!isset($property_lot['entrance_code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `entrance_code` in `Lots` sheet at row " . ($index + 2);
        }
        if(!isset($map_property_entrances_codes[$property_lot['entrance_code']])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `entrance_code` '" . $property_lot['entrance_code'] . "' in `Lots` sheet at row " . ($index + 2);
        }
        if(!isset($property_lot['ref'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `ref` in `Lots` sheet at row " . ($index + 2);
        }
        if(!isset($property_lot['nature'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `nature` in `Lots` sheet at row " . ($index + 2);
        }

        // check nature consistency

        // #todo - complete
        $nature = [
            'APPARTEMENT'   => 'apartment',
            'APARTMENT'     => 'apartment',
            'PARKING'       => 'parking',
            'GARAGE'        => 'garage',
            'ROOM'          => 'room'
            ][$property_lot['nature']] ?? strtolower($property_lot['nature']);

        $propertyLotNature = PropertyLotNature::search(['code', '=', $nature])
            ->first();

        if(!$propertyLotNature) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown code ({$property_lot['nature']}) for `nature` in `Lots` sheet at row " . ($index + 2);
        }
    }

    foreach($data['Ownerships_history'] as $index => $ownership_history) {
        if(!isset($ownership_history['lot_code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing lot_code in Ownerships_history sheet at row " . ($index + 2);
        }
        if(!isset($map_property_lots_codes[$ownership_history['lot_code']])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `lot_code` '" . $ownership_history['lot_code'] . "' in Ownerships_history sheet at row " . ($index + 2);
        }
        if(!isset($ownership_history['ownership_code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `ownership_code` in Ownerships_history sheet at row " . ($index + 2);
        }
        if(!isset($map_ownerships_codes[$ownership_history['ownership_code']])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `ownership_code` '" . $ownership_history['ownership_code'] . "' in Ownerships_history sheet at row " . ($index + 2);
        }
        if(!isset($ownership_history['date_from'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `date_from` in Ownerships_history sheet at row " . ($index + 2);
        }

    }

    foreach($data['Apport_keys'] as $index => $apportionment_key) {
        if(!isset($apportionment_key['code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `code` in Apport_keys sheet at row " . ($index + 2);
        }
        if(!isset($apportionment_key['total_shares'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `total_shares` in Apport_keys sheet at row " . ($index + 2);
        }
    }

    foreach($data['Apport_shares'] as $index => $apportionment_share) {
        if(!isset($apportionment_share['apport_key_code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `apport_key_code` in Apport_shares sheet at row " . ($index + 2);
        }
        if(!isset($apportionment_share['lot_code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `lot_code` in Apport_shares sheet at row " . ($index + 2);
        }
        if(!isset($apportionment_share['lot_shares'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `lot_shares` in Apport_shares sheet at row " . ($index + 2);
        }
        if(!isset($map_apportionment_keys_codes[$apportionment_share['apport_key_code']])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `apport_key_code` '" . $apportionment_share['apport_key_code'] . "' in Apport_shares sheet at row " . ($index + 2);
        }
        if(!isset($map_property_lots_codes[$apportionment_share['lot_code']])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `lot_code` '" . $apportionment_share['lot_code'] . "' in Apport_shares sheet at row " . ($index + 2);
        }
    }

    foreach($data['Supplierships'] as $index => $suppliership) {
        if(!isset($suppliership['supplier_code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `supplier_code` in Supplierships sheet at row " . ($index + 2);
        }

        $supplier = null;
        if(str_starts_with($suppliership['code'], 'uuid-')) {
            $uuid = substr($suppliership['code'], 5);
            $supplier = Supplier::search(['uuid', '=', $uuid])->first();

            // #todo - fetch the missing supplier from GLOBAL instance
        }
        else {
            $supplier = Supplier::id((int) $suppliership['supplier_code'])->first();
        }

        if(!$supplier) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `supplier_code` '" . $suppliership['supplier_code'] . "' in suppliership sheet at row " . ($index + 2);
        }
    }

    // 3) - check database consistency/constraints

    foreach($data['Condominium'] as $index => $condo) {
        $condominium = null;
        if(isset($condo['registration_number'])) {
            $condominium = Condominium::search(['registration_number', '=', $condo['registration_number']])->first();
            if($condominium) {
                ++$result['errors'];
                $result['logs'][] = "ERR - a condominium with same registration number already exists [{$condo['registration_number']}]";
            }
        }
        if(!$condominium && isset($condo['cadastral_number'])) {
            $condominium = Condominium::search(['cadastral_number', '=', $condo['cadastral_number']])->first();
            if($condominium) {
                ++$result['errors'];
                $result['logs'][] = "ERR - a condominium with same cadastral number already exists [{$condo['cadastral_number']}]";
            }
        }
        if(!$condominium && isset($condo['vat_number'])) {
            $condominium = Condominium::search(['vat_number', '=', $condo['vat_number']])->first();
            if($condominium) {
                ++$result['errors'];
                $result['logs'][] = "ERR - a condominium with same VAT number already exists [{$condo['vat_number']}]";
            }
        }
    }


    $map_apport_shares_totals = [];
    foreach($data['Apport_shares'] as $index => $apportionment_share) {
        if(!isset($map_share_totals[$apportionment_share['apport_key_code']])) {
            $map_share_totals[$apportionment_share['apport_key_code']] = 0;
        }
        $map_share_totals[$apportionment_share['apport_key_code']] += $apportionment_share['lot_shares'];
    }

    foreach($data['Apport_keys'] as $index => $apportionment_key) {
        $total = $map_share_totals[$apportionment_key['code']] ?? 0;
        if($apportionment_key['total_shares'] !== $total) {
            ++$result['errors'];
            $result['logs'][] = "ERR - `total_shares` for apportionment key '" . $apportionment_key['code'] . "' ({$apportionment_key['total_shares']}) does not match total of shares ({$total})";
        }
    }

    foreach($map_apport_shares_totals as $apport_key_code => $total) {
        $apport_key = $map_apportionment_keys_codes[$apport_key_code];
        if($apport_key['total_shares'] != $total) {
            ++$result['errors'];
            $result['logs'][] = "ERR - `total_shares` for apportionment key '" . $apport_key_code . "' ({$apport_key['total_shares']}) does not match total of shares ({$total})";
        }
    }
}
elseif($dataImport['import_type'] == 'suppliers_import') {
    $suppliers_data = current($data);
    foreach($suppliers_data as $index => $supplier) {
        if(!$supplier['legal_name']) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing mandatory `legal_name` in suppliers sheet at row " . ($index + 2);
        }
        if(!$supplier['registration_number']) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing mandatory `registration_number` in suppliers sheet at row " . ($index + 2);
        }
        if(!$supplier['street']) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing mandatory `street` in suppliers sheet at row " . ($index + 2);
        }
        if(!$supplier['zip']) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing mandatory `zip` in suppliers sheet at row " . ($index + 2);
        }
        if(!$supplier['city']) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing mandatory `city` in suppliers sheet at row " . ($index + 2);
        }
        if(!$supplier['country']) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing mandatory `country` in suppliers sheet at row " . ($index + 2);
        }

        // attempt to find existing identity by registration number
        $identity = Identity::search(['registration_number', '=', $supplier['registration_number']])->first();

        if($identity) {
            ++$result['errors'];
            $result['logs'][] = "ERR - duplicated `{$supplier['registration_number']}` already assigned to identity id {$identity['id']} in suppliers sheet at row " . ($index + 2);
        }

    }
}
elseif($dataImport['import_type'] == 'banks_import') {
    $banks_data = current($data);
    foreach($banks_data as $index => $bank) {
        if(!$bank['legal_name']) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing mandatory `legal_name` in banks sheet at row " . ($index + 2);
        }
        if(!$bank['registration_number']) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing mandatory `registration_number` in banks sheet at row " . ($index + 2);
        }
        if(!$bank['bic']) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing mandatory `bic` in banks sheet at row " . ($index + 2);
        }
    }
}


DataImport::id($params['id'])
    ->update([
        'logs'      => json_encode($result['logs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'status'    => ($result['errors'] > 0) ? 'failing' : 'ready'
    ]);

$context->httpResponse()
        ->body(['result' => $result])
        ->send();
