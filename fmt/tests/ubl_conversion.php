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