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
    'invoice_number'    => $getValue($getEntity('invoice_id')),
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
