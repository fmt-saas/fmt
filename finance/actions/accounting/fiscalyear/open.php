<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\accounting\FiscalYear;

[$params, $providers] = eQual::announce([
    'description'   => 'Open a given fiscal year.',
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

$fiscalYear->transition('open');

$context->httpResponse()
        ->status(204)
        ->send();
