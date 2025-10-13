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
$invoiceType    = $getValue($getEntity('invoice_type'));
$issueDate      = $getValue($getEntity('invoice_date'));
$dueDate        = $getValue($getEntity('due_date'));
$totalNet       = $getValue($getEntity('net_amount'), 0);
$totalTax       = $getValue($getEntity('total_tax_amount'), 0);
$totalAmount    = $getValue($getEntity('total_amount'), 0);
$supplierName   = $getValue($getEntity('supplier_name'));
$supplierVat    = $getValue($getEntity('supplier_tax_id'));
$supplierIban   = $getValue($getEntity('supplier_iban'));
$supplierAddress= $getValue($getEntity('supplier_address'));
$customerName   = $getValue($getEntity('customer_name'));
$currency       = $getValue($getEntity('currency'), 'EUR');

/**
 * Extract line items
 */
$lines = [];
foreach ($entities as $entity) {
    if ($entity['type'] === 'line_item') {
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

/**
 * Final output structure (aligned with Mindee version)
 */
$output = [
    'document_type'     => $invoiceType ?? 'unknown',
    'invoice_number'    => $getValue($getEntity('invoice_id')),
    'invoice_type'      => $invoiceType,
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
        'address' => $getValue($getEntity('customer_address')),
    ],
    'lines' => $lines,
    'totals' => [
        'total_excl_tax' => (float)$totalNet,
        'total_tax'      => (float)$totalTax,
        'total_incl_tax' => (float)$totalAmount,
        'payable_amount' => (float)$totalAmount,
    ],
    'payment' => [
        'iban'               => $supplierIban,
        'bic'                => $getValue($getEntity('supplier_bic')),
        'payment_id'         => null,
        'payment_means_code' => '30'
    ]
];

$context->httpResponse()
        ->body($output)
        ->send();
