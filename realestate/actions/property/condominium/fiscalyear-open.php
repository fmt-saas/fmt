<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => 'Opens a new fiscal year for the targeted Condominium.',
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

$condo->do('open_fiscal_year');

$context->httpResponse()
        ->status(204)
        ->send();
