<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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


$output = [
    'document_type'     => 'invoice',
    'invoice_number'    => '',
    'invoice_type'      => 'INVOICE',
    'issue_date'        => gmdate("Y-m-d\TH:i:s\Z"),
    'due_date'          => gmdate("Y-m-d\TH:i:s\Z"),
    'currency'          => $localeCurrency,
    'buyer_reference'   => '',
    'supplier' => [
        'name'               => '',
        'vat_id'             => '',
        'company_id'         => '',
        'address'            => '',
    ],
    'customer' => [
        'name'               => '',
        'customer_number'    => '',
        'vat_id'             => '',
        'company_id'         => '',
        'address' => [
            'street'         => '',
            'city'           => '',
            'postal_code'    => '',
            'country'        => '',
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
        'iban'               => '',
        'bic'                => '',
        'payment_id'         => null,
        'payment_means_code' => '30'
    ]
];

$context->httpResponse()
        ->body($output)
        ->send();


// Full PEPPOL invoice sample:
/*
<sh:StandardBusinessDocument xmlns:sh="http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader">
  <sh:StandardBusinessDocumentHeader>
    <sh:HeaderVersion>1.0</sh:HeaderVersion>
    <sh:Sender>
      <sh:Identifier Authority="iso6523-actorid-upis">0208:0409536968</sh:Identifier>
    </sh:Sender>
    <sh:Receiver>
      <sh:Identifier Authority="iso6523-actorid-upis">0208:0755885564</sh:Identifier>
    </sh:Receiver>
    <sh:DocumentIdentification>
      <sh:Standard>urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2</sh:Standard>
      <sh:TypeVersion>2.1</sh:TypeVersion>
      <sh:InstanceIdentifier>bbbbc03f-d033-41bc-b519-704fa7bb6ceb</sh:InstanceIdentifier>
      <sh:Type>CreditNote</sh:Type>
      <sh:CreationDateAndTime>2025-12-06T08:49:40.905Z</sh:CreationDateAndTime>
    </sh:DocumentIdentification>
    <sh:BusinessScope>
      <sh:Scope>
        <sh:Type>DOCUMENTID</sh:Type>
        <sh:InstanceIdentifier>urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1</sh:InstanceIdentifier>
        <sh:Identifier>busdox-docid-qns</sh:Identifier>
      </sh:Scope>
      <sh:Scope>
        <sh:Type>PROCESSID</sh:Type>
        <sh:InstanceIdentifier>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</sh:InstanceIdentifier>
        <sh:Identifier>cenbii-procid-ubl</sh:Identifier>
      </sh:Scope>
      <sh:Scope>
        <sh:Type>COUNTRY_C1</sh:Type>
        <sh:InstanceIdentifier>BE</sh:InstanceIdentifier>
      </sh:Scope>
    </sh:BusinessScope>
  </sh:StandardBusinessDocumentHeader>
  <CreditNote xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
    <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
    <cbc:ID>PN202500218611</cbc:ID>
    <cbc:IssueDate>2025-12-05</cbc:IssueDate>
    <cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>
    <cbc:Note>En 2026 la facture électronique structurée sera obligatoire. Partena est prêt. N'attendez plus. Contactez votre Expert Compta pour plus d'infos.
Vanaf 2026 is de gestruct. elektronische factuur verplicht. Partena is klaar. Wacht niet langer. Neem con...</cbc:Note>
    <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
    <cbc:BuyerReference>396301</cbc:BuyerReference>
    <cac:InvoicePeriod>
      <cbc:StartDate>2025-12-01</cbc:StartDate>
      <cbc:EndDate>2025-12-31</cbc:EndDate>
    </cac:InvoicePeriod>
    <cac:AdditionalDocumentReference>
      <cbc:ID>2025-16435040</cbc:ID>
      <cac:Attachment>
        <cbc:EmbeddedDocumentBinaryObject filename="20251205191919FDAA396301004669.pdf" mimeCode="application/pdf">JVBERi0xLjcKJeLjz9MKMyAwIG9iago8PC9GVC9TaWcvVChTaWduYXR1cmUxKS9WIDEgMCBSL0YgMTMyL1R5cGUvQW5ub3QvU3VidHlwZS9XaWRnZXQvUmVjdFswIDAgMCAwXS9BUDw8L04gMiAwIFI+Pi9QIDQgMCBSL0RSPDw+Pj4+CmVuZG9iagoxIDAgb2JqCjw8L1R5cGUvU2lnL0ZpbHRlci9BZG9iZS5QUEtMaXRlL1N1YkZpbH...</cbc:EmbeddedDocumentBinaryObject>
      </cac:Attachment>
    </cac:AdditionalDocumentReference>
    <cac:AccountingSupplierParty>
      <cac:Party>
        <cbc:EndpointID schemeID="0208">0409536968</cbc:EndpointID>
        <cac:PartyName>
          <cbc:Name>Partena Professional</cbc:Name>
        </cac:PartyName>
        <cac:PostalAddress>
          <cbc:StreetName>BP 20023</cbc:StreetName>
          <cbc:CityName>BRUXELLES</cbc:CityName>
          <cbc:PostalZone>1000</cbc:PostalZone>
          <cac:Country>
            <cbc:IdentificationCode>BE</cbc:IdentificationCode>
          </cac:Country>
        </cac:PostalAddress>
        <cac:PartyTaxScheme>
          <cbc:CompanyID>BE0409536968</cbc:CompanyID>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:PartyTaxScheme>
        <cac:PartyLegalEntity>
          <cbc:RegistrationName>Partena Professional</cbc:RegistrationName>
          <cbc:CompanyID schemeID="0208">0409536968</cbc:CompanyID>
        </cac:PartyLegalEntity>
        <cac:Contact>
          <cbc:Name>Al Bouazzati                S</cbc:Name>
          <cbc:Telephone>02/549.30.17</cbc:Telephone>
          <cbc:ElectronicMail>Accountteam2@partena.be</cbc:ElectronicMail>
        </cac:Contact>
      </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
      <cac:Party>
        <cbc:EndpointID schemeID="0208">0755885564</cbc:EndpointID>
        <cac:PartyName>
          <cbc:Name>YESBABYLON</cbc:Name>
        </cac:PartyName>
        <cac:PostalAddress>
          <cbc:StreetName>Boulevard du Souverain 24</cbc:StreetName>
          <cbc:CityName>Watermael-Boitsfort</cbc:CityName>
          <cbc:PostalZone>1170</cbc:PostalZone>
          <cac:Country>
            <cbc:IdentificationCode>BE</cbc:IdentificationCode>
          </cac:Country>
        </cac:PostalAddress>
        <cac:PartyTaxScheme>
          <cbc:CompanyID>BE0755885564</cbc:CompanyID>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:PartyTaxScheme>
        <cac:PartyLegalEntity>
          <cbc:RegistrationName>YESBABYLON</cbc:RegistrationName>
          <cbc:CompanyID schemeID="0208">0755885564</cbc:CompanyID>
        </cac:PartyLegalEntity>
        <cac:Contact>
          <cbc:Name>M. Cédric Françoys</cbc:Name>
        </cac:Contact>
      </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:PaymentMeans>
      <cbc:PaymentMeansCode name="Clearing between partners">97</cbc:PaymentMeansCode>
    </cac:PaymentMeans>
    <cac:TaxTotal>
      <cbc:TaxAmount currencyID="EUR">0.00</cbc:TaxAmount>
      <cac:TaxSubtotal>
        <cbc:TaxableAmount currencyID="EUR">677.07</cbc:TaxableAmount>
        <cbc:TaxAmount currencyID="EUR">0.00</cbc:TaxAmount>
        <cac:TaxCategory>
          <cbc:ID>E</cbc:ID>
          <cbc:Percent>0.00</cbc:Percent>
          <cbc:TaxExemptionReason>Exempt from tax</cbc:TaxExemptionReason>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:TaxCategory>
      </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
      <cbc:LineExtensionAmount currencyID="EUR">677.07</cbc:LineExtensionAmount>
      <cbc:TaxExclusiveAmount currencyID="EUR">677.07</cbc:TaxExclusiveAmount>
      <cbc:TaxInclusiveAmount currencyID="EUR">677.07</cbc:TaxInclusiveAmount>
      <cbc:PayableAmount currencyID="EUR">677.07</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:CreditNoteLine>
      <cbc:ID>1</cbc:ID>
      <cbc:CreditedQuantity unitCode="ZZ">1.00</cbc:CreditedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">677.07</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Name>Provision supplémentaire ONSS</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>E</cbc:ID>
          <cbc:Percent>0.00</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">677.07</cbc:PriceAmount>
      </cac:Price>
    </cac:CreditNoteLine>
  </CreditNote>
</sh:StandardBusinessDocument>
*/