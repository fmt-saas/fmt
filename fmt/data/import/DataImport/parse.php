<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\import\DataImport;
use PhpOffice\PhpSpreadsheet\IOFactory;

[$params, $providers] = eQual::announce([
    'description'   => "Parses a data import to return its content as JSON.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "Identifier of the targeted DataImport object.",
            'foreign_object'    => 'fmt\governance\DataImport',
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
        case preg_match('/iban/i', $field):
        case preg_match('/_iban/i', $field):
            return preg_replace('/[^A-Z0-9]/i', '', strtoupper($value));

        // Email
        case preg_match('/email/i', $field):
        case preg_match('/_email/i', $field):
            return strtolower($value);

        // Phone (remove spaces and symbols)
        case preg_match('/phone|mobile/i', $field):
        case preg_match('/_phone|_tel|_mobile/i', $field):
            return preg_replace('/[^0-9+]/', '', $value);


        // Dates (attempt to convert to ISO format)
        case preg_match('/year_/i', $field):
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
                    if(in_array($field, ['owner_pays', 'owner_langue'])) {
                        return strtoupper($value);
                    }
                    break;

                case 'Ownership_histo':
                    // check coherence of dates
                    if(preg_match('/date_/', $field)) {
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
    trigger_error("APP::unable to load data from given XLSX with PhpOffice: " . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('failed_loading_xlsx', EQ_ERROR_UNKNOWN);
}

//3) parse XLSX data into JSON structure
$map = [
    'condominium_import' => [
        'Condominium'               => ['code', 'name', 'registration_number', 'cadastral_number', 'has_vat', 'vat_number', 'street', 'zip', 'city', 'country', 'lang', 'manager_code', 'accountant_code', 'fiscal_year_start', 'fiscal_year_end', 'fiscal_period', 'expense_mode', 'chart_accounts_code'],
        'Bank_accounts'             => ['description', 'type', 'iban', 'is_primary'],
        'External_representatives'  => ['code', 'type', 'lastname', 'firstname', 'title', 'street', 'zip', 'city', 'country', 'lang', 'phone_1', 'phone_2', 'mobile_1', 'mobile_2', 'email_1', 'email_2', 'iban_1', 'iban_2', 'iban_3', 'date_of_birth', 'citizen_identification', 'vat_number', 'registration_number'],
        'Owners'                    => ['code', 'type', 'lastname', 'firstname', 'title', 'street', 'zip', 'city', 'country', 'lang', 'phone_1', 'phone_2', 'mobile_1', 'mobile_2', 'email_1', 'email_2', 'iban_1', 'iban_2', 'iban_3', 'date_of_birth', 'citizen_identification', 'vat_number', 'registration_number'],
        'Ownerships'                => ['code', 'owner_code', 'shares_full_property', 'shares_bare_property', 'shares_usufruct', 'representative_owner_code', 'external_representative_code', 'extref'],
        'Ownerships_com_prefs'      => ['ownership_code', 'general_assembly_call', 'general_assembly_minutes', 'expense_statement', 'fund_request', 'technical_communication', 'ownership_title'],
        'Entrances'                 => ['code', 'name', 'street', 'zip', 'city', 'country'],
        'Lots'                      => ['code', 'ref', 'nature', 'entrance_code', 'floor', 'column', 'letterbox', 'area', 'primary_lot_code', 'cadastral_number'],
        'Ownerships_history'        => ['lot_code', 'ownership_code', 'date_from', 'date_to'],
        'Apport_keys'               => ['code', 'description', 'total_shares'],
        'Apport_shares'             => ['apport_key_code', 'lot_code', 'lot_shares'],
        'Supplierships'             => ['supplier_code']
    ],
    'suppliers_import' => [
        'suppliers' => ['legal_name', 'short_name', 'street', 'zip', 'city', 'country', 'phone_1', 'phone_2', 'mobile_1', 'email_1', 'email_2', 'iban_1', 'iban_2', 'iban_3', 'vat_number', 'registration_number']
    ],
    'banks_import' => [
        'bank'      => ['legal_name', 'short_name', 'street', 'zip', 'city', 'country', 'phone_1', 'phone_2', 'email_1', 'email_2', 'website', 'vat_number', 'registration_number', 'bic']
    ]
];


$result = [];

foreach($spreadsheet->getWorksheetIterator() as $worksheet) {

    $sheet_name = trim($worksheet->getTitle());

    if(count($map[$dataImport['import_type']]) > 1 && !isset($map[$dataImport['import_type']][$sheet_name])) {
        continue;
    }

    $headers = $map[$dataImport['import_type']][$sheet_name] ?? current($map[$dataImport['import_type']]);
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
