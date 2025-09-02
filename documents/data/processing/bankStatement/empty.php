<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
[$params, $providers] = eQual::announce([
    'description'   => 'Return an empty (single) bank-statement JSON descriptor compliant with `urn:fmt:json-schema:finance:bank-statement`.',
    'params'        => [],
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


$output = [
    "account_iban"          => "",                          // IBAN of the account
    "statement_number"      => "",                          // Unique statement identifier
    "opening_balance"       => 0.0,                         // Balance at the beginning of the statement period
    "opening_date"          => null,                        // Date when the statement period starts
    "closing_balance"       => 0.0,                         // Balance at the end of the statement period
    "closing_date"          => null,                        // Date when the statement period ends
    "statement_currency"    => "EUR",                       // Currency used for all amounts
    "bank_bic"              => "",                          // BIC (Bank Identifier Code) of the issuing bank
    "account_holder"        => "",                          // Name of the account holder
    "account_type"          => "current",                   // Type of account (e.g. current, savings)
    "transactions"          => [                            // List of transactions included in the statement
        [
            "entry_date"             => null,               // Date the transaction was recorded
            "value_date"             => null,               // Date the transaction becomes effective
            "amount"                 => 0.0,                // Transaction amount (negative = debit, positive = credit)
            "currency"               => "EUR",              // Currency of the transaction
            "transaction_type"       => "sepa_direct_debit",// Transaction type (e.g. SEPA direct debit, transfer)
            "sequence_number"        => 1,                  // Internal transaction sequence number
            "received_at"            => null,               // Timestamp when the transaction was received (UTC)
            "mandate_id"             => "",                 // Mandate identifier for SEPA direct debit
            "client_reference"       => "",                 // Reference provided by the client
            "structured_reference"   => "",                 // Structured communication reference
            "bank_reference"         => "",                 // Reference provided by the bank
            "unstructured_reference" => "",                 // Free-text communication
            "counterparty_name"      => "",                 // Name of the transaction counterparty
            "counterparty_iban"      => "",                 // IBAN of the transaction counterparty
            "counterparty_bic"       => "",                 // BIC of the transaction counterparty
            "counterparty_details"   => "",                 // Additional counterparty details (e.g. address)
            "transaction_message"    => ""                  // Message or label associated with the transaction
        ]
    ]
];

$context->httpResponse()
        ->body($output)
        ->send();
