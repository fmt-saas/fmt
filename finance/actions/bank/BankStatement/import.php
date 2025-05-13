<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

$bankFieldMap = [
    'Account' => [
        'target'  => 'account_iban',
        'adapter' => 'iban_normalize',
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
        'adapter' => 'lowercase_enum',
    ],
    'Bic' => [
        'target'  => 'bank_bic',
        'adapter' => 'bic_normalize',
    ],
    'Type of account information' => [
        'target'  => null,
        'adapter' => null,
    ],
    'Statement number' => [
        'target'  => 'statement_number',
        'adapter' => 'string_trim',
    ],
    'Statement currency' => [
        'target'  => 'statement_currency',
        'adapter' => 'currency_uppercase',
    ],
    'Opening balance date' => [
        'target'  => 'opening_date',
        'adapter' => 'date_parse_ymd',
    ],
    'Opening balance' => [
        'target'  => 'opening_balance',
        'adapter' => 'decimal_parse',
    ],
    'Closing balance date' => [
        'target'  => 'closing_date',
        'adapter' => 'date_parse_ymd',
    ],
    'Closing balance' => [
        'target'  => 'closing_balance',
        'adapter' => 'decimal_parse',
    ],
    'Closing available balance' => [
        'target'  => null, // champ supprimé dans ta version actuelle
        'adapter' => null,
    ],
    'Entry date' => [
        'target'  => 'entry_date',
        'adapter' => 'date_parse_ymd',
    ],
    'Value date' => [
        'target'  => 'value_date',
        'adapter' => 'date_parse_ymd',
    ],
    'Transaction amount' => [
        'target'  => 'amount',
        'adapter' => 'decimal_parse_signed',
    ],
    'Transaction currency' => [
        'target'  => 'currency',
        'adapter' => 'currency_uppercase',
    ],
    'Transaction type' => [
        'target'  => 'transaction_type',
        'adapter' => 'type_enum_map',
    ],
    'Client reference' => [
        'target'  => 'client_reference',
        'adapter' => 'string_trim',
    ],
    'Structured Reference' => [
        'target'  => 'structured_reference',
        'adapter' => 'ocr_clean',
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
        'target'  => 'sequence_number',
        'adapter' => 'int_cast',
    ],
    'Reception Date/Time' => [
        'target'  => 'received_at',
        'adapter' => 'datetime_parse_iso',
    ],
    'stFreeMessage' => [
        'target'  => 'transaction_message',
        'adapter' => 'string_append',
    ],
];
