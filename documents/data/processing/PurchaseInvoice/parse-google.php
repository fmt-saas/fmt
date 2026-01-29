<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

[$params, $providers] = eQual::announce([
    'description'   => 'Return a purchase-invoice JSON descriptor from a Google Cloud DocAI `entities` array.',
    'help'          => 'See https://cloud.google.com/document-ai/docs/reference/rest/v1/Document#Entity',
    'params'        => [
        'json' => [
            'type' => 'string',
            'usage' => 'text/json',
            'description' => 'JSON array of `entities` from Google Cloud DocAI Invoice Parser.',
            'required' => true
        ],
    ],
    'access' => [
        'visibility' => 'protected'
    ],
    'response' => [
        'accept-origin' => '*',
        'content-type' => 'application/json'
    ],
    'providers' => ['context']
]);

['context' => $context] = $providers;

$entities = json_decode($params['json'], true);
if (!is_array($entities)) {
    throw new Exception('invalid_json_entities', EQ_ERROR_INVALID_PARAM);
}


$extractAddress = function ($address, $default_country = null) {
    $street = null;
    $postal_code = null;
    $city = null;
    $country = $default_country;

    if(is_array($address)) {
        $streetParts = [];
        if (!empty($address['street_name'])) {
            $streetParts[] = $address['street_name'];
        }
        if (!empty($address['street_number'])) {
            $streetParts[] = $address['street_number'];
        }
        if (!empty($address['address_complement'])) {
            $streetParts[] = $address['address_complement'];
        }

        $street       = implode(' ', $streetParts);
        $city         = $address['city'] ?? null;
        $postal_code  = $address['postal_code'] ?? null;
        $country      = $address['country'] ?? $default_country;
    }
    elseif(is_string($address)) {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $address)),
            fn($l) => $l !== ''
        ));

        foreach($lines as $line) {
            // postal code followed by a city (e.g., "1000 Bruxelles")
            if(preg_match('/(\d{4,6})\s+(.+)/u', $line, $m)) {
                $postal_code = $m[1];
                $city = trim($m[2]);
            }
            // country (alphabetic word or initial uppercase letter)
            elseif(preg_match('/^[A-Z][A-Za-zéèêàçîïùüÿ\-\s]+$/u', $line)) {
                $country = $line;
            }
            // otherwise probably street
            elseif(!$street) {
                $street = $line;
            }
        }
    }

    return [
        'street'        => $street,
        'city'          => $city,
        'postal_code'   => $postal_code,
        'country'       => $country
    ];
};

$extractIban = function($iban) {
    if(!$iban) return null;
    return str_replace(' ', '', strtoupper($iban));
};

$extractVat = function ($tax_id) {
    if(!$tax_id) return null;
    return str_replace(' ', '', strtoupper($tax_id));
};

$computeBicFromIban = function($iban) {
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

/**
 * Helper: find entity by type
 */
$getEntity = function (string $type) use ($entities) {
    foreach($entities as $entity) {
        if($entity['type'] === $type) {
            return $entity;
        }
    }
    return null;
};

/**
 * Helper: safely extract normalized or mentioned value
 */
$getValue = function (?array $entity, $default = null, $expected_type = 'string', float $min_confidence = 0.0) {
    if(!$entity) {
        return $default;
    }
    if(isset($entity['confidence']) && $entity['confidence'] < $min_confidence) {
        return $default;
    }


    $value = null;
    if (isset($entity['normalizedValue']['text'])) {
        $value = trim($entity['normalizedValue']['text']);
    }
    elseif (isset($entity['mentionText'])) {
        $value = trim($entity['mentionText']);
    }

    if($value === null || $value === '') {
        return $default;
    }

    // typage dynamique
    switch ($expected_type) {
        case 'float':
            // Corrige les formats comme "1.388.28" ou "1,388.28"
            $normalized = str_replace([' ', ','], ['', '.'], $value);

            // Si plusieurs points décimaux -> on garde le dernier comme séparateur décimal
            if (substr_count($normalized, '.') > 1) {
                $normalized = preg_replace('/\.(?=.*\.)/', '', $normalized);
            }

            // Vérifie si c’est bien un nombre
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }

            return $default;

        case 'int':
            if (preg_match('/-?\d+/', $value, $matches)) {
                return (int) $matches[0];
            }
            return $default;

        case 'bool':
            $lower = strtolower($value);
            if (in_array($lower, ['yes', 'true', '1', 'oui'], true)) {
                return true;
            }
            if (in_array($lower, ['no', 'false', '0', 'non'], true)) {
                return false;
            }
            return $default;

        case 'string':
        default:
            return (string) $value;
    }

    return $default;
};

