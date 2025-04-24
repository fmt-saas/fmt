<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use realestate\funding\ExpenseStatement;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of given fund request.',
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\ExpenseStatement',
            'description'       => "Identifier of the Expense statement to render.",
            'domain'            => ['condo_id', '=', 'object.condo_id']
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

/** @var \equal\php\Context $context */
$context = $providers['context'];

/*
    #todo - still not sure to handle single and grouped printing
    This controller should remain synced with packages\realestate\data\funding\fiscalperiod\expensestatement\batch-pdf.php (which uses single-html & single-pdf)
*/

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
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_ERROR_INVALID_CONFIG);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
