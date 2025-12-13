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
        ],
        'ids' => [
            'type'              => 'one2many',
            'foreign_object'    => 'sale\pay\Funding',
            'description'       => 'List of Funding IDs to include in the SEPA file.',
            'help'              => 'All received fundings are expected to be from a same condominium (condo_id). A maximum of 50 fundings per SEPA file is enforced.',
            'default'           => []
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

$fundings_ids = $params['ids'];

if(isset($params['id']) && $params['id']) {
    $fundings_ids[] = $params['id'];
}


if(count($fundings_ids) <= 0) {
    throw new Exception("no_fundings", EQ_ERROR_INVALID_PARAM);
}

if(count($fundings_ids) > 50) {
    // truncate to max 50 fundings per SEPA file
    $fundings_ids = array_slice($fundings_ids, 0, 50);
}

// ensure booking object exists and is readable
$fundings = Funding::ids($fundings_ids)
    ->read(['name', 'condo_id'])
    ->get();

$funding = reset($fundings);

// get the SEPA XML data for the given fundings
$output = eQual::run('get', 'sale_pay_Funding_sepa', [
        'ids' => $fundings_ids
    ]);

// store final result as a document (not visible through EDMS)
$document = Document::create([
        'name'          => 'Export SEPA - ' . date('Y-m-d_His'),
        'content_type'  => 'application/xml',
        'data'          => $output,
        'condo_id'      => $funding['condo_id']
    ])
    ->first();

Funding::ids($fundings_ids)->update(['is_sent' => true]);

$context->httpResponse()
        ->body([
            'document_id' => $document['id']
        ])
        ->send();