<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => 'Request a document analysis using Mindee.com service, and return the a result as a JSON descriptor.',
    'params'        => [
        'document_id' =>  [
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

$document = Document::id($params['document_id'])->read(['content_type'])->first();

if(!$document) {
    throw new Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
}

$supported_content_types = [
        'application/pdf',
        'image/webp',
        'image/png',
        'image/jpg',
        'image/jpeg'
    ];

if(!in_array($document['content_type'], $supported_content_types)) {
    throw new Exception('non_supported_document_type', EQ_ERROR_INVALID_PARAM);
}

$data = eQual::run('get', 'documents_processing_Invoice_analyze-mindee', ['id' => $document['id']]);

if(!isset($data['document']['inference']['prediction'])) {
    // invalid Mindee response
    throw new Exception('invalid_api_response', EQ_REPORT_WARNING);
}

$prediction = $data['document']['inference']['prediction'];

// retrieve the corresponding JSON matching schema $id purchase-invoice
$data = eQual::run('get', 'documents_processing_Invoice_parse-mindee', ['json' => json_encode($data['document']['inference']['prediction'])]);

// attempt to enrich with additional data
$text = eQual::run('get', 'documents_processing_dump-text', ['id' =>  $document['id']]);
$info = eQual::run('get', 'documents_processing_Invoice_parse-text', ['text' => $text]);

if(!isset($data['customer']['customer_number']) && isset($info['customer_number'])) {
    $data['customer']['customer_number'] = $info['customer_number'];
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

$data['payment']['bic'] = $computeBicFromIban($data['payment']['iban']);


$context->httpResponse()
        ->body($data)
        ->send();