/**
 * Helper: date formatting (DocAI often provides ISO yyyy-mm-dd)
 */
$formatDate = function ($dateText) {
    if (!$dateText) return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateText)) {
        return $dateText . 'T00:00:00Z';
    }
    return $dateText;
};

/**
 * Helper: map free-text payment method to UN/CEFACT code
 * 30 = Credit transfer (virement), 49 = Direct debit (domiciliation SEPA), 48 = Bank card
 */
$mapPaymentTextToCode = function (string $text = '') {
    if(!$text) {
        return null;
    }

    $t = strtolower(trim($text));

    // French / EN variants
    $isTransfer = str_contains($t, 'virement') || str_contains($t, 'transfert') || str_contains($t, 'transfer') || str_contains($t, 'credit transfer') || str_contains($t, 'bank transfer');
    $isDirectDebit = str_contains($t, 'domiciliation') || str_contains($t, 'prélèvement') || str_contains($t, 'sepa') || str_contains($t, 'direct debit');
    $isCard = str_contains($t, 'carte') || str_contains($t, 'card') || str_contains($t, 'visa') || str_contains($t, 'mastercard');

    if($isDirectDebit) {
        return '49';
    }
    if($isTransfer) {
        return '30';
    }
    if($isCard) {
        return '48';
    }

    return null;
};

/**
 * Extract base entities
 */
$localeCountry = 'BE';


$invoiceType        = $getValue($getEntity('invoice_type'));
$issueDate          = $getValue($getEntity('invoice_date'));
$dueDate            = $getValue($getEntity('due_date'));
$totalNet           = $getValue($getEntity('net_amount'), 0.0, 'float');
$totalTax           = $getValue($getEntity('total_tax_amount'), 0.0, 'float');
$totalAmount        = $getValue($getEntity('total_amount'), 0.0, 'float');
$supplierName       = str_replace("\n", ' ', $getValue($getEntity('supplier_name'), ''));
$supplierVat        = $extractVat($getValue($getEntity('supplier_tax_id')));
$supplierIban       = $extractIban($getValue($getEntity('supplier_iban')));
$supplierBic        = $getValue($getEntity('supplier_bic')) ?? $computeBicFromIban($supplierIban);
$supplierPaymentRef = $getValue($getEntity('supplier_payment_ref'), '');
$supplierAddress    = $extractAddress($getValue($getEntity('supplier_address')), $localeCountry);
$currency           = $getValue($getEntity('currency'), 'EUR');
$customerName       = str_replace("\n", ' ', $getValue($getEntity('customer_name'), ''));
$customerAddress    = $extractAddress($getValue($getEntity('customer_address')), $localeCountry);

/**
 * Extract line items
 */
$lines = [];
foreach($entities as $entity) {
    if($entity['type'] === 'line_item') {
        $line = [
            'id'            => (string)($entity['id'] ?? count($lines) + 1),
            'description'   => $entity['mentionText'] ?? null,
            'quantity'      => 1,
            'unit_code'     => 'C62',
            'unit_price'    => null,
            'amount'        => null,
            'tax'           => [
                'category_id'   => 'S',
                'percent'       => 0,
                'scheme_id'     => 'VAT'
            ]
        ];
        foreach ($entity['properties'] ?? [] as $prop) {
            switch ($prop['type']) {
                case 'line_item/quantity':
                    $line['quantity'] = (float) $getValue($prop, 1);
                    break;
                case 'line_item/unit_price':
                    $line['unit_price'] = (float) $getValue($prop, 0);
                    break;
                case 'line_item/amount':
                    $line['amount'] = (float) $getValue($prop, 0);
                    break;
                case 'line_item/description':
                    $line['description'] = $getValue($prop, $line['description']);
                    break;
            }
        }
        if (!$line['amount'] && $line['unit_price'] && $line['quantity']) {
            $line['amount'] = $line['unit_price'] * $line['quantity'];
        }
        $lines[] = $line;
    }
}

$map_document_type = [
    'invoice_statement'    => 'invoice',
    // 'invoice_statement'    => 'credit_note',
];


