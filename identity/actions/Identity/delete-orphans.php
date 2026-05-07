<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use identity\Identity;

[$params, $providers] = eQual::announce([
    'description'   => "Removes identities that aren't linked to any other objects (User, Customer, Condo, Supplier, Employee, ...).",
    'params'        => [
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

Identity::search([
    ['user_id', 'is', null],
    ['customer_id', 'is', null],
    ['condominium_id', 'is', null],
    ['supplier_id', 'is', null],
    ['contact_id', 'is', null],
    ['employee_id', 'is', null],
    ['organisation_id', 'is', null],
    ['managing_agent_id', 'is', null],
    ['tenant_id', 'is', null]
])
    ->delete(true);

$context
    ->httpResponse()
    ->send();
