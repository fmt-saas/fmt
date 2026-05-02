#### Format JSON standardisé (compatible PEPPOL BIS Billing 3.0)

Les documents sont convertis vers un format JSON conforme au schéma suivant :

- Conforme à [EN16931](https://www.en16931.eu/)
- Compatible avec les outils de validation PEPPOL
- Contient tous les champs essentiels pour une facture



Le schéma utilisé pour les factures est le suivant :

```
{
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "$id": "urn:fmt:json-schema:finance:purchase-invoice",
    "title": "PEPPOL BIS Billing 3.0 (EN16931) compliant JSON",
    "type": "object",
    "required": [
        "invoice_number",
        "invoice_type",
        "issue_date",
        "due_date",
        "currency",
        "supplier",
        "customer",
        "lines",
        "totals"
    ],
    "properties": {
        "document_type": {
            "type": "string"
        },
        "invoice_number": {
            "type": "string"
        },
        "invoice_type": {
            "type": "string",
            "enum": [
                "invoice",
                "credit_note"
            ]
        },
        "issue_date": {
            "type": "string",
            "format": "date-time"
        },
        "due_date": {
            "type": "string",
            "format": "date-time"
        },
        "currency": {
            "type": "string",
            "pattern": "^[A-Z]{3}$"
        },
        "buyer_reference": {
            "anyOf": [
                {
                    "type": "string"
                },
                {
                    "type": "null"
                }
            ]
        },
        "supplier": {
            "type": "object",
            "required": [
                "name",
                "vat_id",
                "address"
            ],
            "properties": {
                "name": {
                    "type": "string"
                },
                "vat_id": {
                    "type": "string"
                },
                "company_id": {
                    "type": "string"
                },
                "address": {
                    "type": "object",
                    "required": [
                        "street",
                        "city",
                        "postal_code",
                        "country"
                    ],
                    "properties": {
                        "street": {
                            "type": "string"
                        },
                        "city": {
                            "type": "string"
                        },
                        "postal_code": {
                            "type": "string"
                        },
                        "country": {
                            "type": "string",
                            "pattern": "^[A-Z]{2}$"
                        }
                    }
                }
            }
        },
        "customer": {
            "type": "object",
            "required": [
                "name",
                "vat_id",
                "address"
            ],
            "properties": {
                "name": {
                    "type": "string"
                },
                "vat_id": {
                    "anyOf": [
                        {
                            "type": "string"
                        },
                        {
                            "type": "null"
                        }
                    ]
                },
                "company_id": {
                    "type": "string"
                },
                "customer_number": {
                    "type": "string"
                },
                "contract_number": {
                    "type": "string"
                },
                "installation_number": {
                    "type": "string"
                },
                "address": {
                    "type": "object",
                    "required": [
                        "street",
                        "city",
                        "postal_code",
                        "country"
                    ],
                    "properties": {
                        "street": {
                            "type": "string"
                        },
                        "city": {
                            "type": "string"
                        },
                        "postal_code": {
                            "type": "string"
                        },
                        "country": {
                            "type": "string",
                            "pattern": "^[A-Z]{2}$"
                        }
                    }
                }
            }
        },
        "invoice_period": {
            "type": "object",
            "required": [
                "start_date",
                "end_date"
            ],
            "properties": {
                "start_date": {
                    "type": "string",
                    "format": "date-time"
                },
                "end_date": {
                    "type": "string",
                    "format": "date-time"
                }
            }
        },
        "lines": {
            "type": "array",
            "minItems": 1,
            "items": {
                "type": "object",
                "required": [
                    "id",
                    "description",
                    "quantity",
                    "unit_code",
                    "unit_price",
                    "amount",
                    "tax"
                ],
                "properties": {
                    "id": {
                        "type": "string"
                    },
                    "description": {
                        "type": "string"
                    },
                    "quantity": {
                        "type": "number"
                    },
                    "unit_code": {
                        "type": "string",
                        "enum": [
                            "C62",
                            "EA",
                            "H87",
                            "KGM",
                            "GRM",
                            "LTR",
                            "MLT",
                            "MTR",
                            "CMK",
                            "MTK",
                            "MMK",
                            "MTQ",
                            "HUR",
                            "MIN",
                            "DAY",
                            "MON",
                            "ANN",
                            "TNE",
                            "NAR",
                            "XPP",
                            "XBG",
                            "XBX",
                            "XCR",
                            "XPK",
                            "XCT",
                            "XBT",
                            "XCA",
                            "XPL",
                            "XKG",
                            "XLT"
                        ]
                    },
                    "unit_price": {
                        "type": "number"
                    },
                    "amount": {
                        "type": "number"
                    },
                    "tax": {
                        "type": "object",
                        "required": [
                            "category_id",
                            "percent",
                            "scheme_id"
                        ],
                        "properties": {
                            "category_id": {
                                "type": "string"
                            },
                            "percent": {
                                "type": "number"
                            },
                            "scheme_id": {
                                "type": "string"
                            }
                        }
                    }
                }
            }
        },
        "totals": {
            "type": "object",
            "required": [
                "total_excl_tax",
                "total_tax",
                "total_incl_tax",
                "payable_amount"
            ],
            "properties": {
                "total_excl_tax": {
                    "type": "number"
                },
                "total_tax": {
                    "type": "number"
                },
                "total_incl_tax": {
                    "type": "number"
                },
                "payable_amount": {
                    "type": "number"
                }
            }
        },
        "payment": {
            "type": "object",
            "required": [
                "iban",
                "bic",
                "payment_means_code"
            ],
            "properties": {
                "iban": {
                    "type": "string",
                    "pattern": "^[A-Z]{2}\\d{2}[A-Z0-9]{11,30}$"
                },
                "bic": {
                    "type": "string",
                    "pattern": "^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$"
                },
                "payment_means_code": {
                    "type": "string"
                },
                "payment_id": {
                    "type": "string"
                }
            }
        }
    },
    "additionalProperties": false
}  
```



**Exemple de structure JSON pour les factures:**

```
{
  "document_type": "invoice",
  "invoice_number": "INV-2025-001",
  "invoice_type": "invoice",
  "issue_date": "2025-05-06T00:00:00Z",
  "due_date": "2025-05-20T00:00:00Z",
  "currency": "EUR",
  "buyer_reference": "PO-123456",
  "supplier": {
    "name": "YesBabylon SA",
    "vat_id": "BE0123456789",
    "address": {
      "street": "Rue des Codeurs 42",
      "city": "Bruxelles",
      "postal_code": "1000",
      "country": "BE"
    }
  },
  "customer": {
    "name": "Ville de La Hulpe",
    "vat_id": "BE9876543210",
    "address": {
      "street": "Place communale 1",
      "city": "La Hulpe",
      "postal_code": "1310",
      "country": "BE"
    }
  },
  "invoice_period": {
    "start_date": "2025-04-01T00:00:00Z",
    "end_date": "2025-04-30T00:00:00Z"
  },
  "lines": [
    {
      "id": "1",
      "description": "Développement applicatif sur mesure",
      "quantity": 10,
      "unit_code": "HUR",
      "unit_price": 85.00,
      "amount": 850.00,
      "tax": {
        "category_id": "S",
        "percent": 21,
        "scheme_id": "VAT"
      }
    }
  ],
  "totals": {
    "total_excl_tax": 850.00,
    "total_tax": 178.50,
    "total_incl_tax": 1028.50,
    "payable_amount": 1028.50
  },
  "payment": {
    "iban": "BE71096123456769",
    "bic": "GKCCBEBB",
    "payment_means_code": "30",
    "payment_id": "+++810/4584/43280+++"
  }
}
```



#### Conversion depuis Mindee (JSON)

Mindee fournit une extraction documentaire à partir d'un PDF ou d'une image. La conversion est pilotée par le `DocumentType`, qui permet d'orienter le mapping vers le schéma JSON standardisé.

Champs extraits typiquement :

- `invoice_number`, `issue_date`, `due_date`
- `supplier`, `customer`
- `lines`, `totals`, `payment`
- `buyer_reference` si détecté

**Limitations connues :**

- Pas de support natif de `invoice_period`
- Les champs comme `payment_means_code` ou `payment_id` ne sont pas toujours présents ou fiables.

#### Conversion UBL (XML BIS3.0 EN16931) vers JSON

Le document XML est analysé via XPath, avec prise en charge des namespaces UBL/PEPPOL. Le résultat est un objet JSON conforme au schéma.

Points particuliers :

- Les balises absentes retournent des chaînes vides
- `invoice_period` n'est ajouté que s'il est présent dans le document source
- Les montants sont parsés avec `number_format` pour assurer une précision standardisée

#### Conversion JSON vers UBL XML

La structure JSON est convertie en XML au format UBL 2.1, en respectant l'ordre et les balises obligatoires selon PEPPOL BIS Billing 3.0.

- L'élément racine est `<Invoice>` avec tous les namespaces requis
- Les noeuds `InvoiceLine`, `AccountingSupplierParty`, `LegalMonetaryTotal`, etc. sont générés dynamiquement
- Les champs comme `invoice_period` sont inclus uniquement s'ils sont définis dans le JSON

##### Codes de mode de paiement (`PaymentMeansCode`)

| Code | Description                                |
| ---- | ------------------------------------------ |
| 30   | Virement bancaire standard (SEPA Transfer) |
| 31   | Virement instantané                        |
| 42   | Paiement par carte bancaire                |
| 48   | Carte prépayée ou débit/crédit             |
| 49   | Virement en ligne (e-banking)              |
| 97   | Non applicable (ex: proforma)              |



##### Mapping des Scheme ID PEPPOL par pays

| Pays               | Code | Scheme ID |
| ------------------ | ---- | --------- |
| Belgique           | BE   | 0208      |
| France             | FR   | 9957      |
| Allemagne          | DE   | 9930      |
| Pays-Bas           | NL   | 9944      |
| Luxembourg         | LU   | 9938      |
| Italie             | IT   | 0211      |
| Espagne            | ES   | 9920      |
| Portugal           | PT   | 9946      |
| Irlande            | IE   | 9935      |
| Autriche           | AT   | 9914      |
| Suisse             | CH   | 9927      |
| Royaume-Uni        | GB   | 9932      |
| Royaume-Uni (UK)   | UK   | 9932      |
| Suède              | SE   | 0007      |
| Danemark           | DK   | 0096      |
| Finlande           | FI   | 0213      |
| Norvège            | NO   | 0192      |
| Islande            | IS   | 0196      |
| Grèce              | GR   | 9933      |
| Chypre             | CY   | 9928      |
| République Tchèque | CZ   | 9929      |
| Slovaquie          | SK   | 9950      |
| Slovénie           | SI   | 9949      |
| Hongrie            | HU   | 9910      |
| Croatie            | HR   | 9934      |
| Pologne            | PL   | 9945      |
| Roumanie           | RO   | 9947      |
| Bulgarie           | BG   | 9926      |
| Estonie            | EE   | 9931      |
| Lettonie           | LV   | 9939      |
| Lituanie           | LT   | 9937      |
| Malte              | MT   | 9943      |
| Andorre            | AD   | 9922      |
| Albanie            | AL   | 9923      |
| Bosnie-Herzégovine | BA   | 9924      |
| Liechtenstein      | LI   | 9936      |
| Monaco             | MC   | 9940      |
| Monténégro         | ME   | 9941      |
| Macédoine du Nord  | MK   | 9942      |
| Serbie             | RS   | 9948      |
| Saint-Marin        | SM   | 9951      |
| Turquie            | TR   | 9952      |
| États-Unis         | US   | 9959      |
| Vatican            | VA   | 9953      |