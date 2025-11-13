<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\import\DataImport;
use purchase\supplier\Supplier;
use realestate\property\Condominium;

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
$dataImport = DataImport::id($params['id'])->read(['name', 'import_type'])->first();

if(!$dataImport) {
    throw new Exception("unknown_data_import", EQ_ERROR_UNKNOWN_OBJECT);
}

// fetch parsed JSON
$data = eQual::run('get', 'fmt_import_DataImport_parse', ['id' => $params['id']]);


if($dataImport['import_type'] == 'condominium_import') {
    if(preg_match_all('/\d+/', $dataImport['name'], $matches)) {
        $condo_code = $matches[0];
        $condominium = Condominium::id((int) $condo_code)->first();
        if(!$condominium) {
            ++$result['errors'];
            $result['logs'][] = "ERR - unknown condominium_code {$condo_code} retrieved from file name: '" . $dataImport['name'] . "'";
        }
    }
}

// 1) map existing codes amongst sheets

$map_owners_codes = [];
foreach($data['Owner'] as $owner) {
    if(isset($owner['owner_code'])) {
        $map_owners_codes[$owner['owner_code']] = true;
    }
}

$map_ownerships_codes = [];
foreach($data['Ownership'] as $ownership) {
    if(isset($ownership['ownership_code'])) {
        $map_ownerships_codes[$ownership['ownership_code']] = true;
    }
}

$map_property_entrances_codes = [];
foreach($data['Entrances'] as $property_entrance) {
    if(isset($property_entrance['entrance_code'])) {
        $map_property_entrances_codes[$property_entrance['entrance_code']] = true;
    }
}

$map_property_lots_codes = [];
foreach($data['Lots'] as $property_lot) {
    if(isset($property_lot['lot_code'])) {
        $map_property_lots_codes[$property_lot['lot_code']] = true;
    }
}

$map_apportionment_keys_codes = [];
foreach($data['Apport_keys'] as $apportionment_key) {
    if(isset($apportionment_key['apport_keys_code'])) {
        $map_apportionment_keys_codes[$apportionment_key['apport_keys_code']] = true;
    }
}

$map_suppliers_codes = [];
foreach($data['suppliership'] as $suppliership) {
    if(isset($suppliership['supplier_code'])) {
        $map_suppliers_codes[$suppliership['supplier_code']] = true;
    }
}

// 2) - check crossed-references consistency

foreach($data['Owner'] as $index => $owner) {
    if(!isset($owner['owner_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing owner_code in Owner sheet at row " . ($index + 2);
    }
}

foreach($data['Ownership'] as $index => $ownership) {
    if(!isset($ownership['owner_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing owner_code in Ownership sheet at row " . ($index + 2);
    }
    if(!isset($map_owners_codes[$ownership['owner_code']])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - unknown owner_code '" . $ownership['owner_code'] . "' in Ownership sheet at row " . ($index + 2);
    }
}

foreach($data['Ownership_com'] as $index => $ownership_communication) {
    if(!isset($ownership_communication['ownership_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing ownership_code in Ownership_com sheet at row " . ($index + 2);
    }
    if(!isset($map_ownerships_codes[$ownership_communication['ownership_code']])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - unknown ownership_code '" . $ownership['ownership_code'] . "' in Ownership_com sheet at row " . ($index + 2);
    }
}

foreach($data['Lots'] as $index => $property_lot) {
    if(!isset($property_lot['lot_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing lot_code in Lots sheet at row " . ($index + 2);
    }
    if(!isset($property_lot['entrance_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing entrance_code in Lots sheet at row " . ($index + 2);
    }
    if(!isset($map_property_entrances_codes[$property_lot['entrance_code']])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - unknown entrance_code '" . $property_lot['entrance_code'] . "' in Lots sheet at row " . ($index + 2);
    }
}

foreach($data['Ownership_histo'] as $index => $ownership_history) {
    if(!isset($ownership_history['lot_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing lot_code in Ownership_histo sheet at row " . ($index + 2);
    }
    if(!isset($map_property_lots_codes[$ownership_history['lot_code']])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - unknown lot_code '" . $ownership_history['lot_code'] . "' in Ownership_histo sheet at row " . ($index + 2);
    }
    if(!isset($ownership_history['ownership_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing ownership_code in Ownership_histo sheet at row " . ($index + 2);
    }
    if(!isset($map_ownerships_codes[$ownership_history['ownership_code']])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - unknown ownership_code '" . $ownership_history['ownership_code'] . "' in Ownership_histo sheet at row " . ($index + 2);
    }
}

foreach($data['Apport_keys'] as $index => $apportionment_key) {
    if(!isset($apportionment_key['apport_keys_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing apport_keys_code in Apport_keys sheet at row " . ($index + 2);
    }
}


foreach($data['Apport_shares'] as $index => $apportionment_share) {
    if(!isset($apportionment_share['apport_keys_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing apport_keys_code in Apport_shares sheet at row " . ($index + 2);
    }
    if(!isset($apportionment_share['lot_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing lot_code in Apport_shares sheet at row " . ($index + 2);
    }
    if(!isset($map_apportionment_keys_codes[$apportionment_share['apport_keys_code']])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - unknown apport_keys_code '" . $apportionment_share['apport_keys_code'] . "' in Apport_shares sheet at row " . ($index + 2);
    }
    if(!isset($map_property_lots_codes[$apportionment_share['lot_code']])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - unknown lot_code '" . $apportionment_share['lot_code'] . "' in Apport_shares sheet at row " . ($index + 2);
    }
}

foreach($data['suppliership'] as $index => $suppliership) {
    if(!isset($suppliership['supplier_code'])) {
        ++$result['errors'];
        $result['logs'][] = "ERR - missing supplier_code in suppliership sheet at row " . ($index + 2);
    }
    $supplier = Supplier::id((int) $suppliership['supplier_code'])->first();
    if(!$supplier) {
        ++$result['errors'];
        $result['logs'][] = "ERR - unknown supplier_code '" . $suppliership['supplier_code'] . "' in suppliership sheet at row " . ($index + 2);
    }
}

DataImport::id($params['id'])
    ->update([
        'logs'      => json_encode($result['logs']),
        'status'    => ($result['errors'] > 0) ? 'failing' : 'ready'
    ]);

$context->httpResponse()
        ->body([
            'result' => $result
        ])
        ->send();
