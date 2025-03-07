<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\accounting\FiscalYear;
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => 'Create a new (current) fiscal year for the targeted Condominium. Use this only when no fiscal year exists yet.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifiers of the targeted condominium.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\Condominium',
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

$user_id = $auth->userId();

if(!$access->userHasCondoRole($user_id, ['manager', 'accountant'], $params['id'])) {
    throw new Exception('role_not_granted', EQ_ERROR_NOT_ALLOWED);
}

$condo = Condominium::id($params['id']);

if($condo->count() != 1) {
    throw new Exception('invalid_condo_id', EQ_ERROR_INVALID_PARAM);
}

// remove any existing draft
FiscalYear::search([['condo_id', '=', $params['id']], ['status', '=', 'draft']])->delete(true);

$condo->do('create_fiscal_year');

$context->httpResponse()
        ->status(204)
        ->send();
