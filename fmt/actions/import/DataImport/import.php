<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\text\TextTransformer;
use finance\accounting\AccountChart;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\bank\BankAccount;
use finance\bank\CondominiumBankAccount;
use fmt\import\DataImport;
use hr\employee\Employee;
use hr\role\Role;
use hr\role\RoleAssignment;
use identity\Identity;
use identity\IdentityType;
use purchase\supplier\Supplier;
use purchase\supplier\Suppliership;
use realestate\finance\accounting\CondoFund;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\Condominium;
use realestate\property\PropertyEntrance;
use realestate\property\PropertyLot;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotNature;
use realestate\property\PropertyLotOwnership;

[$params, $providers] = eQual::announce([
    'description'   => 'Return a JSON structure describing the import.',
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "Identifier of the targeted DataImport object.",
            'foreign_object'    => 'fmt\governance\DataImport',
            'required'          => true
        ],
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

['orm' => $orm] = $providers;


$result = [
    'created'   => 0,
    'updated'   => 0,
    'ignored'   => 0,
    'errors'    => 0,
    'processed' => 0,
    'logs'      => []
];


// fetch DataImport object
$dataImport = DataImport::id($params['id'])
    ->read(['name', 'status', 'import_type', 'logs'])
    ->first();

if(!$dataImport) {
    throw new Exception("unknown_data_import", EQ_ERROR_UNKNOWN_OBJECT);
}

if($dataImport['status'] !== 'ready') {
    throw new Exception("wrong_data_import_status", EQ_ERROR_UNKNOWN_OBJECT);
}


// fetch parsed JSON
$data = eQual::run('get', 'fmt_import_DataImport_parse', ['id' => $params['id']]);


try {

    if($dataImport['import_type'] === 'condominium_import') {

        $map_roles_ids = [];

        $map_external_representatives = [];
        $map_owners_identity = [];
        $map_ownerships = [];
        $map_owners = [];
        $map_property_entrances = [];
        $map_property_lots = [];
        $map_apportionments = [];

        $condominium = null;

        $roles = Role::search()->read(['id', 'code']);
        foreach($roles as $role_id => $role) {
            $map_roles_ids[$role['code']] = $role['id'];
        }

        /*
        if(preg_match_all('/\d+/', $dataImport['name'], $matches)) {
            $condominium = Condominium::id((int) $matches[0])->first();
        }

        if(!$condominium) {
            $condominium = Condominium::create(['managing_agent_id' => 1])->first();
        }
        */

        $events = $orm->disableEvents();

        // here, we assume that data is valid and complete

        foreach($data['Condominium'] as $condominium_data) {

            $fiscal_year_start = null;
            if($condominium_data['fiscal_year_start']) {
                $fiscal_year_start = strtotime($condominium_data['fiscal_year_start']);
            }

            $fiscal_year_end = null;
            if($condominium_data['fiscal_year_end']) {
                $fiscal_year_end = strtotime($condominium_data['fiscal_year_end']);
            }

            $condominiumIdentity = Identity::create([
                    'type_id'           => 3,
                    'type'              => "CO",
                    'description'       => null,
                    'bank_account_iban' => null,
                    'bank_account_bic'  => null,
                    'legal_name'        => $condominium_data['name'],
                    'nationality'       => "BE",
                    'has_vat'           => $condominium_data['has_vat'],
                    'vat_number'        => $condominium_data['vat_number'],
                    'lang_id'           => ['en' => 1, 'fr' => 2, 'nl' => 3][$condominium_data['lang']],
                    'address_street'    => $condominium_data['street'],
                    'address_city'      => $condominium_data['city'],
                    'address_zip'       => $condominium_data['zip'],
                    'address_country'   => $condominium_data['country'],
                ])
                ->first();

            $condominium = Condominium::create([
                    'legal_name'                => $condominium_data['name'],
                    'managing_agent_id'         => 1,
                    'registration_number'       => $condominium_data['registration_number'],
                    'cadastral_number'          => $condominium_data['cadastral_number'],
                    'fiscal_year_start'         => $fiscal_year_start,
                    'fiscal_year_end'           => $fiscal_year_end,
                    'fiscal_period_frequency'   => $condominium_data['fiscal_period'],
                    'expense_management_mode'   => $condominium_data['expense_mode'],
                    'identity_id'               => $condominiumIdentity['id']
                ])
                ->first();

            if($accountantEmployee) {
                RoleAssignment::create([
                    'condo_id'      => $condo_id,
                    'employee_id'   => $accountantEmployee['id'],
                    'role_id'       => $map_roles_ids['accountant']
                ]);
            }

            if($managerEmployee) {
                RoleAssignment::create([
                    'condo_id'      => $condo_id,
                    'employee_id'   => $managerEmployee['id'],
                    'role_id'       => $map_roles_ids['condo_manager']
                ]);
            }

            $accountChart = AccountChart::create([
                    'name'          => "Plan comptable",
                    'condo_id'      => $condominium['id']
                ])
                ->first();

            // #memo - only one condominium is expected
            break;
        }

        foreach($data['Bank_accounts'] as $bank_account) {

            CondominiumBankAccount::create([
                    'condo_id'          => $condominium['id'],
                    'owner_identity_id' => $condominiumIdentity['id'],
                    'description'       => $bank_account['description'],
                    'bank_account_type' => ['current' => 'bank_current', 'savings' => 'bank_savings'][$bank_account['type']],
                    'bank_account_iban' => $bank_account['iban'],
                    'is_primary'        => (bool) $bank_account['is_primary']
                ]);
        }

        foreach($data['External_representative'] as $external_representative) {

            $type = $external_representative['type'];

            // attempt to find existing identity by registration number
            $registration_number = $external_representative['registration_number'] ?? $external_representative['citizen_identification'];

            if($registration_number && strlen($registration_number) > 0) {
                $identity = Identity::search(['registration_number', '=', $registration_number])->read(['id'])->first();
            }

            if(!$identity) {

                $zip = $external_representative['zip'];
                $country = $external_representative['country'];

                if($type === 'IN') {
                    $legal_name = strtolower(TextTransformer::toAscii($external_representative['lastname'] . ' ' . $external_representative['firstname']));
                }
                else {
                    $legal_name = strtolower(TextTransformer::toAscii($external_representative['lastname']));
                }

                $legal_name = str_replace(['\'', ' '], '-', $legal_name);
                // attempt to find existing identity by slug
                if(strlen($type) > 0 && strlen($legal_name) > 0 && strlen($zip) > 0 && strlen($country) > 0) {
                    $slug_parts = [
                            $type,
                            $legal_name,
                            $zip,
                            $country
                        ];

                    $slug = implode('-', array_filter($slug_parts));
                    if(strlen($slug) > 255) {
                        $slug = substr($slug, 0, 255);
                    }
                    $slug_hash = md5($slug);
                    $identity = Identity::search(['slug_hash', '=', $slug_hash])->read(['id'])->first();
                }
            }

            // create a new identity
            if(!$identity) {
                $type = IdentityType::search(['code', '=', $external_representative['type']])
                    ->read(['id'])
                    ->first();

                $date_of_birth = null;
                if($external_representative['date_of_birth']) {
                    $date_of_birth = strtotime($external_representative['date_of_birth']);
                }

                $identity = Identity::create([
                        'type_id'                   => $type['id'],
                        'bank_account_iban'         => $external_representative['iban_1'],
                        'has_vat'                   => $external_representative['vat_number'] ? true : false,
                        'vat_number'                => $external_representative['vat_number'] ?? null,
                        'registration_number'       => $external_representative['registration_number'],
                        'citizen_identification'    => $external_representative['citizen_identification'],
                        'firstname'                 => $external_representative['firstname'],
                        'lastname'                  => $external_representative['lastname'],
                        'gender'                    => ['Madame' => 'F', 'Monsieur' => 'M'][$external_representative['title']],
                        'title'                     => ['Madame' => 'Mrs', 'Monsieur' => 'Mr'][$external_representative['title']],
                        'date_of_birth'             => $date_of_birth,
                        'lang_id'                   => ['en' => 1, 'fr' => 2, 'nl' => 3][$external_representative['lang']],
                        'address_street'            => $external_representative['street'],
                        'address_city'              => $external_representative['city'],
                        'address_zip'               => $external_representative['zip'],
                        'address_country'           => $external_representative['country'],
                        'email'                     => $external_representative['email_1'],
                        'email_alt'                 => $external_representative['email_2'],
                        'phone'                     => ($external_representative['phone_1']) ?: $external_representative['mobile_2'],
                        'mobile'                    => ($external_representative['mobile_1']) ?: $external_representative['phone_2'],
                    ])
                    ->first();

                try {

                    if($external_representative['iban_2']) {
                        BankAccount::create([
                            'owner_identity_id' => $identity['id'],
                            'iban'              => $external_representative['iban_2'],
                        ]);
                    }
                    if($external_representative['iban_3']) {
                        BankAccount::create([
                            'owner_identity_id' => $identity['id'],
                            'iban'              => $external_representative['iban_3'],
                        ]);
                    }

                }
                catch(Exception $e) {
                    // do nothing
                }
            }


            $map_external_representatives[$external_representative['code']] = $identity['id'];
        }

        foreach($data['Owners'] as $owner) {

            $type = $owner['type'];

            // attempt to find existing identity by registration number
            $registration_number = $owner['registration_number'] ?? $owner['citizen_identification'];

            if($registration_number && strlen($registration_number) > 0) {
                $identity = Identity::search(['registration_number', '=', $registration_number])->read(['id'])->first();
            }

            if(!$identity) {

                $zip = $owner['zip'];
                $country = $owner['country'];

                if($type === 'IN') {
                    $legal_name = strtolower(TextTransformer::toAscii($owner['lastname'] . ' ' . $owner['firstname']));
                }
                else {
                    $legal_name = strtolower(TextTransformer::toAscii($owner['lastname']));
                }

                $legal_name = str_replace(['\'', ' '], '-', $legal_name);
                // attempt to find existing identity by slug
                if(strlen($type) > 0 && strlen($legal_name) > 0 && strlen($zip) > 0 && strlen($country) > 0) {
                    $slug_parts = [
                            $type,
                            $legal_name,
                            $zip,
                            $country
                        ];

                    $slug = implode('-', array_filter($slug_parts));
                    if(strlen($slug) > 255) {
                        $slug = substr($slug, 0, 255);
                    }
                    $slug_hash = md5($slug);
                    $identity = Identity::search(['slug_hash', '=', $slug_hash])->read(['id'])->first();
                }
            }

            // create a new identity
            if(!$identity) {
                $type = IdentityType::search(['code', '=', $owner['type']])
                    ->read(['id'])
                    ->first();

                $date_of_birth = null;
                if($owner['date_of_birth']) {
                    $date_of_birth = strtotime($owner['date_of_birth']);
                }

                $identity = Identity::create([
                        'type_id'                   => $type['id'],
                        'bank_account_iban'         => $owner['iban_1'],
                        'has_vat'                   => $owner['vat_number'] ? true : false,
                        'vat_number'                => $owner['vat_number'] ?? null,
                        'registration_number'       => $owner['registration_number'],
                        'citizen_identification'    => $owner['citizen_identification'],
                        'firstname'                 => $owner['firstname'],
                        'lastname'                  => $owner['lastname'],
                        'gender'                    => ['Madame' => 'F', 'Monsieur' => 'M'][$owner['title']],
                        'title'                     => ['Madame' => 'Mrs', 'Monsieur' => 'Mr'][$owner['title']],
                        'date_of_birth'             => $date_of_birth,
                        'lang_id'                   => ['en' => 1, 'fr' => 2, 'nl' => 3][$owner['lang']],
                        'address_street'            => $owner['street'],
                        'address_city'              => $owner['city'],
                        'address_zip'               => $owner['zip'],
                        'address_country'           => $owner['country'],
                        'email'                     => $owner['email_1'],
                        'email_alt'                 => $owner['email_2'],
                        'phone'                     => ($owner['phone_1']) ?: $owner['mobile_2'],
                        'mobile'                    => ($owner['mobile_1']) ?: $owner['phone_2'],
                    ])
                    ->first();

                try {

                    if($owner['iban_2']) {
                        BankAccount::create([
                            'owner_identity_id' => $identity['id'],
                            'iban'              => $owner['iban_2'],
                        ]);
                    }
                    if($owner['iban_3']) {
                        BankAccount::create([
                            'owner_identity_id' => $identity['id'],
                            'iban'              => $owner['iban_3'],
                        ]);
                    }

                }
                catch(Exception $e) {
                    // do nothing
                }
            }

            $map_owners_identity[$owner['code']] = $identity['id'];

            $result['logs'][] = "INFO- assigned identity id {$identity['id']} to owner with code {$owner['code']}";
        }

        // ownerships pass 1 - create ownerships
        foreach($data['Ownerships_history'] as $ownership_history) {
            // prevent creating same ownership multiple times
            $ownership_id = $map_ownerships[$ownership_history['ownership_code']] ?? null;

            $date_to = strtotime($ownership_history['date_to']);
            if(!$date_to) {
                $date_to = null;
            }

            if(!$ownership_id) {
                $ownershipObject = Ownership::create([
                        'condo_id'  => $condominium['id'],
                        'date_from' => strtotime($ownership_history['date_from']),
                        'date_to'   => $date_to
                    ])
                    ->first();

                $map_ownerships[$ownership_history['ownership_code']] = $ownershipObject['id'];
                $result['logs'][] = "INFO- assigned id {$ownershipObject['id']} to ownership with code {$ownership_history['ownership_code']}";
            }
        }

        // ownerships pass 2 - link ownerships and owners
        $map_ownership_count_owners = [];
        foreach($data['Ownership'] as $ownership) {

            $ownership_id = $map_ownerships[$ownership['code']] ?? null;

            if(!$ownership_id) {
                // alert: should not happen
                $result['logs'][] = "ERR - unable to retrieve ownership with code {$ownership['code']}";
                continue;
            }

            $identity_id = $map_owners_identity[$ownership['owner_code']] ?? null;

            if(!$identity_id) {
                // alert: should not happen
                $result['logs'][] = "ERR - unable to retrieve identity for owner with code {$ownership['owner_code']}";
                continue;
            }

            if(!isset($map_ownership_count_owners[$ownership['code']])) {
                $map_ownership_count_owners[$ownership['code']] = 0;
            }

            $ownerObject = Owner::create([
                    'condo_id'              => $condominium['id'],
                    'ownership_id'          => $ownership_id,
                    'shares_full_property'  => $ownership['shares_full_property'],
                    'shares_bare_property'  => $ownership['shares_bare_property'],
                    'shares_usufruct'       => $ownership['shares_usufruct'],
                    'identity_id'           => $identity_id
                ])
                ->first();

            $map_owners[$ownership['owner_code']] = $ownerObject['id'];

            ++$map_ownership_count_owners[$ownership['code']];
        }

        // ownerships pass 3 - set ownership_type
        foreach($data['Ownership'] as $ownership) {
            $ownership_id = $map_ownerships[$ownership['code']] ?? null;

            if(!$ownership_id) {
                // alert: should not happen
                $result['logs'][] = "ERR - unable to retrieve ownership with code {$ownership['code']}";
                continue;
            }

            if($map_ownership_count_owners[$ownership['code']] > 1) {
                Ownership::id($ownership_id)->update(['ownership_type' => 'joint']);
            }
        }

        // ownerships pass 4 - link representatives
        foreach($data['Ownership'] as $ownership) {

            $ownership_id = $map_ownerships[$ownership['code']] ?? null;

            if(!$ownership_id) {
                // alert: should not happen
                $result['logs'][] = "ERR - unable to retrieve ownership with code {$ownership['code']}";
                continue;
            }

            if(isset($ownership['representative_owner_code'])) {
                $owner_id = $map_owners[$ownership['representative_owner_code']] ?? null;

                if(!$owner_id) {
                    // alert: should not happen
                    $result['logs'][] = "ERR - unable to retrieve owner with code {$ownership['representative_owner_code']}";
                    continue;
                }

                Ownership::id($ownership_id)->update([
                        'has_representative'        => true,
                        'representative_owner_id'   => $owner_id
                    ]);
            }

            if(isset($ownership['external_representative_code'])) {
                $identity_id = $map_external_representatives[$ownership['external_representative_code']];

                if(!$identity_id) {
                    // alert: should not happen
                    $result['logs'][] = "ERR - unable to retrieve identity_id for external_representative with code {$ownership['external_representative_code']}";
                    continue;
                }

                Ownership::id($ownership_id)->update([
                        'has_external_representative'   => true,
                        'representative_identity_id'    => $identity_id
                    ]);

            }
        }

        // Entrances
        foreach($data['Entrances'] as $entrance) {
            $propertyEntrance = PropertyEntrance::create([
                    'name'              => $entrance['name'],
                    'address_street'    => $entrance['street'],
                    'address_city'      => $entrance['city'],
                    'address_zip'       => $entrance['zip'],
                    'address_country'   => $entrance['country'],
                    'condo_id'          => $condominium['id']
                ])
                ->first();

            $map_property_entrances[$entrance['code']] = $propertyEntrance['id'];
        }

        // Lots
        foreach($data['Lots'] as $lot) {

            // #todo - complete
            $nature = [
                'APPARTEMENT'   => 'apartment',
                'APARTMENT'     => 'apartment',
                'PARKING'       => 'parking',
                'GARAGE'        => 'garage'
                ][$lot['nature']] ?? null;

            if(!$nature) {
                // alert: should not happen
                continue;
            }

            $propertyLotNature = PropertyLotNature::search(['code', '=', $nature])
                ->read(['id'])
                ->first();

            $is_primary = (bool) $lot['primary_lot_code'];
            $primary_lot_id = $map_property_lots[$lot['primary_lot_code']] ?? null;

            $propertyLot = PropertyLot::create([
                    'property_lot_ref'      => $lot['ref'],
                    'nature_id'             => $propertyLotNature['id'],
                    'property_entrance_id'  => $map_property_entrances[$lot['entrance_code']] ?? null,
                    'condo_id'              => $condominium['id'],
                    'cadastral_number'      => $lot['cadastral_number'],
                    'lot_floor'             => $lot['floor'],
                    'lot_column'            => $lot['column'],
                    'lot_letterbox'         => $lot['letterbox'],
                    'lot_area'              => $lot['area'],
                    'is_primary'            => $is_primary,
                    'primary_lot_id'        => $primary_lot_id
                ])
                ->first();

            $map_property_lots[$lot['code']] = $propertyLot['id'];
        }


        // ownerships pass 3 - create ownerships
        foreach($data['Ownerships_history'] as $ownership_history) {
            $ownership_id = $map_ownerships[$ownership_history['ownership_code']] ?? null;

            if(!$ownership_id) {
                // alert: should not happen
                continue;
            }

            $property_lot_id = $map_ownerships[$ownership_history['lot_code']] ?? null;

            if(!$property_lot_id) {
                // alert: should not happen
                continue;
            }

            $date_to = strtotime($ownership_history['date_to']);
            if(!$date_to) {
                $date_to = null;
            }

            PropertyLotOwnership::create([
                    'condo_id'          => $condominium['id'],
                    'property_lot_id'   => $property_lot_id,
                    'ownership_id'      => $ownership_id,
                    'date_from'         => strtotime($ownership_history['date_from']),
                    'date_to'           => $date_to
                ]);

        }

        // Apport_keys
        foreach($data['Apport_keys'] as $apportionment_key) {

            $is_statutory = ($apportionment_key['code'] === 'stat');

            $apportionment = Apportionment::create([
                    'condo_id'          => $condominium['id'],
                    'description'       => $apportionment_key['description'],
                    'total_shares'      => $apportionment_key['total_shares'],
                    'is_statutory'      => $is_statutory
                ])
                ->first();

            $map_apportionments[$apportionment_key['code']] = $apportionment['id'];
        }

        // Apport_shares
        foreach($data['Apport_shares'] as $apportionment_share) {

            $apportionment_id = $map_apportionments[$apportionment_share['apport_key_code']] ?? null;
            $property_lot_id = $map_property_lots[$apportionment_share['lot_code']] ?? null;

            if(!$apportionment_id) {
                // alert: should not happen
                continue;
            }

            if(!$property_lot_id) {
                // alert: should not happen
                continue;
            }

            PropertyLotApportionmentShare::create([
                    'condo_id'              => $condominium['id'],
                    'apportionment_id'      => $apportionment_id,
                    'property_lot_id'       => $property_lot_id,
                    'property_lot_shares'   => $apportionment_share['lot_shares']
                ]);

        }


        // Supplierships
        foreach($data['Supplierships'] as $suppliership) {

            $supplier = Supplier::id((int) $suppliership['supplier_code'])->first();

            if(!$supplier) {
                // alert: should not happen
                continue;
            }

            Suppliership::create([
                    'condo_id'      => $condominium['id'],
                    'supplier_id'   => $supplier['id']
                ]);
        }


        $orm->enableEvents($events);

        // trigger refresh & sync events on created objects

        Condominium::id($condominium['id'])->do('sync_from_identity');

        Owner::ids(array_values($map_owners))->do('sync_from_identity');

        Identity::ids(array_values($map_owners_identity))
            ->read(['slug_hash'])
            ->do('refresh_legal_name')
            ->do('refresh_registration_number');

        Apportionment::ids(array_values($map_apportionments))
            ->transition('validate');

        AccountChart::search(['condo_id', '=', $condominium['id']])
            ->do('import_accounts', ['chart_template_id' => 1])
            ->transition('activation');

        PropertyLot::search(['condo_id', '=', $condominium['id']])
            ->read(['code']);

        Ownership::search(['condo_id', '=', $condominium['id']])
            ->read(['code'])
            ->transition('validate');

        Suppliership::search(['condo_id', '=', $condominium['id']])
            ->read(['code'])
            ->transition('validate');

        // create supplierships for managing agent
        Suppliership::create(["condo_id" => $condominium['id'], "supplier_id" => 1]);


        $condominiums = Condominium::id($condominium['id']);

        // create first fiscal year draft
        $condominiums->do('create_draft_fiscal_year');

        FiscalYear::search([['condo_id', '=', $condominium['id']], ['status', '=', 'draft']])
            ->do('generate_periods')
            ->transition('preopen');

        // create following fiscal year draft
        $condominiums->do('create_draft_fiscal_year');

        FiscalYear::search([['condo_id', '=', $condominium['id']], ['status', '=', 'draft']])
            ->do('generate_periods');

        // open candidate fiscal year
        $condominiums->do('open_fiscal_year');

        // force computing names
        FiscalPeriod::search([['condo_id', '=', $condominium['id']], ['status', '=', 'pending']])
            ->read(['name']);

        $apportionment_id = null;
        foreach($map_apportionments as $apportionment_code => $apportionment_id) {
            if($apportionment_code !== 'stat') {
                break;
            }
        }

        CondoFund::create([
                'description'           => 'Fonds de roulement',
                'condo_id'              => $condominium['id'],
                'apportionment_id'      => $apportionment_id,
                'fund_type'             => 'working_fund'
            ])
            ->transition('validate');

        CondoFund::create([
                'description'           => 'Fonds de réserve',
                'condo_id'              => $condominium['id'],
                'apportionment_id'      => $apportionment_id,
                'fund_type'             => 'reserve_fund'
            ])
            ->transition('validate');

    }
}
catch(Exception $e) {
    $result['logs'][] = "ERR - Unexpected error :" . $e->getMessage();
}
finally {
    if(count($result['logs'])) {
        $logs = json_decode($dataImport['logs'], true);

        DataImport::id($params['id'])
            ->update([
                'logs'      => json_encode(array_merge($logs, $result['logs']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ]);
    }
}



$context->httpResponse()
        ->status(201)
        ->send();