/**
 * Extract payment info (payment_terms → payment_method / payment_means_code / payment_due_date)
 */
$paymentMeansCode = null;
$paymentMethod = null;

$paymentTerms = $getEntity('payment_terms');
if ($paymentTerms && isset($paymentTerms['properties'])) {
    foreach ($paymentTerms['properties'] as $prop) {
        switch ($prop['type']) {
            case 'payment_means_code':
                $paymentMeansCode = $getValue($prop, null, 'string', 0.0);
                break;
            case 'payment_method':
                $paymentMethod = $getValue($prop, null, 'string', 0.0);
                break;
        }
    }
}

/**
 * Fallbacks:
 * - 1) si le code est absent mais qu'on a payment_method → on mappe (virement/dom/sepa/carte…)
 * - 2) sinon on tente depuis le texte libre de payment_terms
 */
if (!$paymentMeansCode && $paymentMethod) {
    $paymentMeansCode = $mapPaymentTextToCode($paymentMethod);
}

if (!$paymentMeansCode && $paymentTerms) {
    $paymentMeansCode = $mapPaymentTextToCode($getValue($paymentTerms));
}


/**
 * Final output structure (aligned with Mindee version)
 */
$output = [
    'document_type'     => $map_document_type[$invoiceType] ?? 'unknown',
    'invoice_number'    => $getValue($getEntity('invoice_id'), ''),
    'invoice_type'      => $map_document_type[$invoiceType] ?? 'unknown',
    'issue_date'        => $formatDate($issueDate),
    'due_date'          => $formatDate($dueDate),
    'currency'          => $currency,
    'buyer_reference'   => $getValue($getEntity('purchase_order')),
    'supplier' => [
        'name'    => $supplierName,
        'vat_id'  => $supplierVat,
        'address' => $supplierAddress,
    ],
    'customer' => [
        'name'    => $customerName,
        'vat_id'  => $getValue($getEntity('customer_tax_id')),
        'address' => $customerAddress,
    ],
    'lines' => $lines,
    'totals' => [
        'total_excl_tax' => (float) $totalNet,
        'total_tax'      => (float) $totalTax,
        'total_incl_tax' => (float) $totalAmount,
        'payable_amount' => (float) $totalAmount,
    ],
    'payment' => [
        'iban'               => $supplierIban,
        'bic'                => $supplierBic,
        'payment_id'         => $supplierPaymentRef,
        'payment_means_code' => $paymentMeansCode ?? '30' // fallback to bank transfer
    ]
];

$context->httpResponse()
        ->body($output)
        ->send();


