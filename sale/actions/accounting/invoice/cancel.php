<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use sale\accounting\invoice\Invoice;

list($params, $providers) = eQual::announce([
    'description'   => 'Cancel given invoices, can keep or cancel linked receivables.',
    'params'        => [

        'id' =>  [
            'type'              => 'integer',
            'description'       => 'Identifier of the targeted invoice.'
        ],

        'ids' =>  [
            'description'       => 'Identifiers of the targeted invoices.',
            'type'              => 'one2many',
            'foreign_object'    => 'sale\accounting\invoice\Invoice',
            'default'           => []
        ],

        'keep_receivables' => [
            'type'              => 'boolean',
            'description'       => 'If true sets receivables back to pending, else sets them to cancelled.',
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

$context = $providers['context'];

if(empty($params['ids'])) {
    if(!isset($params['id']) || $params['id'] <= 0) {
        throw new Exception('invoice_invalid_id', QN_ERROR_INVALID_PARAM);
    }

    $params['ids'][] = $params['id'];
}

$invoices_ids = Invoice::search([
    ['id', 'in', $params['ids']],
    ['status', '=', 'posted']
])
    ->ids();

if(count($params['ids']) !== count($invoices_ids)) {
    throw new Exception('invoice_invalid_id', QN_ERROR_INVALID_PARAM);
}

Invoice::ids($invoices_ids)
    ->transition(
        $params['keep_receivables'] ? 'cancel-keep-receivables' : 'cancel'
    );

$context->httpResponse()
        ->status(204)
        ->send();
