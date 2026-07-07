<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => 'Create a SEPA export task for every outgoing Funding candidate that has not been exported yet.',
    'params'        => [],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$domain = [
        ['status', '=', 'credit_balance'],
        ['is_sent', '=', true],
        ['is_exported', '=', false],
        ['due_amount', '<', 0],
        ['has_mandate', '=', false],
        ['counterpart_bank_account_id', 'is not', null]
    ];

$funding_ids = Funding::search($domain)
    ->ids();

if(count($funding_ids) > 0) {
    eQual::run('do', 'realestate_sale_pay_Funding_bulk-export', [
        'ids' => $funding_ids
    ]);
}

$context->httpResponse()
        ->body([
            'count' => count($funding_ids),
            'ids'   => $funding_ids
        ])
        ->send();