// Full Google Doc AI API response sample
/*
[
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "1259",
                    "endIndex": "1260"
                },
                {
                    "startIndex": "1261",
                    "endIndex": "1267"
                },
                {
                    "startIndex": "1268",
                    "endIndex": "1272"
                },
                {
                    "startIndex": "1273",
                    "endIndex": "1278"
                }
            ]
        },
        "type": "vat",
        "mentionText": "6 602,58 6,00 36,15",
        "confidence": 1,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.08506841,
                                "y": 0.74600506
                            },
                            {
                                "x": 0.4158239,
                                "y": 0.74600506
                            },
                            {
                                "x": 0.4158239,
                                "y": 0.75567704
                            },
                            {
                                "x": 0.08506841,
                                "y": 0.75567704
                            }
                        ]
                    }
                }
            ]
        },
        "id": "0",
        "properties": [
            {
                "textAnchor": {
                    "textSegments": [
                        {
                            "startIndex": "1259",
                            "endIndex": "1260"
                        }
                    ],
                    "content": "6"
                },
                "type": "vat/category_code",
                "mentionText": "6",
                "confidence": 0.64742,
                "pageAnchor": {
                    "pageRefs": [
                        {
                            "boundingPoly": {
                                "normalizedVertices": [
                                    {
                                        "x": 0.08506841,
                                        "y": 0.74600506
                                    },
                                    {
                                        "x": 0.08863772,
                                        "y": 0.74600506
                                    },
                                    {
                                        "x": 0.08863772,
                                        "y": 0.75441545
                                    },
                                    {
                                        "x": 0.08506841,
                                        "y": 0.75441545
                                    }
                                ]
                            }
                        }
                    ]
                },
                "id": "1"
            },
            {
                "textAnchor": {
                    "textSegments": [
                        {
                            "startIndex": "1261",
                            "endIndex": "1267"
                        }
                    ],
                    "content": "602,58"
                },
                "type": "vat/amount",
                "mentionText": "602,58",
                "confidence": 0.6627546,
                "pageAnchor": {
                    "pageRefs": [
                        {
                            "boundingPoly": {
                                "normalizedVertices": [
                                    {
                                        "x": 0.1784652,
                                        "y": 0.74600506
                                    },
                                    {
                                        "x": 0.2195122,
                                        "y": 0.74600506
                                    },
                                    {
                                        "x": 0.2195122,
                                        "y": 0.75567704
                                    },
                                    {
                                        "x": 0.1784652,
                                        "y": 0.75567704
                                    }
                                ]
                            }
                        }
                    ]
                },
                "id": "2",
                "normalizedValue": {
                    "text": "602.58",
                    "floatValue": 602.58
                }
            },
            {
                "textAnchor": {
                    "textSegments": [
                        {
                            "startIndex": "1268",
                            "endIndex": "1272"
                        }
                    ],
                    "content": "6,00"
                },
                "type": "vat/tax_rate",
                "mentionText": "6,00",
                "confidence": 0.65970767,
                "pageAnchor": {
                    "pageRefs": [
                        {
                            "boundingPoly": {
                                "normalizedVertices": [
                                    {
                                        "x": 0.27900058,
                                        "y": 0.74600506
                                    },
                                    {
                                        "x": 0.30398571,
                                        "y": 0.74600506
                                    },
                                    {
                                        "x": 0.30398571,
                                        "y": 0.754836
                                    },
                                    {
                                        "x": 0.27900058,
                                        "y": 0.754836
                                    }
                                ]
                            }
                        }
                    ]
                },
                "id": "3",
                "normalizedValue": {
                    "text": "6",
                    "floatValue": 6
                }
            },
            {
                "textAnchor": {
                    "textSegments": [
                        {
                            "startIndex": "1273",
                            "endIndex": "1278"
                        }
                    ],
                    "content": "36,15"
                },
                "type": "vat/tax_amount",
                "mentionText": "36,15",
                "confidence": 0.6892522,
                "pageAnchor": {
                    "pageRefs": [
                        {
                            "boundingPoly": {
                                "normalizedVertices": [
                                    {
                                        "x": 0.3872695,
                                        "y": 0.74600506
                                    },
                                    {
                                        "x": 0.4158239,
                                        "y": 0.74600506
                                    },
                                    {
                                        "x": 0.4158239,
                                        "y": 0.75525653
                                    },
                                    {
                                        "x": 0.3872695,
                                        "y": 0.75525653
                                    }
                                ]
                            }
                        }
                    ]
                },
                "id": "4",
                "normalizedValue": {
                    "text": "36.15",
                    "floatValue": 36.15
                }
            }
        ]
    },
    {
        "type": "invoice_type",
        "confidence": 0.97991824,
        "pageAnchor": {
            "pageRefs": [
                []
            ]
        },
        "id": "5",
        "normalizedValue": {
            "text": "invoice_statement"
        }
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "89",
                    "endIndex": "95"
                }
            ],
            "content": "FA6478"
        },
        "type": "invoice_id",
        "mentionText": "FA6478",
        "confidence": 0.955703,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.47471744,
                                "y": 0.092514716
                            },
                            {
                                "x": 0.5205235,
                                "y": 0.092514716
                            },
                            {
                                "x": 0.5205235,
                                "y": 0.10092515
                            },
                            {
                                "x": 0.47471744,
                                "y": 0.10092515
                            }
                        ]
                    }
                }
            ]
        },
        "id": "6"
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "96",
                    "endIndex": "104"
                }
            ],
            "content": "10-05-22"
        },
        "type": "invoice_date",
        "mentionText": "10-05-22",
        "confidence": 0.9468237,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.6478287,
                                "y": 0.09293524
                            },
                            {
                                "x": 0.6989887,
                                "y": 0.09293524
                            },
                            {
                                "x": 0.6989887,
                                "y": 0.101345666
                            },
                            {
                                "x": 0.6478287,
                                "y": 0.101345666
                            }
                        ]
                    }
                }
            ]
        },
        "id": "7",
        "normalizedValue": {
            "text": "2022-05-10",
            "dateValue": {
                "year": 2022,
                "month": 5,
                "day": 10
            }
        }
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "1117",
                    "endIndex": "1136"
                }
            ],
            "content": "BE33 0688 9606 8546"
        },
        "type": "supplier_iban",
        "mentionText": "BE33 0688 9606 8546",
        "confidence": 0.9170218,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.31707317,
                                "y": 0.62615645
                            },
                            {
                                "x": 0.4949435,
                                "y": 0.62615645
                            },
                            {
                                "x": 0.4949435,
                                "y": 0.63751054
                            },
                            {
                                "x": 0.31707317,
                                "y": 0.63751054
                            }
                        ]
                    }
                }
            ]
        },
        "id": "8"
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "497",
                    "endIndex": "505"
                }
            ],
            "content": "10-05-22"
        },
        "type": "due_date",
        "mentionText": "10-05-22",
        "confidence": 0.90207237,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.8143962,
                                "y": 0.33683768
                            },
                            {
                                "x": 0.8649613,
                                "y": 0.33683768
                            },
                            {
                                "x": 0.8649613,
                                "y": 0.3452481
                            },
                            {
                                "x": 0.8143962,
                                "y": 0.3452481
                            }
                        ]
                    }
                }
            ]
        },
        "id": "9",
        "normalizedValue": {
            "text": "2022-05-10",
            "dateValue": {
                "year": 2022,
                "month": 5,
                "day": 10
            }
        }
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "1288",
                    "endIndex": "1294"
                }
            ],
            "content": "602,58"
        },
        "type": "net_amount",
        "mentionText": "602,58",
        "confidence": 0.9006634,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.8786437,
                                "y": 0.7468461
                            },
                            {
                                "x": 0.917906,
                                "y": 0.7468461
                            },
                            {
                                "x": 0.917906,
                                "y": 0.75651807
                            },
                            {
                                "x": 0.8786437,
                                "y": 0.75651807
                            }
                        ]
                    }
                }
            ]
        },
        "id": "10",
        "normalizedValue": {
            "text": "602.58",
            "floatValue": 602.58
        }
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "1326",
                    "endIndex": "1332"
                }
            ],
            "content": "638,73"
        },
        "type": "total_amount",
        "mentionText": "638,73",
        "confidence": 0.87249404,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.87923855,
                                "y": 0.7834315
                            },
                            {
                                "x": 0.917906,
                                "y": 0.7834315
                            },
                            {
                                "x": 0.917906,
                                "y": 0.79268295
                            },
                            {
                                "x": 0.87923855,
                                "y": 0.79268295
                            }
                        ]
                    }
                }
            ]
        },
        "id": "11",
        "normalizedValue": {
            "text": "638.73",
            "floatValue": 638.73
        }
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "1307",
                    "endIndex": "1312"
                }
            ],
            "content": "36,15"
        },
        "type": "total_tax_amount",
        "mentionText": "36,15",
        "confidence": 0.845462,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.88816184,
                                "y": 0.7649285
                            },
                            {
                                "x": 0.9185009,
                                "y": 0.7649285
                            },
                            {
                                "x": 0.9185009,
                                "y": 0.77418
                            },
                            {
                                "x": 0.88816184,
                                "y": 0.77418
                            }
                        ]
                    }
                }
            ]
        },
        "id": "12",
        "normalizedValue": {
            "text": "36.15",
            "floatValue": 36.15
        }
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "187",
                    "endIndex": "204"
                }
            ],
            "content": "+32(0)2 588.01.00"
        },
        "type": "supplier_phone",
        "mentionText": "+32(0)2 588.01.00",
        "confidence": 0.8052036,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.189768,
                                "y": 0.19512194
                            },
                            {
                                "x": 0.29565734,
                                "y": 0.19512194
                            },
                            {
                                "x": 0.29565734,
                                "y": 0.20647603
                            },
                            {
                                "x": 0.189768,
                                "y": 0.20647603
                            }
                        ]
                    }
                }
            ]
        },
        "id": "13"
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "145",
                    "endIndex": "180"
                }
            ],
            "content": "Rue Sander Pierron 7\n1030 Bruxelles"
        },
        "type": "supplier_address",
        "mentionText": "Rue Sander Pierron 7\n1030 Bruxelles",
        "confidence": 0.7857909,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.058298633,
                                "y": 0.15853658
                            },
                            {
                                "x": 0.19155265,
                                "y": 0.15853658
                            },
                            {
                                "x": 0.19155265,
                                "y": 0.186291
                            },
                            {
                                "x": 0.058298633,
                                "y": 0.186291
                            }
                        ]
                    }
                }
            ]
        },
        "id": "14"
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "125",
                    "endIndex": "144"
                }
            ],
            "content": "Lift-Up Engineering"
        },
        "type": "supplier_name",
        "mentionText": "Lift-Up Engineering",
        "confidence": 0.7812277,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.0594884,
                                "y": 0.14003365
                            },
                            {
                                "x": 0.17906009,
                                "y": 0.14003365
                            },
                            {
                                "x": 0.17906009,
                                "y": 0.15138772
                            },
                            {
                                "x": 0.0594884,
                                "y": 0.15138772
                            }
                        ]
                    }
                }
            ]
        },
        "id": "15"
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "287",
                    "endIndex": "301"
                }
            ],
            "content": "BE0502.481.972"
        },
        "type": "supplier_tax_id",
        "mentionText": "BE0502.481.972",
        "confidence": 0.67097425,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.18738846,
                                "y": 0.23296888
                            },
                            {
                                "x": 0.28435454,
                                "y": 0.23296888
                            },
                            {
                                "x": 0.28435454,
                                "y": 0.24222036
                            },
                            {
                                "x": 0.18738846,
                                "y": 0.24222036
                            }
                        ]
                    }
                }
            ]
        },
        "id": "16"
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "1354",
                    "endIndex": "1355"
                }
            ],
            "content": "€"
        },
        "type": "currency",
        "mentionText": "€",
        "confidence": 0.5845674,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.9256395,
                                "y": 0.8019344
                            },
                            {
                                "x": 0.9292088,
                                "y": 0.8019344
                            },
                            {
                                "x": 0.9292088,
                                "y": 0.8111859
                            },
                            {
                                "x": 0.9256395,
                                "y": 0.8111859
                            }
                        ]
                    }
                }
            ]
        },
        "id": "17",
        "normalizedValue": {
            "text": "EUR"
        }
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "349",
                    "endIndex": "381"
                }
            ],
            "content": "Trevi Services - Mme A. De Bondt"
        },
        "type": "receiver_name",
        "mentionText": "Trevi Services - Mme A. De Bondt",
        "confidence": 0.47672457,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.47412255,
                                "y": 0.24936922
                            },
                            {
                                "x": 0.67757285,
                                "y": 0.24936922
                            },
                            {
                                "x": 0.67757285,
                                "y": 0.25862068
                            },
                            {
                                "x": 0.47412255,
                                "y": 0.25862068
                            }
                        ]
                    }
                }
            ]
        },
        "id": "18"
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "382",
                    "endIndex": "435"
                }
            ],
            "content": "Av. Leopold Wiener 127 bte11\n1170\nWatermael-Boitsfort"
        },
        "type": "receiver_address",
        "mentionText": "Av. Leopold Wiener 127 bte11\n1170\nWatermael-Boitsfort",
        "confidence": 0.3063891,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.47412255,
                                "y": 0.26787215
                            },
                            {
                                "x": 0.7031529,
                                "y": 0.26787215
                            },
                            {
                                "x": 0.7031529,
                                "y": 0.2977292
                            },
                            {
                                "x": 0.47412255,
                                "y": 0.2977292
                            }
                        ]
                    }
                }
            ]
        },
        "id": "19"
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "endIndex": "12"
                }
            ],
            "content": "202206200008"
        },
        "type": "receiver_tax_id",
        "mentionText": "202206200008",
        "confidence": 0.16129039,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.7471743,
                                "y": 0.013456686
                            },
                            {
                                "x": 0.85722786,
                                "y": 0.013456686
                            },
                            {
                                "x": 0.85722786,
                                "y": 0.024810765
                            },
                            {
                                "x": 0.7471743,
                                "y": 0.024810765
                            }
                        ]
                    }
                }
            ]
        },
        "id": "20"
    },
    {
        "textAnchor": {
            "textSegments": [
                {
                    "startIndex": "551",
                    "endIndex": "556"
                },
                {
                    "startIndex": "557",
                    "endIndex": "563"
                },
                {
                    "startIndex": "578",
                    "endIndex": "838"
                }
            ]
        },
        "type": "line_item",
        "mentionText": "0,830 726,00 Adresse du bâtiment : Rue Théodore de Cuyper\n212 à 1200 Bruxelles\nMaintenance préventive 4 visites annuelles.\nNuméro de contrat : 3346\nIndice Abex de base : 858 - nov 2020\nIndice Abex actuel : 906 - nov 2021\nPériode de facturation :\ndu 01/03/2022 au 31/12/2022",
        "confidence": 1,
        "pageAnchor": {
            "pageRefs": [
                {
                    "boundingPoly": {
                        "normalizedVertices": [
                            {
                                "x": 0.18143962,
                                "y": 0.37888983
                            },
                            {
                                "x": 0.8143962,
                                "y": 0.37888983
                            },
                            {
                                "x": 0.8143962,
                                "y": 0.49495375
                            },
                            {
                                "x": 0.18143962,
                                "y": 0.49495375
                            }
                        ]
                    }
                }
            ]
        },
        "id": "21",
        "properties": [
            {
                "textAnchor": {
                    "textSegments": [
                        {
                            "startIndex": "551",
                            "endIndex": "556"
                        }
                    ],
                    "content": "0,830"
                },
                "type": "line_item/quantity",
                "mentionText": "0,830",
                "confidence": 0.91427535,
                "pageAnchor": {
                    "pageRefs": [
                        {
                            "boundingPoly": {
                                "normalizedVertices": [
                                    {
                                        "x": 0.6627008,
                                        "y": 0.41295207
                                    },
                                    {
                                        "x": 0.6942296,
                                        "y": 0.41295207
                                    },
                                    {
                                        "x": 0.6942296,
                                        "y": 0.42262405
                                    },
                                    {
                                        "x": 0.6627008,
                                        "y": 0.42262405
                                    }
                                ]
                            }
                        }
                    ]
                },
                "id": "22"
            },
            {
                "textAnchor": {
                    "textSegments": [
                        {
                            "startIndex": "557",
                            "endIndex": "563"
                        }
                    ],
                    "content": "726,00"
                },
                "type": "line_item/amount",
                "mentionText": "726,00",
                "confidence": 0.45034236,
                "pageAnchor": {
                    "pageRefs": [
                        {
                            "boundingPoly": {
                                "normalizedVertices": [
                                    {
                                        "x": 0.7763236,
                                        "y": 0.41295207
                                    },
                                    {
                                        "x": 0.8143962,
                                        "y": 0.41295207
                                    },
                                    {
                                        "x": 0.8143962,
                                        "y": 0.42262405
                                    },
                                    {
                                        "x": 0.7763236,
                                        "y": 0.42262405
                                    }
                                ]
                            }
                        }
                    ]
                },
                "id": "23",
                "normalizedValue": {
                    "text": "726",
                    "floatValue": 726
                }
            },
            {
                "textAnchor": {
                    "textSegments": [
                        {
                            "startIndex": "578",
                            "endIndex": "838"
                        }
                    ],
                    "content": "Adresse du bâtiment : Rue Théodore de Cuyper\n212 à 1200 Bruxelles\nMaintenance préventive 4 visites annuelles.\nNuméro de contrat : 3346\nIndice Abex de base : 858 - nov 2020\nIndice Abex actuel : 906 - nov 2021\nPériode de facturation :\ndu 01/03/2022 au 31/12/2022"
                },
                "type": "line_item/description",
                "mentionText": "Adresse du bâtiment : Rue Théodore de Cuyper\n212 à 1200 Bruxelles\nMaintenance préventive 4 visites annuelles.\nNuméro de contrat : 3346\nIndice Abex de base : 858 - nov 2020\nIndice Abex actuel : 906 - nov 2021\nPériode de facturation :\ndu 01/03/2022 au 31/12/2022",
                "confidence": 0.45194054,
                "pageAnchor": {
                    "pageRefs": [
                        {
                            "boundingPoly": {
                                "normalizedVertices": [
                                    {
                                        "x": 0.18143962,
                                        "y": 0.37888983
                                    },
                                    {
                                        "x": 0.55621654,
                                        "y": 0.37888983
                                    },
                                    {
                                        "x": 0.55621654,
                                        "y": 0.49495375
                                    },
                                    {
                                        "x": 0.18143962,
                                        "y": 0.49495375
                                    }
                                ]
                            }
                        }
                    ]
                },
                "id": "24"
            }
        ]
    }
]
*/