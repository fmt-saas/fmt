<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use realestate\ownership\Ownership;

[$params, $providers] = eQual::announce([
    'description'   => 'Renders the owner account statement as a PDF document for a given ownership and date range.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'description'       => "The ownership that the owner refers to.",
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "First date of the time interval.",
            'required'          => true
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Last date of the time interval.",
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$ownership = Ownership::id($params['id'])
    ->read(['name'])
    ->first();

if(!$ownership) {
    throw new Exception('unknown_ownership', EQ_ERROR_INVALID_PARAM);
}

$data = eQual::run('get', 'finance_accounting_ownerAccountStatement_render-pdf', [
        'ownership_id'  => $params['id'],
        'date_from'     => $params['date_from'],
        'date_to'       => $params['date_to']
    ]);

$filename = rawurlencode("Decompte propriétaire - " . $ownership['name']);

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename*=UTF-8\'\'' . $filename . '.pdf')
        ->body($data)
        ->send();
