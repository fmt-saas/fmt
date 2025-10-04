<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use equal\text\TextTransformer;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

[$params, $providers] = eQual::announce([
    'description'   => "Parse a normalized XLS file and returns it as a list of statement lines.",
    'help'          => "The input is expected to follow the linear structure provided by ISABEL exports.",
    'params'        => [
        'data' =>  [
            'type'          => 'binary',
            'description'   => "Raw XLS(X) binary data to parse as statements.",
            // 'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

// #todo
/*
il faudrait préciser le type de fochier XLS : ISABEL est un des choix 
-> la configuration permet l'extraction des informations

*/

/**
 * This controller converts a XLSX formatted statement to a JSON structure.
 *
 * The input is expected to follow the linear structure provided by ISABEL exports:
 *
 * 'Account'
 * 'Account holder'
 * 'Bank'
 * 'Account type'
 * 'Bic'
 * 'Type of account information'
 * 'Statement number'
 * 'Statement currency'
 * 'Opening balance date'
 * 'Opening balance'
 * 'Closing balance date'
 * 'Closing balance'
 * 'Closing available balance'
 * 'Entry date'
 * 'Value date'
 * 'Transaction amount'
 * 'Transaction currency'
 * 'Transaction type'
 * 'Client reference'
 * 'Structured Reference'
 * 'Unstructured Reference'
 * 'Bank reference'
 * 'Counterparty name'
 * 'Counterparty account'
 * 'Counterparty bank BIC'
 * 'Counterparty data'
 * 'Transaction message'
 * 'Sequence number'
 * 'Reception Date/Time'
 * 'stFreeMessage'
 *
 *
 * Target structure follows the `bank-statement` standard JSON Schema:
 *
 * JSON Schema (https://json-schema.org/draft/2020-12/schema):
 * {
 *   "$schema": "https://json-schema.org/draft/2020-12/schema",
 *   "$id": "https://example.com/schemas/bank-statement.json",
 *   "title": "Bank Statement",
 *   "type": "object",
 *   "required": [
 *     "account_iban", "statement_number", "opening_balance", "opening_date",
 *     "closing_balance", "closing_date", "statement_currency",
 *     "bank_bic", "account_holder", "account_type", "transactions"
 *   ],
 *   "properties": {
 *     "account_iban": { "type": "string", "pattern": "^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$" },
 *     "statement_number": { "type": "string" },
 *     "opening_balance": { "type": "number" },
 *     "opening_date": { "type": "string", "format": "date" },
 *     "closing_balance": { "type": "number" },
 *     "closing_date": { "type": "string", "format": "date" },
 *     "statement_currency": { "type": "string", "pattern": "^[A-Z]{3}$" },
 *     "bank_bic": { "type": "string", "pattern": "^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$" },
 *     "account_holder": { "type": "string" },
 *     "account_type": { "type": "string", "enum": ["current", "savings", "loan"] },
 *     "transactions": {
 *       "type": "array",
 *       "minItems": 1,
 *       "items": {
 *         "type": "object",
 *         "required": [
 *           "entry_date", "value_date", "amount", "currency",
 *           "transaction_type", "sequence_number"
 *         ],
 *         "properties": {
 *           "entry_date": { "type": "string", "format": "date" },
 *           "value_date": { "type": "string", "format": "date" },
 *           "amount": { "type": "number" },
 *           "currency": { "type": "string", "pattern": "^[A-Z]{3}$" },
 *           "transaction_type": { "type": "string" },
 *           "sequence_number": { "type": "integer" },
 *           "mandate_id": { "anyOf": [{ "type": "string" }, { "type": "null" }] },
 *           "client_reference": { "anyOf": [{ "type": "string" }, { "type": "null" }] },
 *           "structured_reference": { "anyOf": [{ "type": "string" }, { "type": "null" }] },
 *           "bank_reference": { "anyOf": [{ "type": "string" }, { "type": "null" }] },
 *           "unstructured_reference": { "anyOf": [{ "type": "string" }, { "type": "null" }] },
 *           "counterparty_name": { "anyOf": [{ "type": "string" }, { "type": "null" }] },
 *           "counterparty_iban": {
 *             "anyOf": [
 *               { "type": "string", "pattern": "^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$" },
 *               { "type": "null" }
 *             ]
 *           },
 *           "counterparty_bic": {
 *             "anyOf": [
 *               { "type": "string", "pattern": "^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$" },
 *               { "type": "null" }
 *             ]
 *           },
 *           "counterparty_details": { "anyOf": [{ "type": "string" }, { "type": "null" }] },
 *           "transaction_message": { "anyOf": [{ "type": "string" }, { "type": "null" }] }
 *         }
 *       }
 *     }
 *   }
 * }
 *
 * Example:
 * {
 *   "account_iban": "BE71 0961 2345 6769",
 *   "statement_number": "0000123456",
 *   "opening_balance": 1000.00,
 *   "opening_date": "2024-05-01",
 *   "closing_balance": 1200.00,
 *   "closing_date": "2024-05-10",
 *   "statement_currency": "EUR",
  *   "bank_bic": "CREGBEBB",
 *   "account_holder": "ASBL Kaleo",
 *   "account_type": "current",
 *   "transactions": [
 *     {
 *       "entry_date": "2024-05-05",
 *       "value_date": "2024-05-05",
 *       "amount": -150.00,
 *       "currency": "EUR",
 *       "transaction_type": "sepa_direct_debit",
 *       "sequence_number": 123,
 *       "received_at": "2024-05-05T10:45:00Z",
 *       "mandate_id": "MANDATE-2023-XYZ",
 *       "client_reference": "Facture 2024-87",
 *       "structured_reference": "+++123/4567/89012+++",
 *       "bank_reference": "987654321",
 *       "unstructured_reference": "Paiement pour facture avril",
 *       "counterparty_name": "EDF Luminus",
 *       "counterparty_iban": "BE23 0910 1111 2222",
 *       "counterparty_bic": "GEBA BE BB",
 *       "counterparty_details": "Rue de l'Énergie, Liège",
 *       "transaction_message": "Paiement automatique"
 *     }
 *   ]
 * }
 */

['context' => $context, 'auth' => $auth] = $providers;

$convertBbanToIban = function($account_number) {

    $account_number = str_replace(['-', ' '], '', $account_number);

    // account number already has IBAN format
    if( !is_numeric(substr($account_number, 0, 2)) ) {
        return $account_number;
    }

    // create numeric code of the target country
    $country_code = 'BE';

    $code_alpha = $country_code;
    $code_num = '';

    for($i = 0; $i < strlen($code_alpha); ++$i) {
        $letter = substr($code_alpha, $i, 1);
        $order = ord($letter) - ord('A');
        $code_num .= '1' . $order;
    }

    $check_digits = substr($account_number, -2);
    $dummy = intval($check_digits . $check_digits . $code_num . '00');
    $control = 98 - ($dummy % 97);

    return trim(sprintf("BE%02d%s", $control, $account_number));
};

$getBicFromIban = function($iban) {
    static $map_bic;
    $result = null;

    if(!$iban) {
        return null;
    }

    $country = substr($iban, 0, 2);
    $bank_code = substr($iban, 4, 3);

    if(!$map_bic) {
        $file = EQ_BASEDIR . "/packages/identity/i18n/en/bic/{$country}.json";
        if(file_exists($file)) {
            $data = file_get_contents($file);
            $map_bic = json_decode($data, true);
        }
    }

    $result = $map_bic[$bank_code]['bic'] ?? null;

    return $result;
};


$adapters = [
    // returns one of the supported account typ values: 'current', 'savings', 'loan'
    'account_type_normalize' => function($type) {
        static $map_account_types = [
            'current' => [
                'a vue',
                'courant',
                'current',
                'checking',
                'zichtrekening',
                'betaalrekening',
                'rekening-courant'
            ],
            'savings' => [
                'epargne',
                'livret',
                'savings',
                'deposit',
                'interest',
                'spaarrekening',
                'depositoboekje',
                'spaardeposito',
                'spaarboekje',
                'getrouwheidsrekening',
                'depositorekening'
            ]
        ];

        $type = strtolower(TextTransformer::toAscii($type));

        foreach($map_account_types['current'] as $needle) {
            if(strpos($type, $needle) !== false) {
                return 'current';
            }
        }
        foreach($map_account_types['savings'] as $needle) {
            if(strpos($type, $needle) !== false) {
                return 'savings';
            }
        }
        return 'current';
    },
    'iban_normalize' => function($account_number) {
        if(empty($account_number)) {
            return null;
        }

        $account_number = strtoupper(trim($account_number));

        if(preg_match('/^([A-Z]{2}[0-9]{2}[A-Z0-9]{10,30})/', $account_number, $matches)) {
            $account_number = $matches[1];
        }

        if(preg_match('/^([A-Z]{2}[0-9]{2}[A-Z0-9]{10,30})([A-Z]{3})$/', $account_number, $matches)) {
            $account_number = $matches[1];
        }

        $prefix = substr($account_number, 0, 2);
        if(!preg_match('/^[A-Z]{2}$/', $prefix)) {
            return null;
        }

        $remainder = substr($account_number, 2);
        $remainder = preg_replace('/[^A-Z0-9]/i', '', $remainder);

        return $prefix . $remainder;
    },
    'bic_normalize' => function($bic) {
        return strtoupper(trim($bic));
    },
    'payment_reference_normalize' => function($reference) {
        return strtoupper(preg_replace('/[^a-z0-9]/i', '', $reference));
    },
    // converts transaction type to a standardized code (string)
    'transaction_type_normalize' => function($transaction_type) {
        $result = $transaction_type;
        // CODA syntax is : %02{family} %02{operation} %03{section}
        // CAMT.053 uses domain, family, subfamily

        /*
        #memo - this list is incomplete and is only meant for XLS/CODA format extraction (from ISABEL XLSX)
        */
        static $coda_transaction_codes = [
            // incoming transfers
            '01'   => 'credit',
            '0101' => 'credit_out',
            '0150' => 'credit_in',
            '0250' => 'credit_in',    // instant
            // payments
            '05'   => 'debit',
            '0501' => 'debit_out',
            '0505' => 'debit_in',
            // account closure
            '3501' => 'account_closure',
            '3537' => 'closing_fee',
            '3550' => 'account_closure_credit',
            // fees
            '8002' => 'electronic_fee',
            '8035' => 'tax_fee',
            '8037' => 'database_access_fee',
            '8039' => 'guarantee_fee',
            '8041' => 'research_fee',
            '8043' => 'printing_fee',
            '8045' => 'documentary_credit_fee',
            '8087' => 'fee_reimbursement'
        ];

        $haystack = str_replace(' ', '', $transaction_type);

        // CODA format
        if(preg_match('/\b(\d{2})(\d{2})\b/', $haystack, $matches)) {
            $code = $matches[1] . $matches[2];
            if(isset($coda_transaction_codes[$code])) {
                return $coda_transaction_codes[$code];
            }
        }
        elseif(preg_match('/\b(\d{2})\b/', $haystack, $matches)) {
            if(isset($coda_transaction_codes[$matches[1]])) {
                return $coda_transaction_codes[$matches[1]];
            }
        }
        else {
            // other format
        }
        return $result;
    },
    'string_trim' => function($str) {
        return trim((string) $str);
    },
    'string_upper' => function($str) {
        return strtoupper($str);
    },
    'date_parse' => function($timestamp) {
        return date('c', $timestamp);
    }
];

$map_xls_fields = [
    'Account' => [
        'target'  => 'account_iban',
        'adapter' => 'iban_normalize'
    ],
    'Account holder' => [
        'target'  => 'account_holder',
        'adapter' => 'string_trim',
    ],
    'Bank' => [
        'target'  => 'bank_name',
        'adapter' => 'string_trim',
    ],
    'Account type' => [
        'target'  => 'account_type',
        'adapter' => 'account_type_normalize'
    ],
    'Bic' => [
        'target'  => 'bank_bic',
        'adapter' => 'bic_normalize'
    ],
    'Statement number' => [
        'target'  => 'statement_number',
        'adapter' => 'string_trim'
    ],
    'Statement currency' => [
        'target'  => 'statement_currency',
        'adapter' => 'string_upper'
    ],
    'Opening balance date' => [
        'target'  => 'opening_date',
        'adapter' => 'date_parse',
    ],
    'Opening balance' => [
        'target'  => 'opening_balance'
    ],
    'Closing balance date' => [
        'target'  => 'closing_date',
        'adapter' => 'date_parse',
    ],
    'Closing balance' => [
        'target'  => 'closing_balance'
    ],
    'Entry date' => [
        'target'  => 'entry_date',
        'adapter' => 'date_parse',
    ],
    'Value date' => [
        'target'  => 'value_date',
        'adapter' => 'date_parse',
    ],
    'Transaction amount' => [
        'target'  => 'amount'
    ],
    'Transaction currency' => [
        'target'  => 'currency',
        'adapter' => 'string_upper',
    ],
    'Transaction type' => [
        'target'  => 'transaction_type',
        'adapter' => 'transaction_type_normalize',
    ],
    'Client reference' => [
        'target'  => 'client_reference',
        'adapter' => 'string_trim',
    ],
    'Structured Reference' => [
        'target'  => 'structured_reference',
        'adapter' => 'payment_reference_normalize',
    ],
    'Unstructured Reference' => [
        'target'  => 'unstructured_reference',
        'adapter' => 'string_trim',
    ],
    'Bank reference' => [
        'target'  => 'bank_reference',
        'adapter' => 'string_trim',
    ],
    'Counterparty name' => [
        'target'  => 'counterparty_name',
        'adapter' => 'string_trim',
    ],
    'Counterparty account' => [
        'target'  => 'counterparty_iban',
        'adapter' => 'iban_normalize',
    ],
    'Counterparty bank BIC' => [
        'target'  => 'counterparty_bic',
        'adapter' => 'bic_normalize',
    ],
    'Counterparty data' => [
        'target'  => 'counterparty_details',
        'adapter' => 'string_trim',
    ],
    'Transaction message' => [
        'target'  => 'transaction_message',
        'adapter' => 'string_trim',
    ],
    'Sequence number' => [
        'target'  => 'sequence_number'
    ]
];



$lines = [];

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

foreach($worksheet->getRowIterator() as $rowIterator) {
    $row = [];
    foreach($rowIterator->getCellIterator() as $cell) {

        $format = $cell->getStyle()->getNumberFormat()->getFormatCode();
        $value = $cell->getValue();

        // support for RichText cells
        if(is_object($value)) {
            if(class_exists('\PhpOffice\PhpSpreadsheet\RichText\RichText')
                && $value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText
            ) {
                $value = $value->getPlainText();
            }
            elseif(method_exists($value, '__toString')) {
                $value = (string) $value;
            }
            else {
                $value = null;
            }
        }

        if(XlsDate::isDateTime($cell)) {
            $date = XlsDate::excelToDateTimeObject($value);
            $value = $date->getTimestamp();
        }
        $row[] = $value;
    }
    $lines[] = $row;
}

// array for knowing if a column should be part of the statement or of one of its transaction
$statement_fields = ['account_iban','statement_number','opening_balance','opening_date','closing_balance','closing_date','statement_currency','bank_bic','account_holder','account_type'];

$statements = [];

$headers = $lines[0];

// remember info for grouping lines by statement
$statement = [];
$previous_account = null;
$previous_statement_number = null;


for($i = 1, $n = count($lines); $i < $n; ++$i) {

    $line = $lines[$i];
    // if entry does not belong to current statement start a new statement
    if($previous_account !== null && ($line[0] != $previous_account || $line[6] != $previous_statement_number)) {
        // fix missing mandatory values
        if(empty($statement['bank_bic'])) {
            $statement['bank_bic'] = $getBicFromIban($statement['account_iban']);
        }

        $statements[] = $statement;
        $statement = [
            'transactions' => []
        ];
    }

    // build entry
    $entry = [];
    foreach($headers as $j => $header) {
        if(!isset($map_xls_fields[$header]['target'])) {
            continue;
        }
        $adapter = $map_xls_fields[$header]['adapter'] ?? null;
        $target = $map_xls_fields[$header]['target'];

        $value = $line[$j] ?? null;

        // handle special case for account IBAN used as an account identifier, potentially holding non-SEPA standard data
        if($target === 'account_iban') {
            if(preg_match('/-/', $value)) {
                $parts = explode('-', str_replace(' ', '', $value), 2);
                $statement['account_suffix'] = $parts[1];
            }
        }

        if($adapter && is_callable($adapters[$adapter])) {
            $value = $adapters[$adapter]($value);
        }

        if(in_array($target, $statement_fields)) {
            $statement[$target] = $value;
        }
        else {
            $entry[$target] = $value;
        }
    }

    // fix missing mandatory values
    if(empty($entry['counterparty_bic'])) {
        $entry['counterparty_bic'] = $getBicFromIban($entry['counterparty_iban']);
    }

    $previous_account = $line[0];
    $previous_statement_number = $line[6];

    $statement['transactions'][] = $entry;
}

// handle current/last statement

// fix missing mandatory values
if(empty($statement['bank_bic'])) {
    $statement['bank_bic'] = $getBicFromIban($statement['account_iban']);
}

$statements[] = $statement;


$result = $statements;

$context->httpResponse()
        ->body($result)
        ->send();
