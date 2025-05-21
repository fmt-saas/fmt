<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

// test UBL conversion


$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$xml1 = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:qdt="urn:oasis:names:specification:ubl:schema:xsd:QualifiedDataTypes-2" xmlns:udt="urn:oasis:names:specification:ubl:schema:xsd:UnqualifiedDataTypes-2" xmlns:ccts="urn:un:unece:uncefact:documentation:2">
  <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
  <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
  <cbc:ID>744000399977</cbc:ID>
  <cbc:IssueDate>2024-12-15</cbc:IssueDate>
  <cbc:DueDate>2025-01-14</cbc:DueDate>
  <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
  <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
  <cac:AccountingSupplierParty>
    <cac:Party>
      <cbc:EndpointID schemeID="0208">0202962701</cbc:EndpointID>
      <cac:PartyName>
        <cbc:Name>VIVAQUA</cbc:Name>
      </cac:PartyName>
      <cac:PostalAddress>
        <cbc:StreetName>boulevard de l\'Impératrice 17-19</cbc:StreetName>
        <cbc:CityName>Bruxelles</cbc:CityName>
        <cbc:PostalZone>1000</cbc:PostalZone>
        <cac:Country>
          <cbc:IdentificationCode>BE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PostalAddress>
      <cac:PartyTaxScheme>
        <cbc:CompanyID>BE0202962701</cbc:CompanyID>
        <cac:TaxScheme>
          <cbc:ID>VAT</cbc:ID>
        </cac:TaxScheme>
      </cac:PartyTaxScheme>
      <cac:PartyLegalEntity>
        <cbc:RegistrationName>VIVAQUA</cbc:RegistrationName>
        <cbc:CompanyID>BE0202962701</cbc:CompanyID>
      </cac:PartyLegalEntity>
    </cac:Party>
  </cac:AccountingSupplierParty>
  <cac:AccountingCustomerParty>
    <cac:Party>
      <cbc:EndpointID schemeID=""/>
      <cac:PartyName>
        <cbc:Name>ACP RESIDENCE THEO C/O YVAN FLION</cbc:Name>
      </cac:PartyName>
      <cac:PostalAddress>
        <cbc:StreetName>CHEE DE LOUVAIN 267 BTE 12</cbc:StreetName>
        <cbc:CityName>SCHAERBEEK</cbc:CityName>
        <cbc:PostalZone>1030</cbc:PostalZone>
        <cac:Country>
          <cbc:IdentificationCode>BE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PostalAddress>
      <cac:PartyTaxScheme>
        <cbc:CompanyID/>
        <cac:TaxScheme>
          <cbc:ID>VAT</cbc:ID>
        </cac:TaxScheme>
      </cac:PartyTaxScheme>
      <cac:PartyLegalEntity>
        <cbc:RegistrationName>ACP RESIDENCE THEO C/O YVAN FLION</cbc:RegistrationName>
        <cbc:CompanyID/>
      </cac:PartyLegalEntity>
    </cac:Party>
  </cac:AccountingCustomerParty>
  <cac:PaymentMeans>
    <cbc:PaymentMeansCode>30</cbc:PaymentMeansCode>
    <cac:PayeeFinancialAccount>
      <cbc:ID>BE52096011784309</cbc:ID>
      <cbc:Name>VIVAQUA</cbc:Name>
      <cac:FinancialInstitutionBranch>
        <cbc:ID/>
      </cac:FinancialInstitutionBranch>
    </cac:PayeeFinancialAccount>
  </cac:PaymentMeans>
  <cac:TaxTotal>
    <cbc:TaxAmount currencyID="EUR">63.11</cbc:TaxAmount>
    <cac:TaxSubtotal>
      <cbc:TaxableAmount currencyID="EUR">1051.89</cbc:TaxableAmount>
      <cbc:TaxAmount currencyID="EUR">63.11</cbc:TaxAmount>
      <cac:TaxCategory>
        <cbc:ID>S</cbc:ID>
        <cbc:Percent>6.00</cbc:Percent>
        <cac:TaxScheme>
          <cbc:ID>VAT</cbc:ID>
        </cac:TaxScheme>
      </cac:TaxCategory>
    </cac:TaxSubtotal>
  </cac:TaxTotal>
  <cac:LegalMonetaryTotal>
    <cbc:LineExtensionAmount currencyID="EUR">1051.89</cbc:LineExtensionAmount>
    <cbc:TaxExclusiveAmount currencyID="EUR">1051.89</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount currencyID="EUR">1115.00</cbc:TaxInclusiveAmount>
    <cbc:PayableAmount currencyID="EUR">1115.00</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
  <cac:InvoiceLine>
    <cbc:ID>1</cbc:ID>
    <cbc:InvoicedQuantity unitCode="C62">1.00</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID="EUR">1051.89</cbc:LineExtensionAmount>
    <cac:Item>
      <cbc:Name>Total de la facture (HTVA) TVA 6%</cbc:Name>
      <cac:ClassifiedTaxCategory>
        <cbc:ID>S</cbc:ID>
        <cbc:Percent>6.00</cbc:Percent>
        <cac:TaxScheme>
          <cbc:ID>VAT</cbc:ID>
        </cac:TaxScheme>
      </cac:ClassifiedTaxCategory>
    </cac:Item>
    <cac:Price>
      <cbc:PriceAmount currencyID="EUR">1051.89</cbc:PriceAmount>
    </cac:Price>
  </cac:InvoiceLine>
