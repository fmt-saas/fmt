<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use equal\orm\Domain;
use finance\accounting\FiscalYear;

list($params, $providers) = eQual::announce([
    'description'   => 'Generate a PDF document with funds requests for all owners, for currently open fiscal year.',
    'help'          => 'This controller is meant to be used by list views, hence no direct `id` field is given, but deduced from domain.',
    'params'        => [
        'domain' => [
            'type'              => 'array',
            'description'       => "Specific domain to filter fiscal years (is expected to hold a reference to condo_id).",
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
 Call example

 do: realestate_funding_fiscalyear_fundrequests_print-pdf
    domain: [[["condo_id","=","1"]]]
*/

/** @var \equal\php\Context $context */
$context = $providers['context'];

$domain = new Domain($params['domain']);
$domain->merge(new Domain(['status', '=', 'open']));

$found_condo = false;
foreach($domain->getClauses() as $clause) {
    foreach($clause->getConditions() as $condition) {
        if($condition->getOperand() == 'condo_id') {
            $found_condo = true;
            break;
        }
    }
}

if(!$found_condo) {
    throw new Exception('missing_condo_operand', EQ_ERROR_INVALID_CONFIG);
}

$fiscalYear = FiscalYear::search($domain->toArray())->first();

if(!$fiscalYear) {
    throw new Exception('no_fiscal_year_found', EQ_ERROR_INVALID_CONFIG);
}

$output = eQual::run('get', 'realestate_funding_fiscalyear_fundrequests_batch-pdf', ['fiscal_year_id' => $fiscalYear['id']]);

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
