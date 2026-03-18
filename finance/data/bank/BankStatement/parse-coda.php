<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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
 *   "account_iban": "BE71 0961 2345 6789",
 *   "statement_number": "0000123456",
 *   "opening_balance": 1000.00,
 *   "opening_date": "2024-05-01",
 *   "closing_balance": 1200.00,
 *   "closing_date": "2024-05-10",
 *   "statement_currency": "EUR",
 *   "bank_bic": "CREGBEBB",
 *   "account_holder": "FMT solutions",
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
    // CODA syntax is : %02{family} %02{operation} %03{section}
    // CAMT.053 uses domain, family, subfamily

    /*
     * #memo - this list is incomplete, but there is a fallback on family (first 2 digits)
     */
    static $coda_transaction_codes = [

        // --------------------------------------------------
        // 01 — SEPA transfers
        // --------------------------------------------------
        '01'   => 'credit_transfer',

        '0101' => 'credit_transfer_out',
        '0102' => 'credit_transfer_out_bank',
        '0103' => 'standing_order_out',
        '0105' => 'salary_payment',
        '0107' => 'bulk_transfer_out',
        '0113' => 'internal_transfer_out',
        '0117' => 'financial_centralisation_out',
        '0137' => 'transfer_fee',

        '0150' => 'credit_transfer_in',
        '0151' => 'credit_transfer_in_bank',
        '0152' => 'third_party_payment',
        '0154' => 'rejected_transfer',
        '0164' => 'internal_transfer_in',
        '0166' => 'financial_centralisation_in',
        '0187' => 'fee_reimbursement',

        // --------------------------------------------------
        // 02 — Instant SEPA transfers
        // --------------------------------------------------
        '02'   => 'instant_credit_transfer',

        '0201' => 'instant_transfer_out',
        '0203' => 'instant_standing_order_out',
        '0205' => 'instant_salary_payment',
        '0213' => 'instant_internal_transfer_out',
        '0237' => 'instant_transfer_fee',

        '0250' => 'instant_transfer_in',
        '0251' => 'instant_transfer_in_bank',
        '0252' => 'instant_third_party_payment',
        '0264' => 'instant_internal_transfer_in',
        '0266' => 'instant_financial_centralisation_in',
        '0287' => 'instant_fee_reimbursement',

        // --------------------------------------------------
        // 03 — Cheques
        // --------------------------------------------------
        '03'   => 'cheque',

        '0301' => 'cheque_payment',
        '0305' => 'voucher_payment',
        '0311' => 'store_cheque',
        '0315' => 'bank_cheque_issue',
        '0317' => 'certified_cheque',
        '0337' => 'cheque_fee',
        '0338' => 'cheque_unpaid',

        '0352' => 'cheque_credit_pending',
        '0358' => 'cheque_credit',
        '0362' => 'cheque_reversal',
        '0363' => 'cheque_second_credit',
        '0387' => 'cheque_fee_reimbursement',

        // --------------------------------------------------
        // 04 — Cards
        // --------------------------------------------------
        '04'   => 'card',

        '0402' => 'card_payment_eu',
        '0403' => 'credit_card_settlement',
        '0404' => 'atm_withdrawal',
        '0406' => 'fuel_card_payment',
        '0408' => 'card_payment_foreign',
        '0437' => 'card_fee',

        '0450' => 'card_payment_received',
        '0453' => 'atm_deposit',
        '0487' => 'card_fee_reimbursement',

        // --------------------------------------------------
        // 05 — Direct debit
        // --------------------------------------------------
        '05'   => 'direct_debit',

        '0501' => 'direct_debit_payment',
        '0503' => 'direct_debit_unpaid',
        '0505' => 'direct_debit_refund',
        '0537' => 'direct_debit_fee',

        '0550' => 'direct_debit_credit',
        '0552' => 'direct_debit_credit_pending',
        '0554' => 'direct_debit_refund_credit',
        '0558' => 'direct_debit_reversal',
        '0587' => 'direct_debit_fee_reimbursement',

        // --------------------------------------------------
        // 07 — Bills of exchange
        // --------------------------------------------------
        '07'   => 'bill_of_exchange',

        '0701' => 'bill_payment',
        '0707' => 'bill_unpaid',
        '0737' => 'bill_fee',

        '0750' => 'bill_credit_after_collection',
        '0752' => 'bill_credit_pending',
        '0754' => 'bill_discount',
        '0787' => 'bill_fee_reimbursement',

        // --------------------------------------------------
        // 09 — Cash operations
        // --------------------------------------------------
        '09'   => 'cash',

        '0901' => 'cash_withdrawal',
        '0913' => 'branch_cash_withdrawal',
        '0937' => 'cash_fee',

        '0950' => 'cash_deposit',
        '0952' => 'night_safe_deposit',
        '0958' => 'branch_cash_deposit',
        '0987' => 'cash_fee_reimbursement',

        // --------------------------------------------------
        // 11 — Securities
        // --------------------------------------------------
        '11'   => 'securities',

        '1101' => 'securities_purchase',
        '1103' => 'securities_subscription',
        '1117' => 'securities_management_fee',
        '1137' => 'securities_fee',

        '1150' => 'securities_sale',
        '1152' => 'coupon_payment',
        '1168' => 'missing_coupon_compensation',
        '1187' => 'securities_fee_reimbursement',

        // --------------------------------------------------
        // 13 — Loans
        // --------------------------------------------------
        '13'   => 'loan',

        '1301' => 'loan_repayment_short_term',
        '1302' => 'loan_repayment_long_term',
        '1311' => 'mortgage_repayment',
        '1337' => 'loan_fee',

        '1350' => 'loan_payment_received',
        '1360' => 'mortgage_payment_received',
        '1362' => 'term_loan',
        '1387' => 'loan_fee_reimbursement',

        // --------------------------------------------------
        // 30 — Miscellaneous
        // --------------------------------------------------
        '30'   => 'misc',

        '3001' => 'fx_purchase_spot',
        '3003' => 'fx_purchase_forward',
        '3005' => 'term_deposit_payment',
        '3037' => 'misc_fee',

        '3050' => 'fx_sale_spot',
        '3052' => 'fx_sale_forward',
        '3054' => 'term_deposit_credit',
        '3087' => 'misc_fee_reimbursement',

        // --------------------------------------------------
        // 35 — Account closing / periodic settlement
        // --------------------------------------------------
        '35'   => 'account_closure',

        '3501' => 'account_closure',
        '3537' => 'closing_fee',
        '3550' => 'account_closure_credit',
        '3587' => 'closing_fee_reimbursement',

        // --------------------------------------------------
        // 41 — International transfers
        // --------------------------------------------------
        '41'   => 'international_transfer',

        '4101' => 'international_transfer_out',
        '4103' => 'international_standing_order',
        '4113' => 'international_internal_transfer',
        '4137' => 'international_transfer_fee',

        '4150' => 'international_transfer_in',
        '4164' => 'international_internal_transfer_in',
        '4166' => 'international_centralisation',
        '4187' => 'international_fee_reimbursement',

        // --------------------------------------------------
        // 80 — Fees
        // --------------------------------------------------
        '80'   => 'bank_fee',

        '8002' => 'electronic_fee',
        '8007' => 'insurance_fee',
        '8009' => 'postage_fee',
        '8013' => 'safe_deposit_fee',
        '8023' => 'research_fee',
        '8033' => 'bank_commission',
        '8035' => 'tax_fee',
        '8037' => 'database_access_fee',
        '8039' => 'guarantee_fee',
        '8041' => 'research_fee',
        '8043' => 'printing_fee',
        '8045' => 'documentary_credit_fee',
        '8049' => 'fee_correction_debit',

        '8099' => 'fee_correction_credit',
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

foreach($statements as $statement) {

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

    foreach($statement->getTransactions() as $transaction) {

        $transaction_account = $transaction->getAccount();

        $counterparty_iban = $convertBbanToIban($transaction->getAccount()->getNumber());
        $counterparty_bic = $transaction->getAccount()->getBic();

        $line['transactions'][] = [
            'entry_date'                => date('c', $transaction->getTransactionDate()->getTimestamp()),
            'value_date'                => date('c', $transaction->getValutaDate()->getTimestamp()),
            'sequence_number'           => $transaction->getStatementSequence(),
            'amount'                    => $transaction->getAmount(),
            'counterparty_name'         => $transaction->getAccount()->getName(),
            'counterparty_iban'         => trim($counterparty_iban) !== '' ? $counterparty_iban : null,
            'counterparty_bic'          => trim($counterparty_bic)  !== '' ? $counterparty_bic : null,
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