</Invoice>';


/**
 * #memo - in general config.json, DEFAULT_RIGHTS is expected to be set to 0.
 */
$tests = [
    '0101' => [
            'description'       =>  "Retrieve Access Controller.",
            'help'              =>  "Access Controller service should be overridden by the one present in `fmt/lib` directory. ",
            'return'            =>  ['object'],
            'act'               =>  function () use($xml1) {
                    $json = convertUblToJson($xml1);
                    $xml2 = convertJsonToUbl($json);

                    return $xml2;
                },
            'assert'            =>  function($xml2) use($xml1) {
                    return areXmlsEqual($xml1, $xml2);
                }
        ]
];

function areXmlsEqual(string $xml1, string $xml2): bool {
    $dom1 = new DOMDocument();
    $dom2 = new DOMDocument();

    $dom1->preserveWhiteSpace = false;
    $dom2->preserveWhiteSpace = false;

    $dom1->loadXML($xml1);
    $dom2->loadXML($xml2);

    $dom1->formatOutput = true;
    $dom2->formatOutput = true;

    return $dom1->C14N() === $dom2->C14N();
}

// #todo - move this somewhere else

/*
    Convert a JSON structure matching the 'purchase-invoice' schema to a valid UBL BIS3.0 XML (EN16931)
    Full specs here : https://docs.peppol.eu/poacc/billing/3.0/bis/
    Nodes details here: https://docs.peppol.eu/poacc/billing/3.0/syntax/ubl-invoice/tree/
*/
function convertJsonToUbl(string $json): string {
    $data = json_decode($json, true);

    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    $cbc = fn($name, $value = '') => $doc->createElement("cbc:$name", htmlspecialchars((string) $value));
    $cac = fn($name) => $doc->createElement("cac:$name");
    $decimal = fn($value) => number_format((float) $value, 2, '.', '');
    $getUblSchemeIdFromCountry = function (string $country_code) {
            // Map of country codes to PEPPOL scheme IDs
            static $map_scheme = [
                'BE' => '0208',
                'FR' => '9957',
                'DE' => '9930',
                'NL' => '9944',
                'LU' => '9938',
                'IT' => '0211',
                'ES' => '9920',
                'PT' => '9946',
                'IE' => '9935',
                'AT' => '9914',
                'CH' => '9927',
                'GB' => '9932',
                'UK' => '9932',
                'SE' => '0007',
                'DK' => '0096',
                'FI' => '0213',
                'NO' => '0192',
                'IS' => '0196',
                'GR' => '9933',
                'CY' => '9928',
                'CZ' => '9929',
                'SK' => '9950',
                'SI' => '9949',
                'HU' => '9910',
                'HR' => '9934',
                'PL' => '9945',
                'RO' => '9947',
                'BG' => '9926',
                'EE' => '9931',
                'LV' => '9939',
                'LT' => '9937',
                'MT' => '9943',
                'MC' => '9940',
                'LI' => '9936',
                'SM' => '9951',
                'VA' => '9953',
                'TR' => '9952',
                'AL' => '9923',
                'AD' => '9922',
                'BA' => '9924',
                'ME' => '9941',
                'MK' => '9942',
                'RS' => '9948',
                'US' => '9959',
            ];

            return $map_scheme[$country_code] ?? null;
        };

    // Create root <Invoice> node with required namespaces
    $invoice = $doc->createElement('Invoice');
    $invoice->setAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
    $invoice->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $invoice->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    $invoice->setAttribute('xmlns:qdt', 'urn:oasis:names:specification:ubl:schema:xsd:QualifiedDataTypes-2');
    $invoice->setAttribute('xmlns:udt', 'urn:oasis:names:specification:ubl:schema:xsd:UnqualifiedDataTypes-2');
    $invoice->setAttribute('xmlns:ccts', 'urn:un:unece:uncefact:documentation:2');

    // Mandatory header elements (order matters)
    $invoice->appendChild($cbc('CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0'));
    $invoice->appendChild($cbc('ProfileID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0'));
    // Unique number identification of the Invoice in the system of the Seller (mandatory)
    $invoice->appendChild($cbc('ID', $data['invoice_number']));
    // The date when the Invoice was issued. Format ="YYYY-MM-DD" (mandatory)
    $invoice->appendChild($cbc('IssueDate', date('Y-m-d', strtotime($data['issue_date']))));
    // The date when the payment is due. Format ="YYYY-MM-DD" (mandatory)
    $invoice->appendChild($cbc('DueDate', date('Y-m-d', strtotime($data['due_date']))));

    // PEPPOL (EN16931) 'invoice' -> 380 ; 'credit_note' -> 381
    $invoice->appendChild($cbc('InvoiceTypeCode', ($data['invoice_type'] === 'invoice') ? '380' : '381'));
    $invoice->appendChild($cbc('DocumentCurrencyCode', $data['currency'] ?? 'EUR'));

    if(!empty($data['buyer_reference'])) {
        $invoice->appendChild($cbc('BuyerReference', $data['buyer_reference']));
    }

    // InvoicePeriod (optional)
    if(isset($data['invoice_period']['start_date'], $data['invoice_period']['end_date'])) {
        $invoicePeriod = $cac('InvoicePeriod');
        // StartDate
        $invoicePeriod->appendChild($cbc('StartDate', date('Y-m-d', strtotime($data['invoice_period']['start_date']))));
        // EndDate
        $invoicePeriod->appendChild($cbc('EndDate', date('Y-m-d', strtotime($data['invoice_period']['end_date']))));
        $invoice->appendChild($invoicePeriod);
    }

    // AccountingSupplierParty and AccountingCustomerParty
    foreach(["supplier" => 'AccountingSupplierParty', "customer" => 'AccountingCustomerParty'] as $role => $tag) {
        if(!isset($data[$role])) {
            continue;
        }

        $party = $cac($tag);
        $partyParty = $cac('Party');

        // EndpointID with schemeID attribute
        $endpoint_id = substr($data[$role]['vat_id'], 2);
        $scheme_id = $getUblSchemeIdFromCountry(substr($data[$role]['vat_id'], 0, 2));
        $partyId = $cbc('EndpointID', $endpoint_id);
        $partyId->setAttribute('schemeID', $scheme_id);
        $partyParty->appendChild($partyId);

        // PartyName - legal or commercial name of the entity
        $partyName = $cac('PartyName');
        $partyName->appendChild($cbc('Name', $data[$role]['name']));
        $partyParty->appendChild($partyName);

        // PostalAddress - Party postal address (mandatory)
        // Subitems: StreetName (optional), AdditionalStreetName (optional), CityName (optional), PostalZone (optional), CountrySubentity (optional), AddressLine (optional), Country (mandatory)
        $postal = $cac('PostalAddress');

        foreach(['street' => 'StreetName', 'city' => 'CityName', 'postal_code' => 'PostalZone'] as $key => $name) {
            $postal->appendChild($cbc($name, $data[$role]['address'][$key]));
        }

        if(!empty($data[$role]['address']['country'])) {
            $country = $cac('Country');
            $country->appendChild($cbc('IdentificationCode', $data[$role]['address']['country']));
            $postal->appendChild($country);
        }
        $partyParty->appendChild($postal);


        $taxScheme = $cac('PartyTaxScheme');
        $taxScheme->appendChild($cbc('CompanyID', $data[$role]['vat_id']));

        // #memo - for Australia/new-Zealand the ID must be 'GST' (instead of 'VAT')
        $tax = $cac('TaxScheme');
        $tax->appendChild($cbc('ID', 'VAT'));
        $taxScheme->appendChild($tax);
        $partyParty->appendChild($taxScheme);

        $legalEntity = $cac('PartyLegalEntity');
        $legalEntity->appendChild($cbc('RegistrationName', $data[$role]['legal_name'] ?? $data[$role]['name']));
        $legalEntity->appendChild($cbc('CompanyID', $data[$role]['vat_id']));
        $partyParty->appendChild($legalEntity);

        $party->appendChild($partyParty);
        $invoice->appendChild($party);
    }

    // PaymentMeans
    /**
     * PaymentMeansCode values as per UN/CEFACT 4461 and PEPPOL BIS Billing 3.0
     *
     * Common values:
     * - 1   => "Not specified"                      // Payment means not specified
     * - 10  => "Cash"                               // Physical money
     * - 20  => "Cheque"                             // Payment by cheque
     * - 30  => "Credit transfer"                    // Standard bank transfer (most common)
     * - 31  => "SEPA Credit Transfer"               // Transfer within the SEPA zone
     * - 42  => "Direct debit"                       // Debtor account is debited automatically
     * - 48  => "Payment card"                       // Payment via debit/credit card
     * - 49  => "Credit transfer via e-banking"      // Initiated via online banking
     * - 50  => "Credit card"                        // Payment specifically via credit card
     * - 57  => "Mobile payment"                     // E.g. Apple Pay, Google Pay, Payconiq
     * - 58  => "Creditor’s invoicing portal"        // Paid through a supplier's web portal
     * - 97  => "Not applicable"                     // No payment method, e.g. proforma invoice
     */
    if (!empty($data['payment'])) {
        $paymentMeans = $cac('PaymentMeans');
        $paymentMeans->appendChild($cbc('PaymentMeansCode', $data['payment']['payment_means_code']));
        if (!empty($data['payment']['payment_id'])) {
            $paymentMeans->appendChild($cbc('PaymentID', $data['payment']['payment_id']));
        }
        $account = $cac('PayeeFinancialAccount');
        $account->appendChild($cbc('ID', $data['payment']['iban']));
        $account->appendChild($cbc('Name', $data['supplier']['name']));
        $fi = $cac('FinancialInstitutionBranch');
        $fi->appendChild($cbc('ID', $data['payment']['bic']));
        $account->appendChild($fi);
        $paymentMeans->appendChild($account);
        $invoice->appendChild($paymentMeans);
    }

    // TaxTotal (should reflect actual % used in InvoiceLines)
    $taxTotal = $cac('TaxTotal');
    $taxTotal->appendChild($cbc('TaxAmount', $decimal($data['totals']['total_tax'])))->setAttribute('currencyID', $data['currency']);
    $taxSubtotal = $cac('TaxSubtotal');
    $taxSubtotal->appendChild($cbc('TaxableAmount', $decimal($data['totals']['total_excl_tax'])))->setAttribute('currencyID', $data['currency']);
    $taxSubtotal->appendChild($cbc('TaxAmount', $decimal($data['totals']['total_tax'])))->setAttribute('currencyID', $data['currency']);
    $taxCategory = $cac('TaxCategory');
    $taxCategory->appendChild($cbc('ID', 'S'));
    $taxCategory->appendChild($cbc('Percent', $decimal($data['lines'][0]['tax']['percent'] ?? 21)));
    $taxScheme = $cac('TaxScheme');
    $taxScheme->appendChild($cbc('ID', 'VAT'));
    $taxCategory->appendChild($taxScheme);
    $taxSubtotal->appendChild($taxCategory);
    $taxTotal->appendChild($taxSubtotal);
    $invoice->appendChild($taxTotal);

    // LegalMonetaryTotal (amounts summary)
    $total = $cac('LegalMonetaryTotal');
    $total->appendChild($cbc('LineExtensionAmount', $decimal($data['totals']['total_excl_tax'])))->setAttribute('currencyID', $data['currency']);
    $total->appendChild($cbc('TaxExclusiveAmount', $decimal($data['totals']['total_excl_tax'])))->setAttribute('currencyID', $data['currency']);
    $total->appendChild($cbc('TaxInclusiveAmount', $decimal($data['totals']['total_incl_tax'])))->setAttribute('currencyID', $data['currency']);
    $total->appendChild($cbc('PayableAmount', $decimal($data['totals']['payable_amount'])))->setAttribute('currencyID', $data['currency']);
    $invoice->appendChild($total);

    // Invoice Line(s)
    $lineId = 1;
    foreach ($data['lines'] as $l) {
        $line = $cac('InvoiceLine');
        $line->appendChild($cbc('ID', (string) $lineId++));
        $qty = $cbc('InvoicedQuantity', $decimal($l['quantity']));
        $qty->setAttribute('unitCode', $l['unit_code']);
        $line->appendChild($qty);
        $line->appendChild($cbc('LineExtensionAmount', $decimal($l['amount'])))->setAttribute('currencyID', $data['currency']);
        $item = $cac('Item');
        $item->appendChild($cbc('Name', $l['description']));
        $classifiedTaxCategory = $cac('ClassifiedTaxCategory');
        $classifiedTaxCategory->appendChild($cbc('ID', $l['tax']['category_id']));
        $classifiedTaxCategory->appendChild($cbc('Percent', $decimal($l['tax']['percent'])));
        $taxScheme = $cac('TaxScheme');
        $taxScheme->appendChild($cbc('ID', $l['tax']['scheme_id']));
        $classifiedTaxCategory->appendChild($taxScheme);
        $item->appendChild($classifiedTaxCategory);
        $line->appendChild($item);
        $price = $cac('Price');
        $price->appendChild($cbc('PriceAmount', $decimal($l['unit_price'])))->setAttribute('currencyID', $data['currency']);
        $line->appendChild($price);
        $invoice->appendChild($line);
    }

    $doc->appendChild($invoice);

    return $doc->saveXML();
}


