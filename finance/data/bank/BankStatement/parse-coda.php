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
 *       "transaction_type": "transfer",
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
 *
 * CODA transaction types families
 *
 * | Code | CODA family                                           |
 * | ---: | ----------------------------------------------------- |
 * | `01` | Domestic/local transfers — SEPA Credit Transfers      |
 * | `02` | Instant SEPA Credit Transfers                         |
 * | `03` | Cheques                                               |
 * | `04` | Cards                                                 |
 * | `05` | Direct Debits                                         |
 * | `07` | Domestic bills of exchange                            |
 * | `09` | Counter operations                                    |
 * | `11` | Securities / coupons                                  |
 * | `13` | Credits / loans                                       |
 * | `30` | Miscellaneous operations                              |
 * | `35` | Closing — periodic settlement of interest, fees, etc. |
 * | `41` | International / non-SEPA transfers                    |
 * | `43` | Foreign cheques                                       |
 * | `47` | Foreign bills of exchange                             |
 * | `80` | Fees and commissions charged separately               |
 *
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
    $result = 'transfer';
    // CODA syntax is : %02{family} %02{operation} %03{section}
    // CAMT.053 uses domain, family, subfamily

    /*
     * #memo - this list is incomplete, but there is a fallback on family (first 2 digits)
     */
    static $map_transaction_codes = [

        // --------------------------------------------------
        // 01 — SEPA credit transfers
        // --------------------------------------------------
        '01'   => 'transfer',

        '0101' => 'transfer_out',
        '0102' => 'transfer_out',
        '0103' => 'transfer_out',
        '0105' => 'transfer_out',
        '0107' => 'transfer_out',
        '0113' => 'transfer_out',
        '0117' => 'transfer_out',

        '0137' => 'transfer_fee',

        '0150' => 'transfer_in',
        '0151' => 'transfer_in',
        '0152' => 'transfer_in',
        '0154' => 'transfer_rejected',
        '0164' => 'transfer_in',
        '0166' => 'transfer_in',

        '0187' => 'transfer_fee_reimbursement',


        // --------------------------------------------------
        // 02 — Instant SEPA credit transfers
        // Same business semantics as family 01.
        // --------------------------------------------------
        '02'   => 'transfer',

        '0201' => 'transfer_out',
        '0203' => 'transfer_out',
        '0205' => 'transfer_out',
        '0213' => 'transfer_out',

        '0237' => 'transfer_fee',

        '0250' => 'transfer_in',
        '0251' => 'transfer_in',
        '0252' => 'transfer_in',
        '0264' => 'transfer_in',
        '0266' => 'transfer_in',

        '0287' => 'transfer_fee_reimbursement',


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
        '0338' => 'cheque_rejected',

        '0352' => 'cheque_credit_provisional',
        '0358' => 'cheque_credit',
        '0362' => 'cheque_reversal',
        '0363' => 'cheque_credit',

        '0387' => 'cheque_fee_reimbursement',


        // --------------------------------------------------
        // 04 — Cards
        // --------------------------------------------------
        '04'   => 'card',

        '0402' => 'card_payment',
        '0403' => 'card_payment',
        '0404' => 'card_withdrawal',
        '0406' => 'card_payment',
        '0408' => 'card_payment',

        '0437' => 'card_fee',

        '0450' => 'card_payment_received',
        '0453' => 'card_deposit',

        '0487' => 'card_fee_reimbursement',


        // --------------------------------------------------
        // 05 — Direct debits
        // --------------------------------------------------
        '05'   => 'direct_debit',

        '0501' => 'direct_debit_out',
        '0503' => 'direct_debit_rejected',
        '0505' => 'direct_debit_refund',

        '0537' => 'direct_debit_fee',

        '0550' => 'direct_debit_in',
        '0552' => 'direct_debit_credit_provisional',
        '0554' => 'direct_debit_refund',
        '0558' => 'direct_debit_reversal',

        '0587' => 'direct_debit_fee_reimbursement',


        // --------------------------------------------------
        // 07 — Bills of exchange
        // --------------------------------------------------
        '07'   => 'bill_of_exchange',

        '0701' => 'bill_payment',
        '0707' => 'bill_rejected',

        '0737' => 'bill_fee',

        '0750' => 'bill_credit',
        '0752' => 'bill_credit_provisional',
        '0754' => 'bill_discount',

        '0787' => 'bill_fee_reimbursement',


        // --------------------------------------------------
        // 09 — Cash operations
        // --------------------------------------------------
        '09'   => 'cash',

        '0901' => 'cash_withdrawal',
        '0913' => 'cash_withdrawal',

        '0937' => 'cash_fee',

        '0950' => 'cash_deposit',
        '0952' => 'cash_deposit',
        '0958' => 'cash_deposit',

        '0987' => 'cash_fee_reimbursement',


        // --------------------------------------------------
        // 11 — Securities
        // --------------------------------------------------
        '11'   => 'securities',

        '1101' => 'securities_purchase',
        '1103' => 'securities_purchase',
        '1117' => 'securities_fee',
        '1137' => 'securities_fee',

        '1150' => 'securities_sale',
        '1152' => 'securities_income',
        '1168' => 'securities_income',

        '1187' => 'securities_fee_reimbursement',


        // --------------------------------------------------
        // 13 — Loans / financing
        // --------------------------------------------------
        '13'   => 'loan',

        '1301' => 'loan_repayment',
        '1302' => 'loan_repayment',
        '1311' => 'loan_repayment',

        '1337' => 'loan_fee',

        '1350' => 'loan_disbursement',
        '1360' => 'loan_disbursement',
        '1362' => 'loan_disbursement',

        '1387' => 'loan_fee_reimbursement',


        // --------------------------------------------------
        // 30 — Miscellaneous financial operations
        // --------------------------------------------------
        '30'   => 'misc',

        '3001' => 'fx_purchase',
        '3003' => 'fx_purchase',
        '3005' => 'term_deposit',

        '3037' => 'misc_fee',

        '3050' => 'fx_sale',
        '3052' => 'fx_sale',
        '3054' => 'term_deposit',

        '3087' => 'misc_fee_reimbursement',


        // --------------------------------------------------
        // 35 — Periodic account settlement
        // --------------------------------------------------
        '35'   => 'account_settlement',

        '3501' => 'account_settlement_out',
        '3537' => 'account_settlement_fee',
        '3550' => 'account_settlement_in',
        '3587' => 'account_settlement_fee_reimbursement',


        // --------------------------------------------------
        // 41 — International / non-SEPA credit transfers
        // Same business semantics as families 01 and 02.
        // --------------------------------------------------
        '41'   => 'transfer',

        '4101' => 'transfer_out',
        '4103' => 'transfer_out',
        '4113' => 'transfer_out',

        '4137' => 'transfer_fee',

        '4150' => 'transfer_in',
        '4164' => 'transfer_in',
        '4166' => 'transfer_in',

        '4187' => 'transfer_fee_reimbursement',


        // --------------------------------------------------
        // 80 — Standalone bank fees
        //
        // We deliberately normalize detailed CODA fee types
        // as bank_fee: the original CODA code remains available
        // when the detailed nature of the fee is needed.
        // --------------------------------------------------
        '80'   => 'bank_fee',

        '8002' => 'bank_fee',
        '8007' => 'bank_fee',
        '8009' => 'bank_fee',
        '8013' => 'bank_fee',
        '8023' => 'bank_fee',
        '8033' => 'bank_fee',
        '8035' => 'bank_fee',
        '8037' => 'bank_fee',
        '8039' => 'bank_fee',
        '8041' => 'bank_fee',
        '8043' => 'bank_fee',
        '8045' => 'bank_fee',

        '8049' => 'bank_fee_correction',

        // Keep if observed in actual bank/XLS imports.
        '8087' => 'bank_fee_reimbursement',

        '8099' => 'bank_fee_correction',
    ];

    // CODA format
    $code = $family . $operation;
    if(isset($map_transaction_codes[$code])) {
        return $map_transaction_codes[$code];
    }
    $code = $family;
    if(isset($map_transaction_codes[$code])) {
        return $map_transaction_codes[$code];
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
