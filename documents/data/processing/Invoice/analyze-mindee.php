<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use documents\Document;
use equal\http\HttpRequest;
use equal\http\HttpResponse;

[$params, $providers] = eQual::announce([
    'description'   => 'Request a document analysis using Mindee.com service, and return the a result as a JSON descriptor.',
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the document to parse.',
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'constants'     => ['MINDEE_API_KEY'],
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


$document = Document::id($params['id'])->read(['name', 'content_type'])->first();

if(!$document) {
    throw new Exception('unknown_document', EQ_ERROR_UNKNOWN_OBJECT);
}

// #todo #test - comment for PROD
if($document['name'] === 'FACTURES ACP RESIDENCE THEO 4.pdf') {
    $test = true;
    $json = <<<'EOT'
    {"api_request":{"error":[],"resources":["document"],"status":"success","status_code":201,"url":"https://api.mindee.net/v1/products/mindee/invoices/v4/predict"},"document":{"id":"29e61251-d0a0-43c5-9ca3-f99967d51f06","inference":{"extras":[],"finished_at":"2025-05-06T10:10:34.265672","is_rotation_applied":true,"pages":[{"extras":[],"id":0,"orientation":{"value":0},"prediction":{"billing_address":{"address_complement":null,"city":null,"confidence":1,"country":null,"po_box":null,"polygon":[[0.069,0.394],[0.274,0.394],[0.274,0.404],[0.069,0.404]],"postal_code":"1210","state":null,"street_name":null,"street_number":null,"value":"CHEE DE LOUVAIN 261, 1210"},"category":{"confidence":0.85,"value":"miscellaneous"},"customer_address":{"address_complement":"BTE 12","city":"SCHAERBEEK","confidence":1,"country":null,"po_box":null,"polygon":[[0.562,0.234],[0.777,0.234],[0.777,0.256],[0.562,0.256]],"postal_code":"1030","state":null,"street_name":"CHEE DE LOUVAIN","street_number":"267","value":"CHEE DE LOUVAIN 267 BTE 12 1030 SCHAERBEEK"},"customer_company_registrations":[],"customer_id":{"confidence":1,"polygon":[[0.068,0.283],[0.158,0.283],[0.158,0.292],[0.068,0.292]],"value":"1000328782"},"customer_name":{"confidence":0.99,"polygon":[[0.561,0.205],[0.723,0.205],[0.723,0.227],[0.561,0.227]],"raw_value":"ACP RESIDENCE THEO c/o YVAN FLION","value":"ACP RESIDENCE THEO C/O YVAN FLION"},"date":{"confidence":0.99,"polygon":[[0.7,0.353],[0.848,0.353],[0.848,0.373],[0.7,0.373]],"value":"2024-12-15"},"document_type":{"value":"INVOICE"},"document_type_extended":{"confidence":0.99,"value":"INVOICE"},"due_date":{"confidence":0.99,"is_computed":false,"polygon":[[0.75,0.553],[0.87,0.553],[0.87,0.569],[0.75,0.569]],"value":"2025-01-14"},"invoice_number":{"confidence":1,"polygon":[[0.46,0.355],[0.65,0.355],[0.65,0.369],[0.46,0.369]],"value":"744000399977"},"line_items":[{"confidence":1,"description":"Total de la facture (HTVA) TVA 6%","polygon":[[0.066,0.471],[0.523,0.471],[0.523,0.515],[0.066,0.515]],"product_code":null,"quantity":null,"tax_amount":null,"tax_rate":null,"total_amount":1051.89,"unit_measure":null,"unit_price":null}],"locale":{"confidence":0.93,"country":"BE","currency":"EUR","language":"fr","value":"fr-BE"},"orientation":{"confidence":0.99,"degrees":0},"payment_date":{"confidence":0.99,"polygon":[[0.75,0.553],[0.87,0.553],[0.87,0.569],[0.75,0.569]],"value":"2025-01-14"},"po_number":{"confidence":0.08,"polygon":[[0.757,0.395],[0.84,0.395],[0.84,0.402],[0.757,0.402]],"value":"4000232058"},"reference_numbers":[],"shipping_address":{"address_complement":null,"city":null,"confidence":0,"country":null,"po_box":null,"polygon":[],"postal_code":null,"state":null,"street_name":null,"street_number":null,"value":null},"subcategory":{"confidence":0.85,"value":null},"supplier_address":{"address_complement":null,"city":"Bruxelles","confidence":1,"country":null,"po_box":null,"polygon":[[0.389,0.951],[0.667,0.951],[0.667,0.958],[0.389,0.958]],"postal_code":"1000","state":null,"street_name":"boulevard de l'Impératrice","street_number":"17-19","value":"17-19 boulevard de l'Impératrice 1000 Bruxelles -"},"supplier_company_registrations":[{"confidence":1,"polygon":[[0.096,0.965],[0.189,0.965],[0.189,0.972],[0.096,0.972]],"type":"VAT NUMBER","value":"BE0202962701"}],"supplier_email":{"confidence":0,"polygon":[],"value":null},"supplier_name":{"confidence":1,"polygon":[[0.066,0.215],[0.19,0.215],[0.19,0.221],[0.066,0.221]],"raw_value":"vivaqua","value":"VIVAQUA"},"supplier_payment_details":[{"account_number":null,"confidence":1,"iban":"BE52096011784309","polygon":[[0.75,0.591],[0.924,0.591],[0.924,0.6],[0.75,0.6]],"routing_number":null,"swift":null}],"supplier_phone_number":{"confidence":1,"polygon":[[0.104,0.228],[0.2,0.228],[0.2,0.237],[0.104,0.237]],"value":"025188810"},"supplier_website":{"confidence":1,"polygon":[[0.06,0.207],[0.195,0.207],[0.195,0.226],[0.06,0.226]],"value":"www.vivaqua.be"},"taxes":[{"base":1051.89,"confidence":1,"polygon":[[0.1,0.507],[0.1,0.515],[0.52,0.515],[0.52,0.507]],"rate":6,"value":63.11}],"total_amount":{"confidence":1,"polygon":[[0.461,0.542],[0.522,0.542],[0.522,0.55],[0.461,0.55]],"value":1115},"total_net":{"confidence":1,"polygon":[[0.465,0.472],[0.523,0.472],[0.523,0.48],[0.465,0.48]],"value":1051.89},"total_tax":{"confidence":1,"polygon":[[0.486,0.507],[0.52,0.507],[0.52,0.515],[0.486,0.515]],"value":63.11}}},{"extras":[],"id":1,"orientation":{"value":0},"prediction":{"billing_address":{"address_complement":null,"city":null,"confidence":0,"country":null,"po_box":null,"polygon":[],"postal_code":null,"state":null,"street_name":null,"street_number":null,"value":null},"category":{"confidence":0.92,"value":"software"},"customer_address":{"address_complement":null,"city":null,"confidence":0,"country":null,"po_box":null,"polygon":[],"postal_code":null,"state":null,"street_name":null,"street_number":null,"value":null},"customer_company_registrations":[],"customer_id":{"confidence":0,"polygon":[],"value":null},"customer_name":{"confidence":0,"polygon":[],"raw_value":null,"value":null},"date":{"confidence":0,"polygon":[],"value":null},"document_type":{"value":"INVOICE"},"document_type_extended":{"confidence":0.78,"value":"OTHER"},"due_date":{"confidence":0,"is_computed":false,"polygon":[],"value":null},"invoice_number":{"confidence":0,"polygon":[],"value":null},"line_items":[],"locale":{"confidence":0.93,"country":"BE","currency":"EUR","language":"fr","value":"fr-BE"},"orientation":{"confidence":0.99,"degrees":0},"payment_date":{"confidence":0,"polygon":[],"value":null},"po_number":{"confidence":0,"polygon":[],"value":null},"reference_numbers":[],"shipping_address":{"address_complement":null,"city":null,"confidence":0,"country":null,"po_box":null,"polygon":[],"postal_code":null,"state":null,"street_name":null,"street_number":null,"value":null},"subcategory":{"confidence":0.92,"value":null},"supplier_address":{"address_complement":null,"city":null,"confidence":0,"country":null,"po_box":null,"polygon":[],"postal_code":null,"state":null,"street_name":null,"street_number":null,"value":null},"supplier_company_registrations":[],"supplier_email":{"confidence":0,"polygon":[],"value":null},"supplier_name":{"confidence":0,"polygon":[],"raw_value":null,"value":null},"supplier_payment_details":[],"supplier_phone_number":{"confidence":1,"polygon":[[0.456,0.173],[0.549,0.173],[0.549,0.181],[0.456,0.181]],"value":"025188810"},"supplier_website":{"confidence":1,"polygon":[[0.077,0.166],[0.315,0.166],[0.315,0.189],[0.077,0.189]],"value":"www.vivaqua.be"},"taxes":[],"total_amount":{"confidence":0,"polygon":[],"value":null},"total_net":{"confidence":0,"polygon":[],"value":null},"total_tax":{"confidence":0,"polygon":[],"value":null}}}],"prediction":{"billing_address":{"address_complement":null,"city":null,"confidence":1,"country":null,"page_id":0,"po_box":null,"polygon":[[0.069,0.394],[0.274,0.394],[0.274,0.404],[0.069,0.404]],"postal_code":"1210","state":null,"street_name":null,"street_number":null,"value":"CHEE DE LOUVAIN 261, 1210"},"category":{"confidence":0.85,"value":"miscellaneous"},"customer_address":{"address_complement":"BTE 12","city":"SCHAERBEEK","confidence":1,"country":null,"page_id":0,"po_box":null,"polygon":[[0.562,0.234],[0.777,0.234],[0.777,0.256],[0.562,0.256]],"postal_code":"1030","state":null,"street_name":"CHEE DE LOUVAIN","street_number":"267","value":"CHEE DE LOUVAIN 267 BTE 12 1030 SCHAERBEEK"},"customer_company_registrations":[],"customer_id":{"confidence":1,"page_id":0,"polygon":[[0.068,0.283],[0.158,0.283],[0.158,0.292],[0.068,0.292]],"value":"1000328782"},"customer_name":{"confidence":0.99,"page_id":0,"polygon":[[0.561,0.205],[0.723,0.205],[0.723,0.227],[0.561,0.227]],"raw_value":"ACP RESIDENCE THEO c/o YVAN FLION","value":"ACP RESIDENCE THEO C/O YVAN FLION"},"date":{"confidence":0.99,"page_id":0,"polygon":[[0.7,0.353],[0.848,0.353],[0.848,0.373],[0.7,0.373]],"value":"2024-12-15"},"document_type":{"value":"INVOICE"},"document_type_extended":{"confidence":0.99,"value":"INVOICE"},"due_date":{"confidence":0.99,"is_computed":false,"page_id":0,"polygon":[[0.75,0.553],[0.87,0.553],[0.87,0.569],[0.75,0.569]],"value":"2025-01-14"},"invoice_number":{"confidence":1,"page_id":0,"polygon":[[0.46,0.355],[0.65,0.355],[0.65,0.369],[0.46,0.369]],"value":"744000399977"},"line_items":[{"confidence":1,"description":"Total de la facture (HTVA) TVA 6%","page_id":0,"polygon":[[0.066,0.471],[0.523,0.471],[0.523,0.515],[0.066,0.515]],"product_code":null,"quantity":null,"tax_amount":null,"tax_rate":null,"total_amount":1051.89,"unit_measure":null,"unit_price":null}],"locale":{"confidence":0.93,"country":"BE","currency":"EUR","language":"fr","value":"fr-BE"},"payment_date":{"confidence":0.99,"page_id":0,"polygon":[[0.75,0.553],[0.87,0.553],[0.87,0.569],[0.75,0.569]],"value":"2025-01-14"},"po_number":{"confidence":0.08,"page_id":0,"polygon":[[0.757,0.395],[0.84,0.395],[0.84,0.402],[0.757,0.402]],"value":"4000232058"},"reference_numbers":[],"shipping_address":{"address_complement":null,"city":null,"confidence":0,"country":null,"page_id":null,"po_box":null,"polygon":[],"postal_code":null,"state":null,"street_name":null,"street_number":null,"value":null},"subcategory":{"confidence":0.85,"value":null},"supplier_address":{"address_complement":null,"city":"Bruxelles","confidence":1,"country":null,"page_id":0,"po_box":null,"polygon":[[0.389,0.951],[0.667,0.951],[0.667,0.958],[0.389,0.958]],"postal_code":"1000","state":null,"street_name":"boulevard de l'Impératrice","street_number":"17-19","value":"17-19 boulevard de l'Impératrice 1000 Bruxelles -"},"supplier_company_registrations":[{"confidence":1,"page_id":0,"polygon":[[0.096,0.965],[0.189,0.965],[0.189,0.972],[0.096,0.972]],"type":"VAT NUMBER","value":"BE0202962701"}],"supplier_email":{"confidence":0,"page_id":null,"polygon":[],"value":null},"supplier_name":{"confidence":1,"page_id":0,"polygon":[[0.066,0.215],[0.19,0.215],[0.19,0.221],[0.066,0.221]],"raw_value":"vivaqua","value":"VIVAQUA"},"supplier_payment_details":[{"account_number":null,"confidence":1,"iban":"BE52096011784309","page_id":0,"polygon":[[0.75,0.591],[0.924,0.591],[0.924,0.6],[0.75,0.6]],"routing_number":null,"swift":null}],"supplier_phone_number":{"confidence":1,"page_id":0,"polygon":[[0.104,0.228],[0.2,0.228],[0.2,0.237],[0.104,0.237]],"value":"025188810"},"supplier_website":{"confidence":1,"page_id":0,"polygon":[[0.06,0.207],[0.195,0.207],[0.195,0.226],[0.06,0.226]],"value":"www.vivaqua.be"},"taxes":[{"base":1051.89,"confidence":1,"page_id":0,"polygon":[[0.1,0.507],[0.1,0.515],[0.52,0.515],[0.52,0.507]],"rate":6,"value":63.11}],"total_amount":{"confidence":1,"page_id":0,"polygon":[[0.461,0.542],[0.522,0.542],[0.522,0.55],[0.461,0.55]],"value":1115},"total_net":{"confidence":1,"page_id":0,"polygon":[[0.465,0.472],[0.523,0.472],[0.523,0.48],[0.465,0.48]],"value":1051.89},"total_tax":{"confidence":1,"page_id":0,"polygon":[[0.486,0.507],[0.52,0.507],[0.52,0.515],[0.486,0.515]],"value":63.11}},"processing_time":1.383,"product":{"features":["locale","invoice_number","po_number","reference_numbers","date","due_date","payment_date","total_net","total_amount","total_tax","taxes","supplier_payment_details","supplier_name","supplier_company_registrations","supplier_address","supplier_phone_number","supplier_website","supplier_email","customer_name","customer_company_registrations","customer_address","customer_id","shipping_address","billing_address","document_type","document_type_extended","subcategory","category","orientation","line_items"],"name":"mindee/invoices","type":"standard","version":"4.11"},"started_at":"2025-05-06T10:10:32.882737"},"n_pages":2,"name":"document.pdf"}}
    EOT;
}


if(!isset($test) || !$test) {

    // check that content_type is supported by the API
    $supported_content_types = [
            'application/pdf',
            'image/webp',
            'image/png',
            'image/jpg',
            'image/jpeg',
            'image/heic',
            'image/tiff',
            'image/tif'
        ];

    if(!in_array($document['content_type'], $supported_content_types)) {
        throw new Exception('unsupported_document_type', EQ_ERROR_INVALID_PARAM);
    }

    $document_data = eQual::run('get', 'documents_document', ['id' => $params['id']]);

    $request = new HttpRequest("POST https://api.mindee.net/v1/products/mindee/invoices/v4/predict");

    $api_key = constant('MINDEE_API_KEY');

    $boundary = uniqid('boundary_');

    // build multipart body
    $body = "--$boundary\r\n";
    $body .= 'Content-Disposition: form-data; name="document"; filename="document.pdf"' . "\r\n";
    $body .= 'Content-Type: ' . $document['content_type'] . "\r\n\r\n";
    $body .= $document_data . "\r\n";
    $body .= "--$boundary--\r\n";


    /** @var HttpResponse */
    $request
        ->header('Authorization', "Token {$api_key}")
        ->header('Content-Type', "multipart/form-data; boundary=$boundary");

    $request->body($body, true);

    /** @var HttpResponse */
    $response = $request->send();

    // check response status
    $status = $response->getStatusCode();

    if($status < 200 || $status >= 300) {
        // upon request rejection, we stop the whole job
        ob_start();
        var_dump($response->body());
        $info = ob_get_clean();
        throw new Exception("request to API rejected with code $status: " . $info, EQ_ERROR_INVALID_PARAM);
    }

    // we should have received an application/json response, if so HttpMessage::body() contains a decoded version of the JSON data
    $data = $response->body();

    if(!is_array($data)) {
        throw new Exception('invalid_mindee_response', EQ_ERROR_UNKNOWN);
    }

    $json = $response->getBody(true);

}

// format result
$data = json_decode($json, true);
$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// store JSON response in the Document
Document::id($document['id'])
    ->update([
        'has_analysis_json' => true,
        'analysis_json'     => $json,
        'analysis_version'  => 'mindee_v4'
    ]);


$context->httpResponse()
        ->body($json)
        ->send();


// #todo - move this somewhere else

/*
    Convert a JSON structure matching the 'purchase-invoice' schema to a valid UBL BIS3.0 XML (EN16931)
    Full specs here : https://docs.peppol.eu/poacc/billing/3.0/bis/
    Nodes details here: https://docs.peppol.eu/poacc/billing/3.0/syntax/ubl-invoice/tree/
*/
$convertJsonToUbl = function(string $json): string {
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
};


/*
    Convert XML UBL BIS3.0 to a JSON structure following  the 'purchase-invoice' schema.
*/
$convertUblToJson = function(string $xml): string {
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
};

