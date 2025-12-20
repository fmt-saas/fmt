<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Send all email minutes reports for the target assembly.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the invitation sending refers to.",
            'foreign_object'    => 'realestate\funding\FundRequestExecution',
            'required'          => true
        ],

        'communication_method' => [
            'type'              => 'string',
            'description'       => 'Method of sending.',
            'help'              => 'This controllers expect only digital communication methods (e.g. email).',
            'default'           => 'email',
            'selection'         => [
                'email'
            ]
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context                 $context
 */
['context' => $context] = $providers;


$fundRequestExecution = FundRequestExecution::id($params['id'])
    ->read(['status', 'condo_id', 'name'])
    ->first();

if(!$assembly) {
    throw new Exception("unknown_assembly", EQ_ERROR_UNKNOWN_OBJECT);
}

// fetch invitations relating to given communication_method
$fundRequestCorrespondences = FundRequestCorrespondence::search([
        [ 'assembly_id', '=', $assembly['id'] ],
        [ 'communication_method', '=', $params['communication_method'] ]
    ])
    ->read(['is_sent', 'document_id']);

$fund_request_correspondences_ids = [];

foreach($fundRequestCorrespondences as $fund_request_correspondence_id => $fundRequestCorrespondence) {
    // #memo - `export-invitation` and `send-invitation` are the only controllers where documents are generated for Assembly invites
    if(!$fundRequestCorrespondence['document_id']) {
        // generate document, add it to EDMS, and attach it to invitation
        eQual::run('do', 'realestate_funding_FundRequestCorrespondence_generate-document', ['id' => $fund_request_correspondence_id]);
    }

    $fundRequestCorrespondence = FundRequestCorrespondence::id($fund_request_correspondence_id)
        ->read(['document_id' => ['data']])
        ->first();

    if(!$fundRequestCorrespondence['document_id']) {
        continue;
    }

    $fund_request_correspondences_ids[] = $fund_request_correspondence_id;
}

// send all generated documents
foreach($fund_request_correspondences_ids as $fund_request_correspondence_id) {
    try {
        eQual::run('do', 'realestate_funding_FundRequestCorrespondence_send', ['id' => $fund_request_correspondence_id]);
    }
    catch(Exception $e) {
        trigger_error('APP::Error while sending documents ' . $e->getMessage(), EQ_REPORT_ERROR);
        throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
