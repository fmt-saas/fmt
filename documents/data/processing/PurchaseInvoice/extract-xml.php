<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => 'Extract given UBL XML document data, and return the result as a JSON descriptor.',
    'params'        => [
        'document_id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'description'       => 'Identifier of the document to parse.',
            'required'          => true
        ],
        'dissociate_attachments' => [
            'type'              => 'boolean',
            'description'       => 'If true, the attachements inside the xml document will be removed from xml and linked as associated documents.',
            'default'           => false
        ]
    ],
    'access'        => [
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

/**
 * Methods
 */

$removeAttachmentsFromUblXml = function($ubl_xml) {
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = false;

    if (!$doc->loadXML($ubl_xml, LIBXML_NONET)) {
        throw new RuntimeException('Invalid XML');
    }

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

    foreach ($xpath->query('//cac:Attachment') as $node) {
        $node->parentNode->removeChild($node);
    }

    return $doc->saveXML();
};


/**
 * Action
 */

$document = Document::id($params['document_id'])
    ->read(['data'])
    ->first();

if(!$document) {
    throw new Exception('invalid_document', EQ_ERROR_INVALID_PARAM);
}

$data = eQual::run('get', 'finance_accounting_invoice_extract-ubl', ['xml' => $document['data']]);

if(!in_array($data['document_type'], ['Invoice', 'CreditNote'])) {
    throw new Exception('invalid_ubl_data', EQ_ERROR_INVALID_PARAM);
}

if($params['dissociate_attachments']) {
    if(!empty($data['attachments'])) {
        foreach($data['attachments'] as $attachment) {
            Document::create([
                'name'                  => $attachment['name'],
                'content_type'          => $attachment['content_type'],
                'data'                  => base64_decode($attachment['data']),
                'origin_document_id'    => $document['id']
            ]);
        }

        Document::id($params['document_id'])
            ->update(['data' => $removeAttachmentsFromUblXml($document['data'])]);

        unset($data['attachments']);
    }
}

// #memo - Invoices and credit notes are considered as supplier invoices
$data['document_type'] = 'supplier_invoice';

$context
    ->httpResponse()
    ->body($data)
    ->send();
