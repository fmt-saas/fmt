<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\import\DataImport;
use hr\employee\Employee;
use identity\Identity;
use identity\IdentityType;
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
$dataImport = DataImport::id($params['id'])->read(['name', 'import_type', 'condo_id'])->first();

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
    $map_owners_has_email = [];
    foreach($data['Owners'] as $owner) {
        if(isset($owner['code'])) {
            $map_owners_codes[$owner['code']] = true;
            $map_owners_has_email[$owner['code']] =
                (isset($owner['email_1']) && trim((string) $owner['email_1']) !== '')
                || (isset($owner['email_2']) && trim((string) $owner['email_2']) !== '');
        }
    }

    $map_external_representatives_has_email = [];
    foreach($data['External_representatives'] ?? [] as $external_representative) {
        if(isset($external_representative['code'])) {
            $map_external_representatives_has_email[$external_representative['code']] =
                (isset($external_representative['email_1']) && trim((string) $external_representative['email_1']) !== '')
                || (isset($external_representative['email_2']) && trim((string) $external_representative['email_2']) !== '');
        }
    }

    $map_ownerships_codes = [];
    $map_ownerships_owners_codes = [];
    $map_ownerships_representative_owner_code = [];
    $map_ownerships_external_representative_code = [];
    foreach($data['Ownerships'] as $ownership) {
        if(isset($ownership['code'])) {
            $map_ownerships_codes[$ownership['code']] = true;
            if(isset($ownership['owner_code'])) {
                $map_ownerships_owners_codes[$ownership['code']][] = $ownership['owner_code'];
            }
            if(isset($ownership['representative_owner_code']) && trim((string) $ownership['representative_owner_code']) !== '') {
                $map_ownerships_representative_owner_code[$ownership['code']] = $ownership['representative_owner_code'];
            }
            if(isset($ownership['external_representative_code']) && trim((string) $ownership['external_representative_code']) !== '') {
                $map_ownerships_external_representative_code[$ownership['code']] = $ownership['external_representative_code'];
            }
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
                $result['logs'][] = "ERR - unknown referenced manager employee with code {$condo['manager_code']} in Condominium sheet at row " . ($index + 2);
            }
        }
        if(isset($condo['accountant_code'])) {
            $accountantEmployee = Employee::search(['id', '=', $condo['accountant_code']])->first();
            if(!$accountantEmployee) {
                ++$result['errors'];
                $result['logs'][] = "ERR - unknown referenced accountant employee with code {$condo['accountant_code']} in Condominium sheet at row " . ($index + 2);
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

        if(strlen($owner['email_1']) > 0 && !preg_match('/^([_a-z0-9-\.]+)(\+([_a-z0-9]+))?@(([a-z0-9-]+\.)*)([a-z0-9-]{1,63})(\.[a-z-]{2,24})$/i', $owner['email_1'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `email_1` ({$owner['email_1']}) in Owner sheet at row " . ($index + 2);

        }

        if(strlen($owner['email_2']) > 0 && !preg_match('/^([_a-z0-9-\.]+)(\+([_a-z0-9]+))?@(([a-z0-9-]+\.)*)([a-z0-9-]{1,63})(\.[a-z-]{2,24})$/i', $owner['email_2'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `email_2` ({$owner['email_2']}) in Owner sheet at row " . ($index + 2);

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

    $ownerships_shares = [];

    foreach($data['Ownerships'] as $index => $ownership) {
        $row_index = $index + 2;

        if(!isset($ownership['code']) || $ownership['code'] === '') {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `code` in Ownership sheet at row " . $row_index;
            continue;
        }

        if(!isset($ownership['owner_code']) || $ownership['owner_code'] === '') {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `owner_code` in Ownership sheet at row " . $row_index;
        }
        elseif(!isset($map_owners_codes[$ownership['owner_code']])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `owner_code` '" . $ownership['owner_code'] . "' in Ownership sheet at row " . $row_index;
        }

        $code = $ownership['code'];

        if(!isset($ownerships_shares[$code])) {
            $ownerships_shares[$code] = [
                'rows'                 => [],
                'shares_full_property' => 0.0,
                'shares_bare_property' => 0.0,
                'shares_usufruct'      => 0.0,
                'shares_total'         => (float) $ownership['shares_total']
            ];
        }

        $ownerships_shares[$code]['rows'][] = $row_index;

        foreach(['shares_full_property', 'shares_bare_property', 'shares_usufruct'] as $field) {
            if(isset($ownership[$field]) && $ownership[$field] !== '') {
                if(!is_numeric($ownership[$field])) {
                    ++$result['errors'];
                    $result['logs'][] = "ERR - invalid numeric value for `$field` in Ownership sheet at row " . $row_index;
                }
                else {
                    $ownerships_shares[$code][$field] += (float) $ownership[$field];
                }
            }
        }
    }

    foreach($ownerships_shares as $code => $stats) {
        $rows = implode(', ', $stats['rows']);

        $shares_full_property = $stats['shares_full_property'] ?? 0;
        $shares_bare_property = $stats['shares_bare_property'] ?? 0;
        $shares_usufruct      = $stats['shares_usufruct'] ?? 0;
        $shares_total         = $stats['shares_total'] ?? 0;

        if(abs($shares_bare_property - $shares_usufruct) >= 0.01) {
            ++$result['errors'];
            $result['logs'][] =
                "ERR - invalid ownership shares for code `$code`: " .
                "`shares_bare_property` sum ($shares_bare_property) must equal " .
                "`shares_usufruct` sum ($shares_usufruct) at rows: " . $rows;
        }

        if(abs(($shares_bare_property + $shares_full_property) - $shares_total) >= 0.01) {
            ++$result['errors'];
            $result['logs'][] =
                "ERR - invalid ownership shares for code `$code`: " .
                "`shares_bare_property` sum ($shares_bare_property) + " .
                "`shares_full_property` sum ($shares_full_property) must equal " .
                "`shares_total` sum ($shares_total) at rows: " . $rows;
        }
    }

    foreach($data['Ownerships_com_prefs'] as $index => $ownership_communication) {
        $ownership_code = $ownership_communication['ownership_code'] ?? null;

        if(!isset($ownership_communication['ownership_code'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `ownership_code` in Ownership_com sheet at row " . ($index + 2);
        }
        elseif(!isset($map_ownerships_codes[$ownership_code])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown `ownership_code` '" . $ownership_code . "' in Ownership_com sheet at row " . ($index + 2);
        }
        if(!isset($ownership_communication['ownership_title']) || strlen($ownership_communication['ownership_title']) <= 0) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `ownership_title` '" . $ownership_code . "' in Ownership_com sheet at row " . ($index + 2);
        }

        $preferences = ['general_assembly_call', 'general_assembly_minutes', 'expense_statement', 'fund_request', 'technical_communication'];
        foreach($preferences as $preference) {
            $has_email_channel = false;
            foreach(explode(',', (string) ($ownership_communication[$preference] ?? '')) as $channel) {
                if(strtolower(trim($channel)) === 'email') {
                    $has_email_channel = true;
                    break;
                }
            }

            if(!$has_email_channel) {
                continue;
            }

            $has_email = false;
            if(isset($map_ownerships_external_representative_code[$ownership_code])) {
                $external_representative_code = $map_ownerships_external_representative_code[$ownership_code];
                $has_email = $map_external_representatives_has_email[$external_representative_code] ?? false;
            }
            elseif(isset($map_ownerships_representative_owner_code[$ownership_code])) {
                $owner_code = $map_ownerships_representative_owner_code[$ownership_code];
                $has_email = $map_owners_has_email[$owner_code] ?? false;
            }
            else {
                foreach($map_ownerships_owners_codes[$ownership_code] ?? [] as $owner_code) {
                    if($map_owners_has_email[$owner_code] ?? false) {
                        $has_email = true;
                        break;
                    }
                }
            }

            if(!$has_email) {
                ++$result['errors'];
                $result['logs'][] = "ERR - missing `email_1` or `email_2` for ownership_code '" . $ownership_code . "' while `email` is used in `{$preference}` in Ownership_com sheet at row " . ($index + 2);
            }
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
        if(str_starts_with($suppliership['supplier_code'], 'uuid-')) {
            $uuid = substr($suppliership['supplier_code'], 5);
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
elseif($dataImport['import_type'] == 'ownership_import') {
    $ownerships_data = current($data) ?: [];
    $condo_id = $dataImport['condo_id']['id'] ?? ($dataImport['condo_id'] ?? null);
    $identity_rows = [];
    $representative_rows = [];
    $shares = [
        'rows'                 => [],
        'shares_full_property' => 0.0,
        'shares_bare_property' => 0.0,
        'shares_usufruct'      => 0.0,
        'shares_total'         => null,
        'has_share_values'     => false
    ];

    if(!$condo_id) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing mandatory `condo_id` for ownership import";
    }

    if(count($ownerships_data) <= 0) {
        ++$result['errors'];
        $result['logs'][] = "ERR - empty ownership import sheet";
    }

    foreach($ownerships_data as $index => $ownership_row) {
        $row_index = $index + 2;
        $type_code = strtoupper(trim((string) ($ownership_row['type'] ?? '')));
        $type = null;

        if($type_code === '') {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `type` in ownership import sheet at row " . $row_index;
        }
        else {
            $type = IdentityType::search(['code', '=', $type_code])->read(['id'])->first();
            if(!$type) {
                ++$result['errors'];
                $result['logs'][] = "ERR - unknown `type` ({$ownership_row['type']}) in ownership import sheet at row " . $row_index;
            }
        }

        $owner_firstname = trim($ownership_row['firstname'] ?? '');
        if($owner_firstname !== '' && !preg_match('/^[\p{L}\'\- ]+$/u', $owner_firstname)) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid chars for `firstname` ({$ownership_row['firstname']}) in ownership import sheet at row " . $row_index;
        }

        if($owner_firstname !== '' && strlen($owner_firstname) < 2) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid length (<2) for `firstname` ({$ownership_row['firstname']}) in ownership import sheet at row " . $row_index;
        }

        $owner_lastname = trim($ownership_row['lastname'] ?? '');
        if($owner_lastname === '') {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `lastname` in ownership import sheet at row " . $row_index;
        }
        elseif($type_code === 'IN' && !preg_match('/^[\p{L}\'\- ]+$/u', $owner_lastname)) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid chars for `lastname` ({$ownership_row['lastname']}) in ownership import sheet at row " . $row_index;
        }

        if(!empty($ownership_row['title']) && !in_array($ownership_row['title'], ['Madame', 'Mme', 'Monsieur', 'M', 'Mr', 'Mrs', 'Ms', 'Dr', 'Pr'], true)) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `title` ({$ownership_row['title']}) in ownership import sheet at row " . $row_index;
        }

        $identity = null;

        if(!empty($ownership_row['citizen_identification'])) {
            $identity = Identity::search(['citizen_identification', '=', $ownership_row['citizen_identification']])->read(['id'])->first();

            if(!$identity) {
                $identity = Identity::search(['registration_number', '=', $ownership_row['citizen_identification']])->read(['id'])->first();
            }
        }

        if(!$identity && !empty($ownership_row['registration_number'])) {
            $identity = Identity::search(['registration_number', '=', $ownership_row['registration_number']])->read(['id'])->first();
        }

        if(!$identity && !empty($ownership_row['vat_number'])) {
            $identity = Identity::search(['vat_number', '=', $ownership_row['vat_number']])->read(['id'])->first();
        }

        if(!$identity && $type_code === 'CO' && empty($ownership_row['registration_number'])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `registration_number` for company identity creation in ownership import sheet at row " . $row_index;
        }

        $identity_key = null;
        if($identity) {
            $identity_key = 'identity:' . $identity['id'];
        }
        elseif(!empty($ownership_row['citizen_identification'])) {
            $identity_key = 'citizen_identification:' . $ownership_row['citizen_identification'];
        }
        elseif(!empty($ownership_row['registration_number'])) {
            $identity_key = 'registration_number:' . $ownership_row['registration_number'];
        }
        elseif(!empty($ownership_row['vat_number'])) {
            $identity_key = 'vat_number:' . $ownership_row['vat_number'];
        }
        else {
            $identity_key = 'identity_row:' . $type_code . '|' . strtolower($owner_firstname) . '|' . strtolower($owner_lastname) . '|' . strtolower($ownership_row['zip'] ?? '') . '|' . strtoupper($ownership_row['country'] ?? '');
        }

        if(isset($identity_rows[$identity_key])) {
            ++$result['errors'];
            $result['logs'][] = "ERR - duplicate owner identity in ownership import sheet at rows {$identity_rows[$identity_key]} and $row_index";
            continue;
        }
        $identity_rows[$identity_key] = $row_index;

        if(isset($ownership_row['lang']) && $ownership_row['lang'] !== null && $ownership_row['lang'] !== '' && !in_array(strtolower($ownership_row['lang']), ['en', 'fr', 'nl'], true)) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `lang` ({$ownership_row['lang']}) in ownership import sheet at row " . $row_index;
        }

        if(isset($ownership_row['country']) && $ownership_row['country'] !== null && $ownership_row['country'] !== '' && !preg_match('/^[A-Z]{2}$/', strtoupper($ownership_row['country']))) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `country` ({$ownership_row['country']}) in ownership import sheet at row " . $row_index;
        }

        foreach(['email_1', 'email_2'] as $email_field) {
            if(!empty($ownership_row[$email_field]) && !preg_match('/^([_a-z0-9-\.]+)(\+([_a-z0-9]+))?@(([a-z0-9-]+\.)*)([a-z0-9-]{1,63})(\.[a-z-]{2,24})$/i', $ownership_row[$email_field])) {
                ++$result['errors'];
                $result['logs'][] = "ERR - invalid `$email_field` ({$ownership_row[$email_field]}) in ownership import sheet at row " . $row_index;
            }
        }

        foreach(['phone_1', 'phone_2', 'mobile_1'] as $phone_field) {
            if(!empty($ownership_row[$phone_field]) && !preg_match('/^\+?[0-9]*$/', $ownership_row[$phone_field])) {
                ++$result['errors'];
                $result['logs'][] = "ERR - invalid `$phone_field` ({$ownership_row[$phone_field]}) in ownership import sheet at row " . $row_index;
            }
        }

        if(!empty($ownership_row['date_of_birth']) && strtotime($ownership_row['date_of_birth']) === false) {
            ++$result['errors'];
            $result['logs'][] = "ERR - invalid `date_of_birth` ({$ownership_row['date_of_birth']}) in ownership import sheet at row " . $row_index;
        }

        if(array_key_exists('is_representative_owner', $ownership_row) && $ownership_row['is_representative_owner'] !== null && $ownership_row['is_representative_owner'] !== '') {
            $value = strtolower(trim((string) $ownership_row['is_representative_owner']));
            $is_valid_boolean = is_bool($ownership_row['is_representative_owner'])
                || is_numeric($ownership_row['is_representative_owner'])
                || in_array($value, ['1', 'true', 'yes', 'y', 'oui', 'o', 'x', '0', 'false', 'no', 'n', 'non'], true);

            if(!$is_valid_boolean) {
                ++$result['errors'];
                $result['logs'][] = "ERR - invalid `is_representative_owner` ({$ownership_row['is_representative_owner']}) in ownership import sheet at row " . $row_index;
            }
            else {
                $is_representative_owner = is_bool($ownership_row['is_representative_owner'])
                    ? $ownership_row['is_representative_owner']
                    : (is_numeric($ownership_row['is_representative_owner'])
                        ? ((float) $ownership_row['is_representative_owner']) !== 0.0
                        : in_array($value, ['1', 'true', 'yes', 'y', 'oui', 'o', 'x'], true));

                if($is_representative_owner) {
                    $representative_rows[] = $row_index;
                }
            }
        }

        $shares['rows'][] = $row_index;

        foreach(['shares_full_property', 'shares_bare_property', 'shares_usufruct'] as $field) {
            if(array_key_exists($field, $ownership_row) && $ownership_row[$field] !== null && $ownership_row[$field] !== '') {
                if(!is_numeric($ownership_row[$field])) {
                    ++$result['errors'];
                    $result['logs'][] = "ERR - invalid numeric value for `$field` in ownership import sheet at row " . $row_index;
                }
                else {
                    $shares[$field] += (float) $ownership_row[$field];
                    $shares['has_share_values'] = true;
                }
            }
        }

        if(array_key_exists('shares_total', $ownership_row) && $ownership_row['shares_total'] !== null && $ownership_row['shares_total'] !== '') {
            if(!is_numeric($ownership_row['shares_total'])) {
                ++$result['errors'];
                $result['logs'][] = "ERR - invalid numeric value for `shares_total` in ownership import sheet at row " . $row_index;
            }
            elseif($shares['shares_total'] === null) {
                $shares['shares_total'] = (float) $ownership_row['shares_total'];
            }
            elseif(abs($shares['shares_total'] - (float) $ownership_row['shares_total']) >= 0.01) {
                ++$result['errors'];
                $result['logs'][] = "ERR - inconsistent `shares_total` in ownership import sheet at row " . $row_index;
            }
        }
    }

    if($shares['has_share_values']) {
        $rows = implode(', ', $shares['rows']);
        $shares_full_property = $shares['shares_full_property'] ?? 0;
        $shares_bare_property = $shares['shares_bare_property'] ?? 0;
        $shares_usufruct = $shares['shares_usufruct'] ?? 0;
        $shares_total = $shares['shares_total'] ?? null;

        if($shares_total === null) {
            ++$result['errors'];
            $result['logs'][] = "ERR - missing `shares_total` for ownership import shares at rows: " . $rows;
        }
        else {
            if(abs($shares_bare_property - $shares_usufruct) >= 0.01) {
                ++$result['errors'];
                $result['logs'][] = "ERR - invalid ownership shares: `shares_bare_property` sum ($shares_bare_property) must equal `shares_usufruct` sum ($shares_usufruct) at rows: " . $rows;
            }

            if(abs(($shares_bare_property + $shares_full_property) - $shares_total) >= 0.01) {
                ++$result['errors'];
                $result['logs'][] = "ERR - invalid ownership shares: `shares_bare_property` sum ($shares_bare_property) + `shares_full_property` sum ($shares_full_property) must equal `shares_total` ($shares_total) at rows: " . $rows;
            }
        }
    }

    if(count($representative_rows) > 1) {
        ++$result['errors'];
        $result['logs'][] = "ERR - multiple representative owners in ownership import sheet at rows: " . implode(', ', $representative_rows);
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

if(count($result['logs']) <= 0) {
    $result['logs'][] = "INFO- file is valid (no errors found).";
}

DataImport::id($params['id'])
    ->update([
        'logs'      => json_encode($result['logs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'status'    => ($result['errors'] > 0) ? 'failing' : 'ready'
    ]);

$context->httpResponse()
        ->body(['result' => $result])
        ->send();
