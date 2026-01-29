<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => 'Request a document analysis using Mindee.com service, and return the result as a JSON descriptor.',
    'params'        => [
        'document_id' =>  [
            'description'   => 'Identifier of the document to parse.',
            'type'          => 'string',
            'required'      => true
        ]
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


$computeBicFromIban = function($iban) {
    static $map_bic;
    $result = null;

    if(!$iban) {
        return null;
    }

    $country = substr($iban, 0, 2);
    $bank_code = substr($iban, 4, 3);

    if(!$map_bic) {
        $file = EQ_BASEDIR . "/packages/identity/i18n/en/bic/{$country}.json";
        if(file_exists($file)) {
            $data = file_get_contents($file);
            $map_bic = json_decode($data, true);
        }
    }

    $result = $map_bic[$bank_code]['bic'] ?? null;

    return $result;
};

$document = Document::id($params['document_id'])
    ->read(['has_analysis_json', 'analysis_json'])
    ->first();

if(!$document) {
    throw new Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
}

// 1) si le document n'a pas encore été analysé, lancer le controller dédié
if(!$document['has_analysis_json']) {
    $data = eQual::run('do', 'documents_processing_PurchaseInvoice_analyze-google', ['id' => $document['id']]);

    $document = Document::id($params['document_id'])
        ->read(['has_analysis_json', 'analysis_json'])
        ->first();

    if(!$document['has_analysis_json']) {
        throw new Exception('error_retrieving_analysis', EQ_ERROR_UNKNOWN);
    }
}


// retrieve the corresponding JSON matching schema $id purchase-invoice
$data = eQual::run('get', 'documents_processing_PurchaseInvoice_parse-google', ['json' => $document['analysis_json']]);

// essayer de récupérer le vat_seller dans les lignes
// $data[lines_ids]
// => ['supplier']['vat_id']

// attempt to enrich with additional data
$text = eQual::run('get', 'documents_processing_dump-text', ['id' =>  $document['id']]);
$info = eQual::run('get', 'documents_processing_parse-text', ['text' => $text]);


// #todo : conserver des données identifiées sur base du format (IBAN, EAN, REGISTRY_NUMBER), mais non rattachées à un champ précis

if(!isset($data['supplier']['vat_id']) && isset($info['seller_vat'])) {
    $data['supplier']['vat_id'] = $info['seller_vat'];
}

if(!isset($data['customer']['vat_id']) && isset($info['buyer_vat'])) {
    $data['customer']['vat_id'] = $info['buyer_vat'];
}

if(!isset($data['customer']['customer_number']) && isset($info['customer_number'])) {
    $data['customer']['customer_number'] = $info['customer_number'];
}

// customer_reference

if(!isset($data['customer']['customer_id']) && isset($info['customer_registration_number'])) {
    $data['customer']['customer_id'] = $info['customer_registration_number'];
}

if(!isset($data['customer']['contract_number']) && isset($info['contract_number'])) {
    $data['customer']['contract_number'] = $info['contract_number'];
}

if(!isset($data['customer']['installation_number']) && isset($info['installation_number'])) {
    $data['customer']['installation_number'] = $info['installation_number'];
}

if(!isset($data['payment']['payment_id']) && isset($info['payment_id'])) {
    $data['payment']['payment_id'] = $info['payment_id'];
}

if(!isset($data['payment']['iban']) && isset($info['iban'])) {
    $data['payment']['iban'] = $info['iban'];
}

if(!isset($data['invoice_period']) && isset($info['period_start'], $info['period_end'])) {
    $data['invoice_period'] = [
        'start_date' => $info['period_start'],
        'end_date'   => $info['period_end']
    ];
}


// #memo - EAN 5414 (=BE) + 2 last digits as control (%97)
if(!isset($data['buyer_reference']) && isset($info['ean_code'])) {
    $data['buyer_reference'] = $info['ean_code'];
}

$data['payment']['bic'] = $computeBicFromIban($data['payment']['iban'] ?? '');


$context->httpResponse()
        ->body($data)
        ->send();
