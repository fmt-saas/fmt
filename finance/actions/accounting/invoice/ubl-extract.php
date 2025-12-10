<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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


$xpathValue = function ($xml, $query, $default = null) {
    $result = $xml->xpath($query);
    return (!empty($result) && isset($result[0])) ? ( (string) $result[0] ) : $default;
};


$xml = $params['xml'];

$xml = simplexml_load_string($xml);

if($xml === false) {
    throw new Exception('invalid_xml', EQ_ERROR_INVALID_PARAM);
}

$namespaces = $xml->getNamespaces(true);

if(!isset($namespaces['cbc']) || !isset($namespaces['cac'])) {
    throw new Exception('missing_mandatory_namespace', EQ_ERROR_INVALID_PARAM);
}

$xml->registerXPathNamespace('cbc', $namespaces['cbc']);
$xml->registerXPathNamespace('cac', $namespaces['cac']);

if(isset($namespaces['sh'])) {
    $xml->registerXPathNamespace('sh', $namespaces['sh']);
}

$ubl_version_code = $xpathValue($xml, '//cbc:UBLVersionID', null);

if($ubl_version_code && $ubl_version_code !== '2.1') {
    throw new Exception('ubl_version_mismatch', EQ_ERROR_INVALID_PARAM);
}


$data = [];

$data['document_type'] = strtolower($xpathValue($xml, '//sh:StandardBusinessDocument/sh:StandardBusinessDocumentHeader/sh:DocumentIdentification/sh:Type', 'invoice'));

// main fields
$data['invoice_number'] = $xpathValue($xml, '//cbc:ID', '');
$data['issue_date'] = $xpathValue($xml, '//cbc:IssueDate', '');
$data['due_date'] = $xpathValue($xml, '//cbc:DueDate', null);
$data['currency'] = $xpathValue($xml, '//cbc:DocumentCurrencyCode', 'EUR');

// supplier
$data['supplier'] = [
        'name'      => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PartyName/cbc:Name', ''),
        'street'    => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PostalAddress//cbc:StreetName', ''),
        'vat_id'    => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PartyTaxScheme//cbc:CompanyID', ''),
        'company_id'=> $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PartyLegalEntity//cbc:CompanyID')
    ];

// customer
$data['customer'] = [
        'name'      => $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PartyName/cbc:Name', ''),
        'street'    => $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PostalAddress//cbc:StreetName', ''),
        'vat_id'    => $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PartyTaxScheme//cbc:CompanyID'),
        'company_id'=> $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PartyLegalEntity//cbc:CompanyID')
    ];

// customer reference
$data['customer_reference'] = $xpathValue($xml, '//cbc:BuyerReference');

// invoicing period
$start = $xpathValue($xml, '//cac:InvoicePeriod//cbc:StartDate', null);
$end = $xpathValue($xml, '//cac:InvoicePeriod//cbc:EndDate', null);
if($start || $end) {
    $data['billing_period'] = [
        'start' => $start,
        'end'   => $end
    ];
}

// payment reference
$data['payment_reference'] = $xpathValue($xml, '//cbc:PaymentMeansNote');

// bank account
$data['payment_account'] = $xpathValue($xml, '//cac:PaymentMeans//cac:PayeeFinancialAccount//cbc:ID', null);

$account_name = $xpathValue($xml, '//cac:PaymentMeans//cac:PayeeFinancialAccount//cbc:Name', null);
if($account_name) {
    $data['payment_account_name'] = $account_name;
}

// payment type (code ISO 20022 / UNCL4461)
$paymentCodeValue = $xpathValue($xml, '//cac:PaymentMeans//cbc:PaymentMeansCode', null);
$data['payment_type'] = $paymentCodeValue;
// 49 – SEPA Direct Debit
$data['auto_debit'] = ($paymentCodeValue === '49');

// invoice lines
$data['lines'] = [];

$lines = $xml->xpath('//cac:InvoiceLine');

foreach($lines as $line) {
    $line->registerXPathNamespace('cbc', $namespaces['cbc']);
    $line->registerXPathNamespace('cac', $namespaces['cac']);

    $data['lines'][] = [
            'id'            => (string)  $xpathValue($line, './cbc:ID', ''),
            'description'   => (string)  $xpathValue($line, './/cbc:Description', ''),
            'amount'        => (float)   $xpathValue($line, './cbc:LineExtensionAmount', 0),
            'unit_price'    => (float)   $xpathValue($line, './/cac:Price//cbc:PriceAmount', 0)
        ];
}

// total amounts
$data['totals'] = [
        'subtotal'          => (float) $xpathValue($xml, '//cac:LegalMonetaryTotal//cbc:LineExtensionAmount', 0),
        'total_excl_tax'    => (float) $xpathValue($xml, '//cac:LegalMonetaryTotal//cbc:TaxExclusiveAmount', 0),
        'total_incl_tax'    => (float) $xpathValue($xml, '//cac:LegalMonetaryTotal//cbc:TaxInclusiveAmount', 0),
        'payable_amount'    => (float) $xpathValue($xml, '//cac:LegalMonetaryTotal//cbc:PayableAmount', 0)
    ];

$context->httpResponse()
        ->body($data)
        ->send();
