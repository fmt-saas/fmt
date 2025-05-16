<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
[$params, $providers] = eQual::announce([
    'description'   => 'Return a purchase-invoice JSON descriptor from a JSON prediction provided by Mindee API.',
    'help'          => "Detailed Mindee API response values here: https://developers.mindee.com/docs/invoice-ocr#api-response",
    'params'        => [
        'json' =>  [
            'type'              => 'string',
            'usage'             => 'text/json',
            'description'       => 'JSON `prediction` as returned from Mindee API service.',
            'required'          => true
        ],
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

$prediction = json_decode($params['json'], true);

$extractAddress = function ($address, $default_country) {
    if(!$address) {
        return null;
    }
    $streetParts = [];
    if(!empty($address['street_name'])) {
        $streetParts[] = $address['street_name'];
    }
    if(!empty($address['street_number'])) {
        $streetParts[] = $address['street_number'];
    }
    if(!empty($address['address_complement'])) {
        $streetParts[] = $address['address_complement'];
    }

    return [
        'street'        => implode(' ', $streetParts),
        'city'          => $address['city'] ?? null,
        'postal_code'   => $address['postal_code'] ?? null,
        'country'       => $address['country'] ?? $default_country
    ];
};

$formatDate = function ($date) { return $date ? $date . 'T00:00:00Z' : null; };

// for all requested value, check confidence and presence
$getProperty = function ($key, $default = -1) use($prediction) {
    $arr = $prediction[$key];
    if(!isset($arr['confidence'])) {
        throw new Exception('missing_confidence_for_property_' . $key, EQ_ERROR_INVALID_PARAM);
    }
    if($arr['confidence'] < 0.85) {
        if($default === -1) {
            throw new Exception('insufficient_confidence_for_property_' . $key, EQ_ERROR_INVALID_PARAM);
        }
        else {
            return $default;
        }
    }
    if(!isset($arr['value']) && $default === -1) {
        throw new Exception('missing_mandatory_value', EQ_ERROR_INVALID_PARAM);
    }
    return $arr['value'] ?? $default;
};

$locale = $getProperty('locale', 'fr-BE');
$localeCountry = $prediction['locale']['country'] ?? 'BE';
$localeCurrency = $prediction['locale']['currency'] ?? 'EUR';
$localeTaxPercent = 21;

$supplier_vat = null;
foreach($prediction['supplier_company_registrations'] ?? [] as $registration) {
    if(in_array($registration['type'], ['VAT', 'VAT NUMBER'], true)) {
        $supplier_vat = $registration['value'];
        break;
    }
}

$customer_vat = null;
foreach($prediction['customer_company_registrations'] ?? [] as $registration) {
    if(in_array($registration['type'], ['VAT', 'VAT NUMBER'], true)) {
        $customer_vat = $registration['value'];
        break;
    }
}

if(!isset($supplier_vat)) {
    throw new Exception('missing_mandatory_seller_vat', EQ_ERROR_INVALID_PARAM);
}

/*
    Possible values from Mindee API v4:
    https://developers.mindee.com/docs/php-invoice-ocr#document-type

        CREDIT NOTE: Reduces the amount a buyer owes.
        INVOICE: Requests payment for goods or services.
        PAYSLIP: Details employee earnings and deductions.
        PURCHASE ORDER: Buyer's official request to purchase.
        QUOTE: Seller's estimated cost for goods or services.
        RECEIPT: Acknowledges payment.
        STATEMENT: Summary of financial transactions over a period.
        OTHER FINANCIAL: Miscellaneous financial documents.
        OTHER: Documents not fitting other financial categories.
*/
$map_document_type = [
    'CREDIT NOTE'    => 'credit_note',
    'INVOICE'        => 'invoice',
    'PURCHASE ORDER' => 'purchase_order',
    'QUOTE'          => 'quote'
];

$mindee_doc_type = $getProperty('document_type_extended');

$document_type = $map_document_type[$mindee_doc_type] ?? 'unknown';

$output = [
    'document_type'     => $document_type,
    'invoice_number'    => $getProperty('invoice_number'),
    'invoice_type'      => strtolower(str_replace(' ', '_', $mindee_doc_type)),
    'issue_date'        => $formatDate($getProperty('date')),
    'due_date'          => $formatDate($getProperty('due_date')),
    'currency'          => $localeCurrency,
    'buyer_reference'   => $getProperty('po_number', null), // accept low confidence
    'supplier' => [
        'name'              => $getProperty('supplier_name'),
        'vat_id'            => $supplier_vat,
        'address'           => $extractAddress($prediction['supplier_address'], $localeCountry),
    ],
    'customer' => [
        'name'              => $getProperty('customer_name'),
        'customer_number'   => $getProperty('customer_id', null),
        'vat_id'            => $customer_vat,
        'address'           => $extractAddress($prediction['customer_address'], $localeCountry),
    ],
    'lines' => [],
    'totals' => [
        'total_excl_tax'    => (float) $getProperty('total_net'),
        'total_tax'         => (float) $getProperty('total_tax'),
        'total_incl_tax'    => (float) $getProperty('total_amount'),
        'payable_amount'    => (float) $getProperty('total_amount'),
    ],
    'payment' => [
        'iban'              => $prediction['supplier_payment_details'][0]['iban'] ?? null,
        'bic'               => $prediction['supplier_payment_details'][0]['swift'] ?? null,
        'payment_id'        => $prediction['supplier_payment_details'][0]['routing_number'] ?? null,
        'payment_means_code' => '30'
    ]
];

foreach($prediction['line_items'] as $i => $line) {
    $output['lines'][] = [
        'id'            => (string) ($i + 1),
        'description'   => $line['description'],
        'quantity'      => $line['quantity'] ?? 1,
        'unit_code'     => $line['unit_measure'] ?? 'C62',
        'unit_price'    => $line['unit_price'] ?? $line['total_amount'],
        'amount'        => $line['total_amount'],
        'tax'           => [
            'category_id'   => 'S',
            'percent'       => $line['tax_rate'] ?? ($prediction['taxes'][0]['rate'] ?? $localeTaxPercent),
            'scheme_id'     => 'VAT'
        ]
    ];
}

$context->httpResponse()
        ->body($output)
        ->send();
