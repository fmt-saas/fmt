<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => 'Analyse and parse the given document to return the result its data as a JSON descriptor.',
    'params'        => [
        'document_id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'description'       => 'Identifier of the document to parse.',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

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
    ->read(['content_type'])
    ->first();

if(!$document) {
    throw new Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
}

if($document['content_type'] === 'application/pdf') {
    $data = \eQual::run('get', 'documents_processing_PurchaseInvoice_extract-pdf', [
        'document_id' => $document['id']
    ]);
}
elseif(in_array($document['content_type'], ['application/xml', 'text/xml'])) {
    $data = \eQual::run('get', 'documents_processing_PurchaseInvoice_extract-xml', [
        'document_id'               => $document['id'],
        'dissociate_attachments'    => true
    ]);
}
else {
    throw new Exception('invalid_content_type', EQ_ERROR_INVALID_PARAM);
}

$context
    ->httpResponse()
    ->body($data)
    ->send();
