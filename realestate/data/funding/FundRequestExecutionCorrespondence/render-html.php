<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\FundRequestExecutionCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an HTML document for a fund request execution correspondence.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific FundRequestExecutionCorrespondence to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\FundRequestExecutionCorrespondence',
            'required'          => true
        ],
        'debug' => [
            'type'              => 'boolean',
            'default'           => false
        ],
        'view_id' => [
            'type'              => 'string',
            'default'           => 'print.default'
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'text/html',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$fundRequestExecutionCorrespondence = FundRequestExecutionCorrespondence::id($params['id'])
    ->read(['fund_request_execution_id', 'ownership_id', 'owner_id'])
    ->first();

if(!$fundRequestExecutionCorrespondence) {
    throw new Exception('unknown_fund_request_execution_correspondence', EQ_ERROR_UNKNOWN_OBJECT);
}

$html = eQual::run('get', 'realestate_funding_fiscalperiod_fundrequestexecution_single-html', [
    'fund_request_execution_id' => $fundRequestExecutionCorrespondence['fund_request_execution_id'],
    'ownership_id'              => $fundRequestExecutionCorrespondence['ownership_id'],
    'owner_id'                  => $fundRequestExecutionCorrespondence['owner_id'],
    'debug'                     => $params['debug'],
    'view_id'                   => $params['view_id']
]);

$context->httpResponse()
        ->body($html)
        ->send();