/*
    Convert XML UBL BIS3.0 to a JSON structure following  the 'purchase-invoice' schema.
*/
function convertUblToJson(string $xml): string {
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);

    if(!$doc->loadXML($xml)) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        throw new Exception("Erreur lors du chargement du XML : " . $errors[0]->message, EQ_ERROR_UNKNOWN);
    }

    $xpath = new DOMXPath($doc);

    // Enregistrement explicite des namespaces
    $xpath->registerNamespace('inv', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

    $getValue = fn($query) => trim($xpath->evaluate("string($query)"));

    $supplier = [
        'name' => $getValue('//cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name'),
        'vat_id' => $getValue('//cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID'),
        'address' => [
            'street' => $getValue('//cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:StreetName'),
            'city' => $getValue('//cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:CityName'),
            'postal_code' => $getValue('//cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:PostalZone'),
            'country' => $getValue('//cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode'),
        ]
    ];

    $customer = [
        'name'      => $getValue('//cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name'),
        'vat_id'    => $getValue('//cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID'),
        'address'   => [
            'street'        => $getValue('//cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cbc:StreetName'),
            'city'          => $getValue('//cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cbc:CityName'),
            'postal_code'   => $getValue('//cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cbc:PostalZone'),
            'country'       => $getValue('//cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode'),
        ]
    ];

    $lines = [];
    /** @var DOMElement $line */
    foreach ($xpath->query('//cac:InvoiceLine') as $line) {
        $unitCode = '';
        $quantityNode = $line->getElementsByTagNameNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'InvoicedQuantity')->item(0);
        if ($quantityNode) {
            $unitCode = $quantityNode->getAttribute('unitCode');
        }

        $lines[] = [
            'id'            => $xpath->evaluate('string(cbc:ID)', $line),
            'description'   => $xpath->evaluate('string(cac:Item/cbc:Name)', $line),
            'quantity'      => (float)$xpath->evaluate('string(cbc:InvoicedQuantity)', $line),
            'unit_code'     => $unitCode,
            'unit_price'    => (float)$xpath->evaluate('string(cac:Price/cbc:PriceAmount)', $line),
            'amount'        => (float)$xpath->evaluate('string(cbc:LineExtensionAmount)', $line),
            'tax'           => [
                'category_id'   => $xpath->evaluate('string(cac:Item/cac:ClassifiedTaxCategory/cbc:ID)', $line),
                'percent'       => (float)$xpath->evaluate('string(cac:Item/cac:ClassifiedTaxCategory/cbc:Percent)', $line),
                'scheme_id'     => $xpath->evaluate('string(cac:Item/cac:ClassifiedTaxCategory/cac:TaxScheme/cbc:ID)', $line),
            ]
        ];
    }

    $invoicePeriodStart = $getValue('//cac:InvoicePeriod/cbc:StartDate');
    $invoicePeriodEnd = $getValue('//cac:InvoicePeriod/cbc:EndDate');

    $data = [
        'invoice_number'    => $getValue('//cbc:ID'),
        'invoice_type'      => $getValue('//cbc:InvoiceTypeCode') === '381' ? 'credit_note' : 'invoice',
        'issue_date'        => $getValue('//cbc:IssueDate') . 'T00:00:00Z',
        'due_date'          => $getValue('//cbc:DueDate') . 'T00:00:00Z',
        'currency'          => $getValue('//cbc:DocumentCurrencyCode'),
        'buyer_reference'   => $getValue('//cbc:BuyerReference'),
        'supplier'          => $supplier,
        'customer'          => $customer,
        'lines'             => $lines,
        'totals'            => [
            'total_excl_tax' => (float) $getValue('//cac:TaxSubtotal/cbc:TaxableAmount'),
            'total_tax'      => (float) $getValue('//cac:TaxSubtotal/cbc:TaxAmount'),
            'total_incl_tax' => (float) $getValue('//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount'),
            'payable_amount' => (float) $getValue('//cac:LegalMonetaryTotal/cbc:PayableAmount'),
        ],
        'payment'           => [
            'iban'               => $getValue('//cac:PayeeFinancialAccount/cbc:ID'),
            'bic'                => $getValue('//cac:PayeeFinancialAccount/cac:FinancialInstitutionBranch/cbc:ID'),
            'payment_means_code' => $getValue('//cac:PaymentMeans/cbc:PaymentMeansCode'),
        ]
    ];

    if($invoicePeriodStart !== '' && $invoicePeriodEnd !== '') {
        $data['invoice_period'] = [
            'start_date' => $invoicePeriodStart . 'T00:00:00Z',
            'end_date'   => $invoicePeriodEnd . 'T00:00:00Z'
        ];
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
