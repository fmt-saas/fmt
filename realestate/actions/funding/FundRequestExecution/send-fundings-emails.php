<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestExecutionCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Send all funding request emails for the target fund request execution.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The fund request execution the email sending refers to.",
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

if(!$fundRequestExecution) {
    throw new Exception('unknown_fund_request_execution', EQ_ERROR_UNKNOWN_OBJECT);
}

// fetch correspondences relating to given communication_method
$fundRequestExecutionCorrespondences = FundRequestExecutionCorrespondence::search([
        [ 'fund_request_execution_id', '=', $fundRequestExecution['id'] ],
        [ 'communication_method', '=', $params['communication_method'] ]
    ])
    ->read(['is_sent', 'document_id']);

foreach($fundRequestExecutionCorrespondences as $fund_request_execution_correspondence_id => $fundRequestExecutionCorrespondence) {
    // #memo - `export-fundings-letters` and `send-fundings-emails` are the controllers where fund request documents can be generated on demand
    if(!$fundRequestExecutionCorrespondence['document_id']) {
        try {
            // generate document, add it to EDMS, and attach it to the correspondence
            eQual::run('do', 'realestate_funding_FundRequestExecutionCorrespondence_generate-document', ['id' => $fund_request_execution_correspondence_id]);
        }
        catch(Exception $e) {
            // error while rendering or duplicate
        }
    }
}


// send all generated documents
foreach($fundRequestExecutionCorrespondences as $fund_request_execution_correspondence_id => $fundRequestExecutionCorrespondence) {
    $fundRequestExecutionCorrespondence = FundRequestExecutionCorrespondence::id($fund_request_execution_correspondence_id)
        ->read(['document_id' => ['data']])
        ->first();

    if(!$fundRequestExecutionCorrespondence['document_id']) {
        continue;
    }

    try {
        eQual::run('do', 'realestate_funding_FundRequestExecutionCorrespondence_send-email', ['id' => $fund_request_execution_correspondence_id]);
    }
    catch(Exception $e) {
        trigger_error('APP::Error while sending documents ' . $e->getMessage(), EQ_REPORT_ERROR);
        continue;
    }
}


$context->httpResponse()
        ->status(204)
        ->send();
