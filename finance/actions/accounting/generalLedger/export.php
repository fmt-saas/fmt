<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use realestate\funding\FundRequest;


[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF file of the given fund request.',
    'params'        => [
        'params' => [
            'description'       => 'Optional identifier of a specific targeted Ownership to limit to rendering to.',
            'help'              => 'Expected/possible keys are: condo_id, account_id, fiscal_year_id, date_from, date_to.',
            'type'              => 'array',
            'required'          => true
        ],
        'domain' => [
            'description'   => 'Criterias that results have to match (series of conjunctions)',
            'type'          => 'array',
            'default'       => []
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

// pass data to PDF renderer
$output = eQual::run('get', 'finance_accounting_generalLedger_render-pdf', [
        'params'    => $params['params'],
        'domain'    => $params['domain']
    ]);

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="general-ledger-export.pdf"')
        ->body($output)
        ->send();