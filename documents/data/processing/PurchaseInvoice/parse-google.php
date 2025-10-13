<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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


/**
 * Helper: find entity by type
 */
$getEntity = function (string $type) use ($entities) {
    foreach ($entities as $entity) {
        if ($entity['type'] === $type) {
            return $entity;
        }
    }
    return null;
};

/**
 * Helper: safely extract normalized or mentioned value
 */
$getValue = function (?array $entity, $default = null, float $min_confidence = 0.0) {
    if(!$entity) {
        return $default;
    }
    if(isset($entity['confidence']) && $entity['confidence'] < $min_confidence) {
        return $default;
    }
    if(isset($entity['normalizedValue']['text'])) {
        return trim($entity['normalizedValue']['text']);
    }
    if(isset($entity['mentionText'])) {
        return trim($entity['mentionText']);
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
 * Extract base entities
 */
$localeCountry = 'BE';


$invoiceType        = $getValue($getEntity('invoice_type'));
$issueDate          = $getValue($getEntity('invoice_date'));
$dueDate            = $getValue($getEntity('due_date'));
$totalNet           = $getValue($getEntity('net_amount'), 0);
$totalTax           = $getValue($getEntity('total_tax_amount'), 0);
$totalAmount        = $getValue($getEntity('total_amount'), 0);
$supplierName       = $getValue($getEntity('supplier_name'));
$supplierVat        = $getValue($getEntity('supplier_tax_id'));
$supplierIban       = str_replace(' ', '', $getValue($getEntity('supplier_iban'), ''));
$supplierBic        = $getValue($getEntity('supplier_bic'), '');
$supplierPaymentRef = $getValue($getEntity('supplier_payment_ref'), '');
$customerName       = $getValue($getEntity('customer_name'), '');
$currency           = $getValue($getEntity('currency'), 'EUR');

/**
 * Extract line items
 */
$lines = [];
foreach($entities as $entity) {
    if($entity['type'] === 'line_item') {
        $line = [
            'id' => (string)($entity['id'] ?? count($lines) + 1),
            'description' => $entity['mentionText'] ?? null,
            'quantity' => 1,
            'unit_code' => 'C62',
            'unit_price' => null,
            'amount' => null,
            'tax' => [
                'category_id' => 'S',
                'percent' => 0,
                'scheme_id' => 'VAT'
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
        'address' => $extractAddress($getValue($getEntity('supplier_address')), $localeCountry),
    ],
    'customer' => [
        'name'    => $customerName,
        'vat_id'  => $getValue($getEntity('customer_tax_id')),
        'address' => $getValue($getEntity('customer_address'), $localeCountry),
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
        'payment_means_code' => '30'
    ]
];

$context->httpResponse()
        ->body($output)
        ->send();
