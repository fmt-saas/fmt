<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use Opis\JsonSchema\Validator;


$providers = eQual::inject(['context', 'orm', 'auth', 'access']);


$statement_schema = <<<'EOT'
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://example.com/schemas/bank-statement.json",
  "title": "Bank Statement",
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
$coda_test = <<< EOT
    0000021032022005        00028964  PROGNO ALEXANDRE          CREGBEBB   00401214467 00000                                       2
    10039191156749841 EUR0BE                  0000000011581240190220KALEO - CENTRE BELGE TOURICompte d'entreprise CBC            039
    2100010000ZDZU01115ASCTBBEONTVA0000000000019800200220001500001101150011118675                                      20022003901 0
    2200010000                                                                                        GKCCBEBB                   1 0
    2300010000BE41063012345610                     PARTNER 1                                                                     0 1
    3100010001OL44483FW SCTOFBIONLO001010001001PARTNER 1                                                                         0 0
    2100020000OL4414AC8BOVSOVSOVERS0000000000044450110111001500001101150011118676                                      11011113501 0
    2200020000                                                                                        BBRUBEBB                   1 0
    2300020000BE61310126985517                     PARTNER 2                                                                     0 1
    3100020001OL4414AC8BOVSOVSOVERS001500001001PARTNER 2                                                                         1 0
    3200020001MOLENSTRAAT 60                     9340    LEDE                                                                    0 0
    2100030000AFECA0BIS IKLINNINBIS1000000000479040110111313410000              KBC-INVESTERINGSKREDIET 737-6543210-21 11011113510 0
    2100030001AFECA0BIS IKLINNINBIS1000000000419920110111813410660                                                     11011113500 0
    2100030002AFECA0BIS IKLINNINBIS1000000000059120110111813410020                                                     11011113510 0
    2100040000AFECA0CVA IKLINNINNIG1000000000479040110111313410000              KBC-INVESTERINGSKREDIET 737-6543210-21 11011113510 0
    2100040001AFECA0CVA IKLINNINNIG1000000000419920110111813410660                                                     11011113500 0
    2100040002AFECA0CVA IKLINNINNIG1000000000059120110111813410020                                                     11011113510 0
    2100050000AOGM00160BSCTOBOGOVER0000000000063740110111001500000TERUGGAVE 37232481 8400083296 .                      11011113501 0
    2200050000                                                     362/363                            KREDBEBB                   1 0
    2300050000BE43730004200601                     KBC VERZEKERINGEN NV                                                          0 1
    3100050001AOGM00160BSCTOBOGOVER001500001001KBC VERZEKERINGEN NV                                                              1 0
    3200050001VAN OVERSTRAETENPLEIN 2            3000    LEUVEN                                                                  0 0
    8135BE44734024486445                  EUR0000000013646050110111                                                                0
    9               000022000000001393080000000003108190                                                                           2
    EOT;



$tests = [

        '1101' => [
            'description'       => "Check CODA import.",
            'help'              => "Convert a CODA bank statement to standardized JSON and validate result against `bank-statement` schema.",
            'arrange'           => function() use($providers) {
                },
            'act'               => function($coda) use($providers, $coda_test) {
                    return eQual::run('get', 'finance_bank_BankStatement_parse-coda', ['data' => $coda_test]);
                },
            'assert'            => function($statements) use($providers, $statement_schema) {
                    $valid = true;
                    foreach($statements as $statement) {
                        $validator = new Validator();
                        /** @var ValidationResult $result */
                        $result = $validator->validate((object) json_decode(json_encode($statement)), $statement_schema);
                        $valid &= $result->isValid();
                    }
                    return $valid;
                },
            'rollback'          => function() use($providers) {
                }
        ]
];

