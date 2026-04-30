<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\FundRequestExecution;

[$params, $providers] = eQual::announce([
    'description'   => "Export assembly minutes: generate per-invitation documents (if missing), merge them into a single PDF, store the result as a non-EDMS document, and return its id.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The Fund Request Execution the export refers to.",
            'foreign_object'    => 'realestate\funding\FundRequestExecution',
            'required'          => true
        ],
        // reprendre la situation de compte
        'with_due_balance' =>  [
            'type'              => 'boolean',
            'description'       => "Take into account the balance status of the co-owners.",
            'default'           => true
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
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;


$fundRequestExecution = FundRequestExecution::id($params['id'])
    ->read(['status', 'condo_id', 'name']);

if($fundRequestExecution->count() <= 0) {
    throw new Exception("unknown_assembly", EQ_ERROR_UNKNOWN_OBJECT);
}


$fundRequestExecution->transition('call');


$context->httpResponse()
        ->status(204)
        ->send();
