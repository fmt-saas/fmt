<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF file of the given Expense Summary (virtual entity).',
    'params'        => [
        'params' => [
            'description'       => 'Optional params for rendering the targeted expense summary.',
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
$output = eQual::run('get', 'finance_accounting_expenseSummary_render-pdf', [
        'params'    => $params['params'],
        'domain'    => $params['domain']
    ]);

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="expense-summary-export.pdf"')
        ->body($output)
        ->send();