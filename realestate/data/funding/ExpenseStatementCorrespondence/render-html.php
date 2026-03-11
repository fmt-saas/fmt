<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\ExpenseStatementCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of a Expense Statement.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific ExpenseStatementCorrespondence to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\ExpenseStatementCorrespondence',
            'required'          => true
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
            'default'     => 'fr'
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'text/html',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);


/** @var \equal\php\Context $context */
$context = $providers['context'];

$expenseStatementCorrespondence = ExpenseStatementCorrespondence::id($params['id'])
    ->read(['status', 'condo_id', 'expense_statement_id', 'ownership_id', 'name'])
    ->first();

if(!$expenseStatementCorrespondence) {
    throw new Exception("unknown_expense_statement_correspondence", EQ_ERROR_UNKNOWN_OBJECT);
}

$html = eQual::run('get', 'realestate_funding_fiscalperiod_expensestatement_single-html', [
        'expense_statement_id'  => $expenseStatementCorrespondence['expense_statement_id'],
        'ownership_id'          => $expenseStatementCorrespondence['ownership_id'],
        'debug'                 => $params['debug'],
        'view_id'               => $params['view_id'],
        'lang'                  => $params['lang']
    ]);

$context->httpResponse()
        ->body($html)
        ->send();
