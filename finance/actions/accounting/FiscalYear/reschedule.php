<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalYear;

[$params, $providers] = eQual::announce([
    'description'   => 'Update the dates of a fiscal year.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'description'       => 'Fiscal year to reschedule.',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'required'          => true
        ],
        'date_from' => [
            'type'              => 'date',
            'usage'             => 'date/plain',
            'description'       => 'First day of the fiscal year (included).',
            'default'           => function ($id = null) {
                if(!$id) {
                    return null;
                }

                $fiscalYear = FiscalYear::id($id)
                    ->read(['date_from'])
                    ->first();

                return $fiscalYear['date_from'] ?? null;
            },
            'required'          => true
        ],
        'date_to' => [
            'type'              => 'date',
            'usage'             => 'date/plain',
            'description'       => 'Last day of the fiscal year (included).',
            'default'           => function ($id = null) {
                if(!$id) {
                    return null;
                }

                $fiscalYear = FiscalYear::id($id)
                    ->read(['date_to'])
                    ->first();

                return $fiscalYear['date_to'] ?? null;
            },
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'self']
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\Collection $self
 */
['context' => $context, 'self' => $self] = $providers;

$self->do('reschedule', [
    'date_from' => $params['date_from'],
    'date_to'   => $params['date_to']
]);

$context->httpResponse()
    ->status(204)
    ->send();
