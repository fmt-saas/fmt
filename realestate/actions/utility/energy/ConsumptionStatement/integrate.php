<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\utility\energy\ConsumptionStatement;

[$params, $providers] = eQual::announce([
    'description' => 'Integrate a consumption statement into accounting using the selected posting date.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'description'    => 'The consumption statement to integrate.',
            'foreign_object' => 'realestate\utility\energy\ConsumptionStatement',
            'required'       => true
        ],
        'posting_date' => [
            'type'        => 'date',
            'description' => 'Date on which the consumption statement must be posted in accounting.',
            'required'    => true,
            'default'     => function ($id = null) {
                if(!$id) {
                    return null;
                }

                $consumptionStatement = ConsumptionStatement::id($id)
                    ->read(['posting_date'])
                    ->first();

                return $consumptionStatement['posting_date'] ?? null;
            }
        ]
    ],
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$consumptionStatements = ConsumptionStatement::id($params['id'])
    ->read(['id']);

if($consumptionStatements->count() <= 0) {
    throw new Exception('unknown_consumption_statement', EQ_ERROR_UNKNOWN_OBJECT);
}

$consumptionStatements
    ->update(['posting_date' => $params['posting_date']])
    ->transition('integrate');

$context->httpResponse()
        ->status(204)
        ->send();
