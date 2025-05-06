<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'description'   => 'Convert a UBL XML formatted invoice to a JSON structure.',
    'params'        => [
        'xml' =>  [
            'description'       => 'XML content.',
            'type'              => 'string',
            'required'          => true
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth', 'access']
]);

/**
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \fmt\access\AccessController        $access
 * @var \equal\php\Context                  $context
 */
['access' => $access, 'auth' => $auth, 'context' => $context] = $providers;

$xml = $params['xml'];

$xml = simplexml_load_string($xml);
$namespaces = $xml->getNamespaces(true);

$xml->registerXPathNamespace('cbc', $namespaces['cbc']);
$xml->registerXPathNamespace('cac', $namespaces['cac']);

$ublVersion = $xml->xpath('//cbc:UBLVersionID');
$ubl_version_code = !empty($ublVersion) ? (string) $ublVersion[0] : null;

if($ubl_version_code !== '2.1') {
    throw new Exception('ubl_version_mismatch', EQ_ERROR_INVALID_PARAM);
}


$data = [];

// main fields
$data['invoice_number'] = (string) $xml->xpath('//cbc:ID')[0];
$data['issue_date'] = (string) $xml->xpath('//cbc:IssueDate')[0];
$data['due_date'] = (string) ($xml->xpath('//cbc:DueDate')[0] ?? null);
$data['currency'] = (string) $xml->xpath('//cbc:DocumentCurrencyCode')[0] ?? 'EUR';

// supplier
$data['supplier'] = [
        'name'      => (string) $xml->xpath('//cac:AccountingSupplierParty//cbc:Name')[0] ?? '',
        'street'    => (string) $xml->xpath('//cac:AccountingSupplierParty//cac:PostalAddress//cbc:StreetName')[0] ?? '',
        'vat'       => (string) $xml->xpath('//cac:AccountingSupplierParty//cac:PartyTaxScheme//cbc:CompanyID')[0] ?? '',
    ];

// customer
$data['customer'] = [
        'name'      => (string) $xml->xpath('//cac:AccountingCustomerParty//cbc:Name')[0] ?? '',
        'street'    => (string) $xml->xpath('//cac:AccountingCustomerParty//cac:PostalAddress//cbc:StreetName')[0] ?? '',
    ];

// customer reference
$buyerRef = $xml->xpath('//cbc:BuyerReference');
$data['customer_reference'] = !empty($buyerRef) ? (string) $buyerRef[0] : null;

// invoicing period
$start = $xml->xpath('//cac:InvoicePeriod//cbc:StartDate');
$end = $xml->xpath('//cac:InvoicePeriod//cbc:EndDate');
if (!empty($start) || !empty($end)) {
    $data['billing_period'] = [
        'start' => !empty($start) ? (string) $start[0] : null,
        'end'   => !empty($end) ? (string) $end[0] : null,
    ];
}

// payment reference
$comm = $xml->xpath('//cbc:PaymentMeansNote');
$data['payment_reference'] = !empty($comm) ? (string) $comm[0] : null;

// bank account
$iban = $xml->xpath('//cac:PaymentMeans//cac:PayeeFinancialAccount//cbc:ID');
$data['payment_account'] = !empty($iban) ? (string) $iban[0] : null;

$account_name = $xml->xpath('//cac:PaymentMeans//cac:PayeeFinancialAccount//cbc:Name');
if (!empty($account_name)) {
    $data['payment_account_name'] = (string) $account_name[0];
}

// payment type (code ISO 20022 / UNCL4461)
$paymentCode = $xml->xpath('//cac:PaymentMeans//cbc:PaymentMeansCode');
$paymentCodeValue = !empty($paymentCode) ? (string) $paymentCode[0] : null;
$data['payment_type'] = $paymentCodeValue;
$data['auto_debit'] = ($paymentCodeValue === '30');

// invoice lines
$data['lines'] = [];
$lines = $xml->xpath('//cac:InvoiceLine');
foreach ($lines as $line) {
    $line->registerXPathNamespace('cbc', $namespaces['cbc']);
    $line->registerXPathNamespace('cac', $namespaces['cac']);

    $data['lines'][] = [
            'id'            => (string) $line->xpath('./cbc:ID')[0],
            'description'   => (string) ($line->xpath('.//cbc:Description')[0] ?? ''),
            'amount'        => (float) ($line->xpath('./cbc:LineExtensionAmount')[0] ?? 0),
            'unit_price'    => (float) ($line->xpath('.//cac:Price//cbc:PriceAmount')[0] ?? 0),
        ];
}

// total amounts
$data['totals'] = [
        'subtotal'          => (float) $xml->xpath('//cac:LegalMonetaryTotal//cbc:LineExtensionAmount')[0],
        'total_excl_tax'    => (float) $xml->xpath('//cac:LegalMonetaryTotal//cbc:TaxExclusiveAmount')[0],
        'total_incl_tax'    => (float) $xml->xpath('//cac:LegalMonetaryTotal//cbc:TaxInclusiveAmount')[0],
        'payable_amount'    => (float) $xml->xpath('//cac:LegalMonetaryTotal//cbc:PayableAmount')[0]
    ];

$context->httpResponse()
        ->body($data)
        ->send();
