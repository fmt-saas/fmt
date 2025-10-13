<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
[$params, $providers] = eQual::announce([
    'description'   => 'Return an empty purchase-invoice JSON descriptor compliant with `urn:fmt:json-schema:finance:purchase-invoice`.',
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


$locale = 'fr-BE';
$localeCountry = 'BE';
$localeCurrency = 'EUR';
$localeTaxPercent = 21;

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


$output = [
    'document_type'     => 'invoice',
    'invoice_number'    => '',
    'invoice_type'      => 'INVOICE',
    'issue_date'        => gmdate("Y-m-d\TH:i:s\Z"),
    'due_date'          => gmdate("Y-m-d\TH:i:s\Z"),
    'currency'          => $localeCurrency,
    'buyer_reference'   => '',
    'supplier' => [
        'name'              => '',
        'vat_id'            => '',
        'address'           => '',
    ],
    'customer' => [
        'name'              => '',
        'customer_number'   => '',
        'vat_id'            => '',
        'address'           => [
            'street'        => '',
            'city'          => '',
            'postal_code'   => '',
            'country'       => '',
        ]
    ],
    'lines' => [],
    'totals' => [
        'total_excl_tax'    => 0.0,
        'total_tax'         => 0.0,
        'total_incl_tax'    => 0.0,
        'payable_amount'    => 0.0,
    ],
    'payment' => [
        'iban'              => '',
        'bic'               => '',
        'payment_id'        => null,
        'payment_means_code' => '30'
    ]
];

$context->httpResponse()
        ->body($output)
        ->send();
