<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\ExpenseStatement;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of given fund request.',
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\ExpenseStatement',
            'description'       => "Fiscal year the fund request relates to."
        ],

        'debug' => [
            'type'        => 'boolean',
            'default'     => false
        ],

        'view_id' => [
            'description' => 'View id of the template to use.',
            'type'        => 'string',
            'default'     => 'print.default'
        ],

        'lang' =>  [
            'description' => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'        => 'string',
            'default'     => constant('DEFAULT_LANG')
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

/*
    #todo - still not sure to handle single and grouped printing
    This controller might not be relevant.
*/

/** @var \equal\php\Context $context */
$context = $providers['context'];

$statement = ExpenseStatement::id($params['id'])
    ->read(['fiscal_period_id'])
    ->first();

if(!$statement) {
    throw new Exception('unknown_expense_statement', EQ_ERROR_UNKNOWN_OBJECT);
}

try {
    $output = (string) eQual::run('get', 'realestate_funding_fiscalperiod_expensestatement_batch-pdf', ['fiscal_period_id' => $statement['fiscal_period_id']]);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
