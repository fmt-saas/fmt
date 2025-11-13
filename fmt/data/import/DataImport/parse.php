<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\import\DataImport;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

// convert a XLSX file to a set of JSON objects (consistency is not verified here)

$mapXlsToJson = function (string $import_type, string $sheet, string $field, $value = null) {
    // normalized raw value
    $value = is_string($value) ? trim($value) : $value;

    // handling empty values
    if ($value === '' || $value === null) {
        return null;
    }

    // typical cleaning (applicable to all sheets)
    switch (true) {
        // VAT numbers
        case preg_match('/num_tva|vat/i', $field):
            return preg_replace('/[^A-Z0-9]/i', '', $value);

        // Company registration number
        case preg_match('/num_entreprise|registration/i', $field):
            return preg_replace('/[^0-9]/', '', $value);

        // IBAN
        case preg_match('/_iban/i', $field):
            return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value));

        // Email
        case preg_match('/_email/i', $field):
            return strtolower($value);

        // Phone (remove spaces and symbols)
        case preg_match('/_tel|_mobile/i', $field):
            return preg_replace('/[^0-9+]/', '', $value);


        // Dates (attempt to convert to ISO format)
        case preg_match('/date_/i', $field):
            if($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }
            if(is_numeric($value)) {
                // raw Excel value
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->getTimestamp();
                return date('Y-m-d', $date);
            }
            if(is_string($value)) {
                $v = trim($value);

                // Normalize separators
                $v = str_replace(['.', '\\'], '/', $v);

                // support only case DD/MM/YYYY
                if(preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $matches)) {
                    if ((int)$matches[2] > 12) {
                        [$d, $mth, $y] = [$matches[2], $matches[1], $matches[3]];
                    } 
                    else {
                        [$d, $mth, $y] = [$matches[1], $matches[2], $matches[3]];
                    }
                    return sprintf('%04d-%02d-%02d', $y, $mth, $d);
                }

                // Last attempt with strtotime
                $ts = strtotime($v);
                if($ts !== false) {
                    return date('Y-m-d', $ts);
                }
            }

            return $value;

        // Amounts (columns containing amount, total, share, etc.)
        case preg_match('/amount|total|share|price|part|PP|NP|Ust/i', $field):
            $num = str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $value));
            return is_numeric($num) ? (float)$num : $value;

        default:
            // sheet-specific cases
            switch ($sheet) {
                case 'Owner':
                    // e.g., uppercase countries/languages
                    if (in_array($field, ['owner_pays', 'owner_langue'])) {
                        return strtoupper($value);
                    }
                    break;

                case 'Ownership_histo':
                    // check coherence of dates
                    if (preg_match('/date_/', $field)) {
                        $ts = strtotime($value);
                        return $ts ? date('Y-m-d', $ts) : null;
                    }
                    break;

                case 'Lots':
                    // Clean lot_code or ref
                    if (in_array($field, ['lot_code', 'lot_ref'])) {
                        return strtoupper($value);
                    }
                    break;
            }

            return $value;
    }
};

// 1) fetch DataImport object
$dataImport = DataImport::id($params['id'])->read(['name', 'import_type', 'document_id' => ['data']])->first();

if(!$dataImport) {
    throw new Exception("unknown_data_import", EQ_ERROR_UNKNOWN_OBJECT);
}

// 2) load XLSX document data
try {
    // #todo - when PhpOffice version will support it, use memory stream instead of tmp file
    $reader = IOFactory::createReader('Xlsx');
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    file_put_contents($tmp, $dataImport['document_id']['data']);
    $spreadsheet = $reader->load($tmp);
    unlink($tmp);
}
catch(Exception $e) {
    trigger_error("APP:unable to load data from given XLSX with PhpOffice: " . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('failed_loading_xlsx', EQ_ERROR_UNKNOWN);
}

//3) parse XLSX data into JSON structure
$map = [
    'condominium_import' => [
        'Owner'            => ['owner_code', 'owner_type', 'owner_nom', 'owner_prenom', 'owner_civilite', 'owner_rue', 'owner_code_postal', 'owner_ville', 'owner_pays', 'owner_langue', 'owner_tel_1', 'owner_tel_2', 'owner_mobile_1', 'owner_mobile_2', 'owner_email_1', 'owner_email_2', 'owner_iban_1', 'owner_iban_2', 'owner_iban_3', 'owner_date_naissance', 'owner_num_national', 'owner_num_tva', 'owner_num_entreprise'],
        'Ownership'        => ['ownership_code', 'owner_code', 'PP', 'NP', 'Ust'],
        'Ownership_com'    => ['ownership_code', 'representative_owner_1', 'representative_owner_2', 'general_assembly_call', 'general_assembly_minutes', 'expense_statement', 'fund_request', 'technical_communication', 'ownership_name'],
        'Entrances'        => ['entrance_code', 'entrance_rue', 'entrance_code_postal', 'entrance_ville', 'entrance_pays'],
        'Lots'             => ['lot_code', 'lot_ref', 'lot_nature', 'entrance_code', 'lot_etage', 'lot_column', 'lot_letterbox', 'lot_area', 'lot_principal_code', 'lot_cadastral_number'],
        'Ownership_histo'  => ['lot_code', 'ownership_code', 'date_from', 'date_to'],
        'Apport_keys'      => ['apport_keys_code', 'apport_keys_description', 'apport_keys_total_shares'],
        'Apport_shares'    => ['apport_keys_code', 'lot_code', 'lot_apport_shares'],
        'suppliership'     => ['supplier_code']
    ],
    'suppliers_import' => [
        'supplier'  => ['fournisseur_code', 'fournisseur_type', 'fournisseur_nom', 'fournisseur_nom_usuel', 'fournisseur_rue', 'fournisseur_code_postal', 'fournisseur_ville', 'fournisseur_pays', 'fournisseur_tel_1', 'fournisseur_tel_2', 'fournisseur_mobile_1', 'fournisseur_mobile_2', 'fournisseur_email_1', 'fournisseur_email_2', 'fournisseur_iban_1', 'fournisseur_iban_2', 'fournisseur_iban_3', 'fournisseur_num_tva', 'fournisseur_num_entreprise']
    ]
];




$result = [];

foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {

    $sheet_name = trim($worksheet->getTitle());

    if(!isset($map[$dataImport['import_type']][$sheet_name])) {
        continue;
    }

    $headers = $map[$dataImport['import_type']][$sheet_name];
    $rows = $worksheet->toArray(null, true, true, true);

    // skip first line
    array_shift($rows);

    $mappedData = [];
    foreach($rows as $row) {
        $record = [];
        foreach($headers as $index => $key_name) {
            $col = chr(65 + $index);
            $value = $row[$col] ?? null;
            $record[$key_name] = $mapXlsToJson($dataImport['import_type'], $sheet_name, $key_name, $value);
        }
        // ignore empty rows
        if(array_filter($record)) {
            $mappedData[] = $record;
        }
    }

    $result[$sheet_name] = $mappedData;
}


$context->httpResponse()
        ->body($result)
        ->send();
