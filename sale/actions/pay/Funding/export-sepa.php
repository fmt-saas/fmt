<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a SEPA XML file for multiple Fundings according to ISO 20022 pain.001.001.03.',
    'help'          => 'Expected param is either a single Funding id or a list of Funding ids via the "ids" parameter. A maximum of 50 fundings per SEPA file is enforced.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\pay\Funding',
            'description'       => 'The Funding for which the SEPA is requested.',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);

/** @var \equal\php\Context $context */
/** @var \equal\orm\ObjectManager $orm */
['context' => $context, 'orm' => $orm] = $providers;

// ensure object exists and is readable
$funding = Funding::id($params['id'])
    ->read(['name', 'is_sent', 'is_exported', 'sepa_document_id', 'condo_id' => ['code']])
    ->first();

if($funding['is_exported']) {
    throw new Exception("funding_already_exported", EQ_ERROR_INVALID_PARAM);
}

if(!$funding['sepa_document_id']) {
    throw new Exception("missing_sepa_document", EQ_ERROR_INVALID_PARAM);
}

$document = Document::id($funding['sepa_document_id'])->read(['data'])->first();

Funding::id($params['id'])->update(['is_exported' => true]);

$context->httpResponse()
        ->body([
            'document_id' => $document['id']
        ])
        ->send();