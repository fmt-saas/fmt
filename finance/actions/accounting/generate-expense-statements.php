<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalPeriod;

[$params, $providers] = eQual::announce([
    'description'   => 'Ensure that a proforma expense statement exists for each condominium fiscal period ending today.',
    'params'        => [],
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

$target_date = strtotime('today');

$fiscalPeriods = FiscalPeriod::search([
        ['condo_id', '<>', null],
        ['status', '=', 'open'],
        ['date_to', '=', $target_date]
    ])
    ->read([
        'condo_id',
        'date_from',
        'date_to',
        'fiscal_year_id' => ['id', 'status']
    ]);

foreach($fiscalPeriods as $fiscal_period_id => $fiscalPeriod) {

    if(
        !isset($fiscalPeriod['fiscal_year_id']['status'])
        || !in_array($fiscalPeriod['fiscal_year_id']['status'], ['open', 'preopen'], true)
    ) {
        continue;
    }

    try {
        eQual::run('do', 'finance_accounting_FiscalPeriod_assert-expense-statement', ['id' => $id]);
    }
    catch(Exception $e) {
        trigger_error("APP::Unexpected error while asserting expense statement for FiscalPerdio ({$id}) " . $e->getMessage(), EQ_REPORT_WARNING);
    }

}

$context->httpResponse()
        ->send();
