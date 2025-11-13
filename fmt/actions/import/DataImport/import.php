<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\text\TextTransformer;
use finance\bank\BankAccount;
use fmt\import\DataImport;
use identity\Identity;
use identity\IdentityType;
use purchase\supplier\Supplier;
use purchase\supplier\Suppliership;
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
    ->read(['name', 'status', 'import_type'])
    ->first();

if(!$dataImport) {
    throw new Exception("unknown_data_import", EQ_ERROR_UNKNOWN_OBJECT);
}

if($dataImport['status'] !== 'ready') {
    throw new Exception("wrong_data_import_status", EQ_ERROR_UNKNOWN_OBJECT);
}


// fetch parsed JSON
$data = eQual::run('get', 'fmt_import_DataImport_parse', ['id' => $params['id']]);


if($dataImport['import_type'] === 'condominium_import') {

    $map_owners_identity = [];
    $map_ownerships = [];
    $map_owners = [];
    $map_property_entrances = [];
    $map_property_lots = [];
    $map_apportionments = [];

    $condominium = null;
    if(preg_match_all('/\d+/', $dataImport['name'], $matches)) {
        $condominium = Condominium::id((int) $matches[0])->first();
    }

    if(!$condominium) {
        $condominium = Condominium::create(['managing_agent_id' => 1])->first();
    }

    $events = $orm->disableEvents();

    // we assume that data is valid and complete
    foreach($data['Owner'] as $owner) {

        $type = $owner['owner_type'];

        $registration_number = $owner['owner_num_entreprise'] ?? $owner['owner_num_national'];

        $identity = Identity::search(['registration_number', '=', $registration_number])->read(['id'])->first();

        if(!$identity) {

            $zip = $owner['owner_code_postal'];
            $country = $owner['owner_pays'];

            if($type == 'IN') {
                $legal_name = strtolower(TextTransformer::toAscii($owner_nom['owner_nom'] . ' ' . $owner_nom['owner_prenom']));
            }
            else {
                $legal_name = strtolower(TextTransformer::toAscii($identity['owner_nom']));
            }

            $legal_name = str_replace(['\'', ' '], '-', $legal_name);

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

            if(!$identity) {
                $type = IdentityType::search(['code', '=', $owner['owner_type']])
                    ->read(['id'])
                    ->first();

                $date_of_birth = null;

                if($owner['owner_date_naissance']) {
                    $date_of_birth = strtotime($owner['owner_date_naissance']);
                }

                $identity = Identity::create([
                        "type_id"                   => $type['id'],
                        "bank_account_iban"         => $owner['owner_iban_1'],
                        "has_vat"                   => $owner['owner_num_tva'] ? true : false,
                        "vat_number"                => $owner['owner_num_tva'],
                        "registration_number"       => $owner['owner_num_entreprise'],
                        "citizen_identification"    => $owner['owner_num_national'],
                        "firstname"                 => $owner['owner_prenom'],
                        "lastname"                  => $owner['owner_nom'],
                        "gender"                    => ['Madame' => 'F', 'Monsieur' => 'M'][$owner['owner_civilite']],
                        "title"                     => ['Madame' => 'Mrs', 'Monsieur' => 'Mr'][$owner['owner_civilite']],
                        "date_of_birth"             => $date_of_birth,
                        "lang_id"                   => ['en' => 1, 'fr' => 2, 'nl' => 3][$owner['owner_langue']],
                        "address_street"            => $owner['owner_rue'],
                        "address_city"              => $owner['owner_ville'],
                        "address_zip"               => $owner['owner_code_postal'],
                        "address_country"           => $owner['owner_pays'],
                        "email"                     => $owner['owner_email_1'],
                        "email_alt"                 => $owner['owner_email_2'],
                        "phone"                     => ($owner['owner_tel_1']) ?: $owner['owner_mobile_2'],
                        "mobile"                    => ($owner['owner_mobile_1']) ?: $owner['owner_tel_2'],
                    ])
                    ->first();

                try {

                    if($owner['owner_iban_2']) {
                        BankAccount::create([
                            'identity_id'       => $identity['id'],
                            'iban'              => $owner['owner_iban_2'],
                        ]);
                    }
                    if($owner['owner_iban_3']) {
                        BankAccount::create([
                            'identity_id'       => $identity['id'],
                            'iban'              => $owner['owner_iban_3'],
                        ]);
                    }

                }
                catch(Exception $e) {
                    // do nothing
                }
            }
        }

        $map_owners_identity[$owner['owner_code']] = $identity['id'];
    }

    // ownerships pass 1 - create ownerships
    foreach($data['Ownership_histo'] as $ownership_history) {
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
        }
    }

    // ownerships pass 2 - link ownerships and owners
    foreach($data['Ownership'] as $ownership) {

        $ownership_id = $map_ownerships[$ownership['ownership_code']] ?? null;

        if(!$ownership_id) {
            // alert: should not happen
            continue;
        }

        $identity_id = $map_owners_identity[$ownership['owner_code']] ?? null;

        if(!$identity_id) {
            // alert: should not happen
            continue;
        }

        $owner_type = 'full';
        $owner_shares = 100;

        if($ownership['PP']) {
            $owner_type = 'full';
            $owner_shares = $ownership['PP'];
        }
        if($ownership['NP']) {
            $owner_type = 'bare';
            $owner_shares = $ownership['NP'];
        }
        if($ownership['Ust']) {
            $owner_type = 'usufruct';
            $owner_shares = $ownership['Ust'];
        }

        $ownerObject = Owner::create([
                'condo_id'      => $condominium['id'],
                'ownership_id'  => $ownership_id,
                'owner_shares'  => $owner_shares,
                'owner_type'    => $owner_type,
                'identity_id'   => $identity_id
            ])
            ->first();

        $map_owners[$ownership['owner_code']] = $ownerObject['id'];

    }

    // Entrances
    foreach($data['Entrances'] as $entrance) {
        $propertyEntrance = PropertyEntrance::create([
                'address_street' => $entrance['entrance_rue'],
                'condo_id'       => $condominium['id']
            ])
            ->first();

        $map_property_entrances[$entrance['entrance_code']] = $propertyEntrance['id'];
    }

    // Lots
    foreach($data['Lots'] as $lot) {

        $nature = ['APPARTEMENT' => 'APARTMENT', 'PARKING' => 'PARKING', 'GARAGE' => 'GARAGE'][$lot['lot_nature']] ?? null;

        if(!$nature) {
            // alert: should not happen
            continue;
        }

        $propertyLotNature = PropertyLotNature::search(['name', '=', $nature])
            ->read(['id'])
            ->first();

        $is_primary = (bool) $lot['lot_principal_code'];
        $primary_lot_id = $map_property_lots[$lot['lot_principal_code']] ?? null;

        $propertyLot = PropertyLot::create([
                'property_lot_ref'      => $lot['lot_ref'],
                'nature_id'             => $propertyLotNature['id'],
                'property_entrance_id'  => $map_property_entrances[$lot['entrance_code']] ?? null,
                'condo_id'              => $condominium['id'],
                'cadastral_number'      => $lot['lot_cadastral_number'],
                'lot_floor'             => $lot['lot_etage'],
                'lot_column'            => $lot['lot_column'],
                'lot_letterbox'         => $lot['lot_column'],
                'lot_area'              => $lot['lot_area'],
                'is_primary'            => $is_primary,
                'primary_lot_id'        => $primary_lot_id
            ])
            ->first();

        $map_property_lots[$lot['lot_code']] = $propertyLot['id'];
    }


    // ownerships pass 3 - create ownerships
    foreach($data['Ownership_histo'] as $ownership_history) {
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

        $is_statutory = ($apportionment_key['apport_keys_code'] === 'stat');

        $apportionment = Apportionment::create([
                'condo_id'          => $condominium['id'],
                'description'       => $apportionment_key['apport_keys_description'],
                'total_shares'      => $apportionment_key['apport_keys_total_shares'],
                'is_statutory'      => $is_statutory
            ])
            ->first();

        $map_apportionments[$apportionment_key['apport_keys_code']] = $apportionment['id'];

    }

    // Apport_shares
    foreach($data['Apport_shares'] as $apportionment_share) {

        $apportionment_id = $map_apportionments[$apportionment_share['apport_keys_code']] ?? null;
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
                'property_lot_shares'   => $apportionment_share['lot_apport_shares']
            ]);

    }


    // Supplierships
    foreach($data['supplier'] as $suppliership) {

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

    // sync owners from identities
    Owner::ids(array_values($map_owners))->do('sync_from_identity');
    Identity::ids(array_values($map_owners_identity))
        ->read(['slug_hash'])
        ->do('refresh_legal_name')
        ->do('refresh_registration_number');
}




$context->httpResponse()
        ->status(201)
        ->send();


