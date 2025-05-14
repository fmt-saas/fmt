<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'description'   => "Schema `bank-statement` with format json-schema.org/draft/2020-12.",
    'params'        => [],
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

['context' => $context] = $providers;

$schema = <<<'EOT'
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "schema:bank-statement",
  "title": "SEPA (CAMT.053 ISO 20022) compliant Bank Statement JSON Input",
  "type": "object",
  "required": [
    "account_iban",
    "statement_number",
    "opening_balance",
    "opening_date",
    "closing_balance",
    "closing_date",
    "statement_currency",
    "bank_bic",
    "account_holder",
    "account_type",
    "transactions"
  ],
  "properties": {
    "account_iban": {
      "type": "string",
      "pattern": "^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$"
    },
    "statement_number": {
      "type": "string"
    },
    "opening_balance": {
      "type": "number"
    },
    "opening_date": {
      "type": "string",
      "format": "date-time"
    },
    "closing_balance": {
      "type": "number"
    },
    "closing_date": {
      "type": "string",
      "format": "date-time"
    },
    "statement_currency": {
      "type": "string",
      "pattern": "^[A-Z]{3}$"
    },
    "bank_bic": {
      "type": "string",
      "pattern": "^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$"
    },
    "account_holder": {
      "type": "string"
    },
    "account_type": {
      "type": "string",
      "enum": ["current", "savings", "loan"]
    },
    "transactions": {
      "type": "array",
      "minItems": 1,
      "items": {
        "type": "object",
        "required": [
          "entry_date",
          "value_date",
          "amount",
          "currency",
          "transaction_type",
          "sequence_number"
        ],
        "properties": {
          "entry_date": {
            "type": "string",
            "format": "date-time"
          },
          "value_date": {
            "type": "string",
            "format": "date-time"
          },
          "amount": {
            "type": "number"
          },
          "currency": {
            "type": "string",
            "pattern": "^[A-Z]{3}$"
          },
          "transaction_type": {
            "type": "string"
          },
          "sequence_number": {
            "type": "integer"
          },
          "mandate_id": {
            "anyOf": [
              { "type": "string" },
              { "type": "null" }
            ]
          },
          "client_reference": {
            "anyOf": [
              { "type": "string" },
              { "type": "null" }
            ]
          },
          "structured_reference": {
            "anyOf": [
              { "type": "string" },
              { "type": "null" }
            ]
          },
          "bank_reference": {
            "anyOf": [
              { "type": "string" },
              { "type": "null" }
            ]
          },
          "unstructured_reference": {
            "anyOf": [
              { "type": "string" },
              { "type": "null" }
            ]
          },
          "counterparty_name": {
            "anyOf": [
              { "type": "string" },
              { "type": "null" }
            ]
          },
          "counterparty_iban": {
            "anyOf": [
              {
                "type": "string",
                "pattern": "^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$"
              },
              { "type": "null" }
            ]
          },
          "counterparty_bic": {
            "anyOf": [
              {
                "type": "string",
                "pattern": "^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$"
              },
              { "type": "null" }
            ]
          },
          "counterparty_details": {
            "anyOf": [
              { "type": "string" },
              { "type": "null" }
            ]
          },
          "transaction_message": {
            "anyOf": [
              { "type": "string" },
              { "type": "null" }
            ]
          }
        }
      }
    }
  }
}
EOT;

$context->httpResponse()
    ->body($schema, true)
    ->send();