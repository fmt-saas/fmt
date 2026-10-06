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
            'type'              => 'text',
            'usage'             => 'text/plain.medium',
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

$data['document_type'] = $xpathValue($xml, '//sh:StandardBusinessDocument/sh:StandardBusinessDocumentHeader/sh:DocumentIdentification/sh:Type', 'Invoice');

// main fields
$data['invoice_number'] = $xpathValue($xml, '//cbc:ID', '');
$issue_date = strtotime($xpathValue($xml, '//cbc:IssueDate', '1970-01-01'));
$data['issue_date'] = date('c', $issue_date);
$due_date = strtotime($xpathValue($xml, '//cbc:DueDate', '1970-01-01'));
$data['due_date'] = date('c', $due_date);
$data['currency'] = $xpathValue($xml, '//cbc:DocumentCurrencyCode', 'EUR');

// supplier
$data['supplier'] = [
        'name'          => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PartyName/cbc:Name', ''),
        'vat_id'        => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PartyTaxScheme//cbc:CompanyID', ''),
        'company_id'    => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PartyLegalEntity//cbc:CompanyID'),
        'address'       => [
            'street'        => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PostalAddress//cbc:StreetName', ''),
            'city'          => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PostalAddress//cbc:CityName', ''),
            'postal_code'   => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PostalAddress//cbc:PostalZone', ''),
            'country'       => $xpathValue($xml, '//cac:AccountingSupplierParty//cac:PostalAddress//cac:Country//cbc:IdentificationCode', '')
        ]
    ];

$name = $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PartyName/cbc:Name', '');
if(empty($name)) {
    $name = $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PartyLegalEntity/cbc:RegistrationName', '');
}

// customer
$data['customer'] = [
        'name'          => $name,
        'vat_id'        => $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PartyTaxScheme//cbc:CompanyID'),
        'company_id'    => $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PartyLegalEntity//cbc:CompanyID'),
        'address'       => [
            'street'        => $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PostalAddress//cbc:StreetName', ''),
            'city'          => $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PostalAddress//cbc:CityName', ''),
            'postal_code'   => $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PostalAddress//cbc:PostalZone', ''),
            'country'       => $xpathValue($xml, '//cac:AccountingCustomerParty//cac:PostalAddress//cac:Country//cbc:IdentificationCode', '')
        ]
    ];

// customer reference
$data['buyer_reference'] = $xpathValue($xml, '//cbc:BuyerReference');

// invoicing period
$start = $xpathValue($xml, '//cac:InvoicePeriod//cbc:StartDate', null);
$end = $xpathValue($xml, '//cac:InvoicePeriod//cbc:EndDate', null);
if($start || $end) {
    $data['invoice_period'] = [
        'start_date'    => $start,
        'end_date'      => $end
    ];
}

$data['payment'] = [
    'iban'                  => $xpathValue($xml, '//cac:PaymentMeans//cac:PayeeFinancialAccount//cbc:ID', null),
    'bic'                   => $xpathValue($xml, '//cac:PaymentMeans//cac:PayeeFinancialAccount//cac:FinancialInstitutionBranch//cbc:ID', null),
    'payment_id'            => $xpathValue($xml, '//cac:PaymentMeans//cac:PaymentMandate//cbc:ID'),
    'payment_means_code'    => $xpathValue($xml, '//cac:PaymentMeans//cbc:PaymentMeansCode', null)
];

// invoice lines
$data['lines'] = [];

$lines = $xml->xpath('//cac:InvoiceLine');

foreach($lines as $line) {
    $line->registerXPathNamespace('cbc', $namespaces['cbc']);
    $line->registerXPathNamespace('cac', $namespaces['cac']);

    $description = (string) $xpathValue($line, './/cbc:Description', '');
    if(empty($description)) {
        $description = (string) $xpathValue($line, './/cbc:Name', '');
    }

    $data['lines'][] = [
        'id'            => (string) $xpathValue($line, './cbc:ID', ''),
        'description'   => $description,
        'amount'        => (float) $xpathValue($line, './cbc:LineExtensionAmount', 0.0),
        'unit_price'    => (float) $xpathValue($line, './/cac:Price//cbc:PriceAmount', 0.0),
        'unit_code'     => (string) $xpathValue($line, './/cbc:InvoicedQuantity/@unitCode', ''),
        'quantity'      => (float) $xpathValue($line, './cbc:InvoicedQuantity', 1.0),
        'tax'           => [
            'category_id'   => $xpathValue($line, './/cac:Item//cac:ClassifiedTaxCategory//cbc:ID'),
            'percent'       => (float) $xpathValue($line, './/cac:Item//cac:ClassifiedTaxCategory//cbc:Percent') * 0.01,
            'scheme_id'     => $xpathValue($line, './/cac:Item//cac:ClassifiedTaxCategory//cac:TaxScheme//cbc:ID')
        ]
    ];
}

$total_excl_tax = (float) $xpathValue($xml, '//cac:LegalMonetaryTotal//cbc:TaxExclusiveAmount', 0.0);
$total_incl_tax = (float) $xpathValue($xml, '//cac:LegalMonetaryTotal//cbc:TaxInclusiveAmount', 0.0);

// total amounts
$data['totals'] = [
    'total_excl_tax'    => $total_excl_tax,
    'total_tax'         => round($total_incl_tax - $total_excl_tax, 2),
    'total_incl_tax'    => $total_incl_tax,
    'payable_amount'    => (float) $xpathValue($xml, '//cac:LegalMonetaryTotal//cbc:PayableAmount', 0.0)
];

// invoice attachments
$data['attachments'] = [];

$attachments = $xml->xpath('//cac:Attachment');
foreach($attachments as $attachment) {
    $data['attachments'][] = [
        'content_type'  => $xpathValue($attachment, './/cbc:EmbeddedDocumentBinaryObject/@mimeCode'),
        'name'          => $xpathValue($attachment, './/cbc:EmbeddedDocumentBinaryObject/@filename'),
        'data'          => $xpathValue($attachment, './/cbc:EmbeddedDocumentBinaryObject')
    ];
}

$context
    ->httpResponse()
    ->body($data)
    ->send();
