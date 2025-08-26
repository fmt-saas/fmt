<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use identity\Identity;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use purchase\supplier\Supplier;

[$params, $providers] = eQual::announce([
    'description'   => 'Return raw data (with original MIME) of a XLSX document.',
    'params'        => [
        'data' => [
            'type'              => 'binary',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'constants'     => ['AUTH_SECRET_KEY'],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

['orm' => $orm] = $providers;

$mapSupplierRowToJson = function (array $row): array {
    return [
        "identity_source"     => "manual",
        "source_type"         => "manual",
        "type_id"             => 3,
        "type"                => "CO",
        "bank_account_iban"   => isset($row['fournisseur_iban_1']) && $row['fournisseur_iban_1'] !== null
                ? preg_replace('/[^A-Z0-9]/i', '', $row['fournisseur_iban_1'])
                : null,
        "vat_number" => isset($row['fournisseur_numero_tva']) && $row['fournisseur_numero_tva'] !== null
                ? preg_replace('/[^A-Z0-9]/i', '', $row['fournisseur_numero_tva'])
                : null,
        "registration_number" => isset($row['fournisseur_numero_entreprise']) && $row['fournisseur_numero_entreprise'] !== null
                ? preg_replace('/[^0-9]/i', '', $row['fournisseur_numero_entreprise'])
                : null,
        "legal_name"          => $row['fournisseur_nom'],
        "has_vat"             => !empty($row['fournisseur_numero_tva']),
        "nationality"         => strtoupper($row['fournisseur_pays'] ?? 'BE'),
        "lang_id"             => 2,
        "address_street"      => $row['fournisseur_nom_rue'] ?? null,
        "address_city"        => $row['fournisseur_localite'] ?? null,
        "address_zip"         => $row['fournisseur_code_postal'] ?? null,
        "email"               => $row['fournisseur_email_1'] ?? null,
        "email_alt"           => $row['fournisseur_email_2'] ?? null,
        "phone"               => $row['fournisseur_tel_1'] ?? null,
        "phone_alt"           => $row['fournisseur_tel_2'] ?? null
    ];
};


$calcHashSha256 = function ($supplier) {
    if(!$supplier['registration_number'] || strlen($supplier['registration_number']) <= 0) {
        return null;
    }
    return hash('sha256', $supplier['registration_number'] . constant('AUTH_SECRET_KEY'));
};


try {
    // #todo - when PhpOffice version will support it, use memory stream instead of tmp file
    $reader = IOFactory::createReader('Xlsx');
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    file_put_contents($tmp, $params['data']);
    $spreadsheet = $reader->load($tmp);
    unlink($tmp);
}
catch(Exception $e) {
    trigger_error("APP:unable to load data from given XLSX with PhpOffice: " . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('failed_loading_xlsx', EQ_ERROR_UNKNOWN);
}

$worksheet = $spreadsheet->getActiveSheet();

$lines = [];
$headers = [];

foreach($worksheet->getRowIterator() as $index => $rowIterator) {
    $cellIterator = $rowIterator->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);

    $cells = [];
    foreach($cellIterator as $cell) {
        $cells[] = $cell->getValue();
    }

    // header line
    if($index === 1) {
        $headers = $cells;
        continue;
    }

    // mapping : ["fournisseur_nom" => "XYZ", ...]
    $row = [];
    foreach($headers as $col_index => $header) {
        if($col_index === 0 && (!$cells[$col_index] || strlen($cells[$col_index] <= 0))) {
            break 2;
        }
        $row[$header] = $cells[$col_index] ?? null;
    }

    $lines[] = $row;
}

$events = $orm->disableEvents();


for($i = 0, $n = count($lines); $i < $n; ++$i) {

    $values = $mapSupplierRowToJson($lines[$i]);

    $hash_sha256 = $calcHashSha256($values);
    $identity = null;
    $supplier = null;

    if($hash_sha256) {
        $identity = Identity::search(['hash_sha256', '=', $hash_sha256])->first();
        $supplier = Supplier::search(['hash_sha256', '=', $hash_sha256])->first();
    }

    if(!$identity) {
        $identity = Identity::create($values)
            ->do('refresh_bank_accounts')
            ->first();
    }

    if(!$supplier) {
        Supplier::create([ 'identity_id' => $identity['id'] ])
            ->do('sync_from_identity');
    }
}

$orm->enableEvents($events);

$context->httpResponse()
        ->status(201)
        ->send();
