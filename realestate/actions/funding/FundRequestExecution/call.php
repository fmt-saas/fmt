<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\FundRequestExecution;

[$params, $providers] = eQual::announce([
    'description'   => "Call a fund request execution, generate funding requests, and optionally schedule their sending/export.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The Fund Request Execution to call.",
            'foreign_object'    => 'realestate\funding\FundRequestExecution',
            'required'          => true
        ],
        'with_due_balance' =>  [
            'type'              => 'boolean',
            'description'       => 'Take into account the balance status of the co-owners.',
            'help'              => "If set to true, the payment request will be base on Ownership due balance instead of theoretical Funding due amount.",
            'default'           => true
        ],
        'perform_sending' => [
            'type'              => 'boolean',
            'description'       => 'If enabled, generated fund request executions will be sent automatically.',
            'default'           => function ($id = null) {
                if(!$id) {
                    return true;
                }
                $fundRequestExecution = FundRequestExecution::id($id)->read(['is_sending_disabled'])->first();
                if($fundRequestExecution && $fundRequestExecution['is_sending_disabled']) {
                    return false;
                }
                return true;
            }
        ],
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
    throw new Exception("unknown_fund_request_execution", EQ_ERROR_UNKNOWN_OBJECT);
}

if(array_key_exists('with_due_balance', $params)) {
    $fundRequestExecution->update(['with_due_balance' => $params['with_due_balance']]);
}

$values = [
    'perform_sending' => $params['perform_sending']
];

$fundRequestExecution
    ->transition('call')
    ->do('send_fund_request_execution_correspondences', $values);

$context->httpResponse()
        ->status(204)
        ->send();
