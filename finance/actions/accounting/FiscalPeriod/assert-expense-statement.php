<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalPeriod;
use realestate\funding\ExpenseStatement;

[$params, $providers] = eQual::announce([
    'description'   => 'Ensure that a proforma expense statement exists for each condominium fiscal period ending today.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifiers of the targeted Fiscal Period.',
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalPeriod',
            'required'          => true
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth', 'access']
]);

/**
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \fmt\access\AccessController        $access
 * @var \equal\php\Context                  $context
 */
['access' => $access, 'auth' => $auth, 'context' => $context] = $providers;

$fiscalPeriod = FiscalPeriod::id($params['id'])
    ->read([
        'condo_id',
        'status',
        'date_from',
        'date_to',
        'fiscal_year_id' => ['id', 'status']
    ])
    ->first();


if(!$fiscalPeriod) {
    throw new \Exception('unknown_fiscal_period', EQ_ERROR_INVALID_PARAM);
}

if($fiscalPeriod['status'] !== 'open') {
    throw new \Exception('non_open_fiscal_period', EQ_ERROR_INVALID_PARAM);
}

$fiscal_period_id = $fiscalPeriod['id'];

if(
    !isset($fiscalPeriod['fiscal_year_id']['status'])
    || !in_array($fiscalPeriod['fiscal_year_id']['status'], ['open', 'preopen'], true)
) {
    continue;
}

$existingExpenseStatement = ExpenseStatement::search([
        ['condo_id', '=', $fiscalPeriod['condo_id']],
        ['fiscal_period_id', '=', $fiscal_period_id],
        ['invoice_type', '=', 'expense_statement'],
        ['status', '=', 'proforma']
    ])
    ->first();

if(!$existingExpenseStatement) {
    ExpenseStatement::create([
            'condo_id'          => $fiscalPeriod['condo_id'],
            'fiscal_period_id'  => $fiscal_period_id,
            'fiscal_year_id'    => $fiscalPeriod['fiscal_year_id']['id'],
            'request_date'      => $fiscalPeriod['date_to'],
            'has_date_range'    => true,
            'date_from'         => $fiscalPeriod['date_from'],
            'date_to'           => $fiscalPeriod['date_to'],
            'invoice_type'      => 'expense_statement'
        ]);
}


$context->httpResponse()
        ->status($existingExpenseStatement ? 200 : 204)
        ->send();
