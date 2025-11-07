<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => 'Performs a document analysis for a bank statement, and return the a result as a JSON descriptor.',
    'params'        => [
        'document_id' =>  [
            'type'          => 'integer',
            'description'   => 'Identifier of the document to parse.',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$document = Document::id($params['document_id'])->read(['content_type', 'data'])->first();

if(!$document) {
    throw new Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
}

$supported_content_types = [
        'text/plain',
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

if(!in_array($document['content_type'], $supported_content_types)) {
    throw new Exception('non_supported_document_type', EQ_ERROR_INVALID_PARAM);
}

switch($document['content_type']) {
    case 'application/octet-stream':
    case 'text/plain':
        $result = eQual::run('get', 'finance_bank_BankStatement_parse-coda', ['data' => $document['data']], false, true);
        break;
    case 'application/vnd.ms-excel':
    case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
        $result = eQual::run('get', 'finance_bank_BankStatement_parse-xls', ['data' => base64_encode($document['data'])], false, true);
        break;
}

$context->httpResponse()
        ->body($result, true)
        ->send();

/**
 * Returned response example:
 *
 * [
 *   {
 *     "account_iban": "BE71 0961 2345 6789",                // IBAN of the account
 *     "statement_number": "0000123456",                     // Unique statement identifier
 *     "opening_balance": 1000.00,                           // Balance at the beginning of the statement period
 *     "opening_date": "2024-05-01",                         // Date when the statement period starts
 *     "closing_balance": 1200.00,                           // Balance at the end of the statement period
 *     "closing_date": "2024-05-10",                         // Date when the statement period ends
 *     "statement_currency": "EUR",                          // Currency used for all amounts
 *     "bank_bic": "CREGBEBB",                               // BIC (Bank Identifier Code) of the issuing bank
 *     "account_holder": "FMT solutions",                    // Name of the account holder
 *     "account_type": "current",                            // Type of account (e.g. current, savings)
 *     "transactions": [                                     // List of transactions included in the statement
 *       {
 *         "entry_date": "2024-05-05",                       // Date the transaction was recorded
 *         "value_date": "2024-05-05",                       // Date the transaction becomes effective
 *         "amount": -150.00,                                // Transaction amount (negative = debit, positive = credit)
 *         "currency": "EUR",                                // Currency of the transaction
 *         "transaction_type": "sepa_direct_debit",          // Transaction type (e.g. SEPA direct debit, transfer)
 *         "sequence_number": 123,                           // Internal transaction sequence number
 *         "received_at": "2024-05-05T10:45:00Z",            // Timestamp when the transaction was received (UTC)
 *         "mandate_id": "MANDATE-2023-XYZ",                 // Mandate identifier for SEPA direct debit
 *         "client_reference": "Facture 2024-87",            // Reference provided by the client
 *         "structured_reference": "+++123/4567/89012+++",   // Structured communication reference
 *         "bank_reference": "987654321",                    // Reference provided by the bank
 *         "unstructured_reference": "Paiement facture mai", // Free-text communication
 *         "counterparty_name": "EDF Luminus",               // Name of the transaction counterparty
 *         "counterparty_iban": "BE23 0910 1111 2222",       // IBAN of the transaction counterparty
 *         "counterparty_bic": "GEBA BE BB",                 // BIC of the transaction counterparty
 *         "counterparty_details": "Rue de l'Énergie, Liège",// Additional counterparty details (e.g. address)
 *         "transaction_message": "Paiement automatique"     // Message or label associated with the transaction
 *       }
 *     ]
 *   }
 * ]
 */
