<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\Document;
use documents\DocumentSubtype;
use documents\DocumentType;

[$params, $providers] = eQual::announce([
    'description'   => 'Attempt to identify document type and subtype.',

    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the document.',
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'required'          => true
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

['context' => $context] = $providers;

// search for documents matching given hash code (should be only one match)
$collection = Document::id($params['id']);
$document = $collection->read(['content_type', 'uuid'])->first();

if(!$document) {
    throw new Exception("document_unknown", EQ_ERROR_UNKNOWN_OBJECT);
}

$result = [
    'document_type'      => [],
    'document_subtype'   => []
];

// extract data (based on content-type)
$text = eQual::run('get', 'documents_processing_dump-text', ['id' => $params['id']]);

$document_type = null;
$document_subtype = null;

// use clues in order to attempt retrieving document type and subtype
if(!$document_type) {

    if(in_array($document['content_type'], ['text/plain', 'text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)) {

        $lines = explode("\n", $text);

        $line = trim($lines[0]);

        // CODA file -> bank statement
        if(preg_match('/^0{1}0{4}\d{6}\d{3}05[D ]{1}[A-Z0-9 ]{7}[A-Z0-9 ]{10}[A-Z0-9 ]{26}[A-Z0-9 ]{11}\d{11}[ ]{1}\d{5}[A-Z0-9 ]{16}[A-Z0-9 ]{16}[A-Z0-9 ]{7}2$/', $line)) {
            $document_type = 'bank_statement';
        }
        // check match against ISABEL format
        else {
            $coda_headers = [
                    'Account', 'Account holder', 'Bank', 'Account type', 'Bic',
                    'Statement number', 'Statement currency',
                    'Opening balance date', 'Opening balance', 'Closing balance date', 'Closing balance',
                    'Closing available balance', 'Entry date', 'Value date', 'Transaction amount',
                    'Transaction currency', 'Transaction type', 'Client reference',
                    'Structured Reference', 'Unstructured Reference', 'Bank reference',
                    'Counterparty name', 'Counterparty account', 'Counterparty bank BIC',
                    'Counterparty data', 'Transaction message', 'Sequence number',
                    'Reception Date/Time'
                ];

            $headers = str_getcsv($line, ',');
            $headers = array_map('trim', $headers);
            $missing = array_diff($coda_headers, $headers);

            if(empty($missing)) {
                // ISABEL bank statement -> bank statement
                $document_type = 'bank_statement';
            }
        }
    }
}

if(!$document_type) {

    $map_signatures = [
        'invoice' => [
            'signatures' => [
                'facture', 'invoice', 'factuur'
            ],
            'subtypes' => [
                'advance_invoice' => [
                    'signatures' => [
                        // FR
                        'acompte', 'facture intermediaire', 'facture d\'acompte',
                        // EN
                        'advance invoice', 'deposit invoice',
                        // NL
                        'voorschot', 'tussentijdse factuur', 'voorschotfactuur', 'waarborgfactuur'
                    ],
                ],
                'adjustment_invoice' => [
                    'signatures' => [
                        // FR
                        'facture de regularisation', 'regularisation', 'rectificatif',
                        // EN
                        'adjustment invoice',
                        // NL
                        'regularisatiefactuur', 'regularisatie', 'rectificatie',
                    ],
                ],
                'off_contract' => [
                    'signatures' => [
                        // FR
                        'hors contrat', 'facture supplementaire',
                        // EN
                        'off contract',
                        // NL
                        'buiten contract', 'bijkomende factuur',
                    ],
                ],
            ],
        ],
        'bank-statement' => [
            'signatures' => [
                'releve bancaire', 'releve de compte', 'bank statement', 'bank account statement',
            ],
            'subtypes' => []
        ],
    ];

    foreach($map_signatures as $type => $typeInfo) {

        foreach($typeInfo['signatures'] as $signature) {
            if(stripos($text, $signature) !== false) {
                $document_type = $type;

                foreach($typeInfo['subtypes'] as $subtype => $subInfo) {
                    foreach($subInfo['signatures'] as $subsig) {
                        if(stripos($text, $subsig) !== false) {
                            $document_subtype = $subtype;
                            break 2;
                        }
                    }
                }

                break 2;
            }
        }

        if(!is_null($document_type)) {
            break;
        }
    }
}

if($document_type) {

    $documentTypes = DocumentType::search()->read(['code'])->get();

    foreach($documentTypes as $document_type_id => $documentType) {
        if($documentType['code'] === $document_type) {
            $result['document_type'] = [
                'id'    => $document_type_id,
                'code'  => $document_type
            ];
            break;
        }
    }

    if($document_subtype) {
        $documentSubtypes = DocumentSubtype::search()->read(['code'])->get();
        foreach($documentSubtypes as $document_subtype_id => $documentSubtype) {
            if($documentSubtype['code'] === $document_subtype) {
                $result['document_subtype'] = [
                    'id'    => $document_subtype_id,
                    'code'  => $document_subtype
                ];
                break;
            }
        }
    }

}

$context->httpResponse()
        ->body($result)
        ->send();
