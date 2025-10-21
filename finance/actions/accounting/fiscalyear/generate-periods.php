<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalYear;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate the periods for a given fiscal year, according to its configuration.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifiers of the targeted Fiscal Year.',
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
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

$fiscalYear = FiscalYear::id($params['id']);

if($fiscalYear->count() != 1) {
    throw new Exception('invalid_fiscal_year_id', EQ_ERROR_INVALID_PARAM);
}

$fiscalYear->do('generate_periods');

$context->httpResponse()
        ->status(204)
        ->send();
