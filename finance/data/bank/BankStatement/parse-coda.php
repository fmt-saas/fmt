<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use Codelicious\Coda\Parser;
use equal\text\TextTransformer;

list($params, $providers) = eQual::announce([
    'description'   => "Parse a raw CODA file and returns it as a list of statement lines.",
    'params'        => [
        'data' =>  [
            'type'          => 'string',
            'description'   => "Raw CODA data to parse as statements.",
            'usage'         => 'text/plain',
            'required'      => true
        ],
        'lang' => [
            'type'          => 'string',
            'description'   => "ISO code of lang (for bank names).",
            'usage'         => 'text/plain:2',
            'default'       => 'en'
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

/**
 * This controller converts a CODA formatted statement to a JSON structure
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

$getTransactionType = function($family, $operation) {
    $result = $family . $operation;
    // CODA syntax is : %02{famille} %02{operation} %03{rubrique}
    // CAMT.053 uses domain, family, subfamily

    /*
    #memo - this list is incomplete and is only meant for CODA format extraction (from ISABEL XLSX)
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

    // CODA format
    $code = $family . $operation;
    if(isset($coda_transaction_codes[$code])) {
        return $coda_transaction_codes[$code];
    }
    $code = $family;
    if(isset($coda_transaction_codes[$code])) {
        return $coda_transaction_codes[$code];
    }
    else {
        // other format
    }
    return $result;
};

$content = $params['data'];

$content = str_replace("\r\n", "\n", $content);
$lines = explode("\n", $content);

// #memo - parser expects ASCII-compatible chars
// latin chars from non ASCII/UTF-8 charsets (e.g. ISO-8859-1) make the parser to return an empty set of statements)
$lines = array_map( function($line) {
            return TextTransformer::toAscii($line);
        },
        $lines
    );

$parser = new Parser();
$statements = $parser->parse($lines);

$result = [];

foreach ($statements as $statement) {

    $account = $statement->getAccount();

    $line = [
        'account_iban'       => $convertBbanToIban($account->getNumber()),
        'statement_number'   => $statement->getSequenceNumber(),
        'opening_balance'    => $statement->getInitialBalance(),
        'opening_date'       => date('c', $statement->getDate()->getTimestamp()),
        'closing_balance'    => $statement->getNewBalance(),
        'closing_date'       => date('c', $statement->getNewDate()->getTimestamp()),
        'statement_currency' => $account->getCurrencyCode(),
        'bank_bic'           => $account->getBic(),
        'account_holder'     => $account->getName(),
        'account_type'       => 'current'
    ];

    $line['transactions'] = [];

    foreach ($statement->getTransactions() as $transaction) {

        $transaction_account = $transaction->getAccount();

        $line['transactions'][] = [
            'entry_date'                => date('c', $transaction->getTransactionDate()->getTimestamp()),
            'value_date'                => date('c', $transaction->getValutaDate()->getTimestamp()),
            'sequence_number'           => $transaction->getStatementSequence(),
            'amount'                    => $transaction->getAmount(),
            'counterparty_name'         => $account->getName(),
            'counterparty_iban'         => $convertBbanToIban($account->getNumber()),
            'counterparty_bic'          => $account->getBic(),
            'counterparty_details'      => '',
            'mandate_id'                => null,
            'transaction_type'          => $getTransactionType($transaction->getTransactionCode()->getFamily(), $transaction->getTransactionCode()->getOperation()),
            'structured_reference'      => $transaction->getStructuredMessage(),
            'client_reference'          => $transaction->getClientReference(),
            'unstructured_reference'    => preg_replace('/\s+/', ' ', trim($transaction->getMessage())),
            'currency'                  => 'EUR',
            'bank_reference'            => '',
            'transaction_message'       => ''
        ];

    }

    $result[] = $line;
}

$context->httpResponse()
        ->body($result)
        ->send();
