<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use sale\accounting\invoice\SaleInvoice;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for the Funding: returns a collection of Reports according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'realestate\sale\pay\Funding'
        ],
        'condo_id' => [
            'type'              => 'many2one',
            'description'       => "The condominium the email relates to.",
            'foreign_object'    => 'realestate\property\Condominium'
        ],
        'supplier_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'purchase\supplier\Supplier',
            'description'       => 'The supplier to which the funding relates to.',
        ],
        'employee_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'hr\employee\Employee',
            'description'       => 'Employee currently in charge of the processing.'
        ],
        'invoice_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
            'description'       => 'The invoice to which the funding relates to.',
        ],
        'due_amount_min' => [
            'type'              => 'integer',
            'description'       => 'Minimal amount expected for the funding.'
        ],
        'due_amount_max' => [
            'type'              => 'integer',
            'description'       => 'Maximum amount expected for funding.'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => 'First day of the searched period.',
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Last day the searched period.',
        ],
        'funding_type' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'purchase_invoice',
                'expense_statement',
                'misc_operation',
                'statement_line'
            ],
            'default'           => 'all'
        ],
        'payment_reference' => [
            'type'              => 'string',
            'description'       => 'Message for identifying the purpose of the transaction.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$domain = $params['domain'];

if(isset($params['date_from'])) {
    $domain = Domain::conditionAdd($domain, ['due_date', '>=', $params['date_from']]);
}

if(isset($params['date_to'])) {
    $domain = Domain::conditionAdd($domain, ['due_date', '<=', $params['date_to']]);
}

if(isset($params['condo_id']) && $params['condo_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['condo_id', '=', $params['condo_id']]);
}

if(isset($params['due_amount_min'])) {
    $params['due_amount_min'] = -abs($params['due_amount_min']);
    $domain = Domain::conditionAdd($domain, ['remaining_amount', '<=', $params['due_amount_min']]);
}

if(isset($params['due_amount_max'])) {
    $params['due_amount_max'] = -abs($params['due_amount_max']);
    $domain = Domain::conditionAdd($domain, ['remaining_amount', '>=', $params['due_amount_max']]);
}

if(isset($params['invoice_id']) && $params['invoice_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['purchase_invoice_id', '=', $params['invoice_id']]);
}

if(isset($params['supplier_id']) && $params['supplier_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['supplier_id', '=', $params['supplier_id']]);
}

if(isset($params['employee_id']) && $params['employee_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['assigned_employee_id', '=', $params['employee_id']]);
}

if(isset($params['payment_reference']) && strlen($params['payment_reference']) > 0 ) {
    $domain = Domain::conditionAdd($domain, ['payment_reference', 'like', '%'. $params['payment_reference'] . '%']);
}

if(isset($params['funding_type']) && strlen($params['funding_type']) > 0 && $params['funding_type'] !== 'all') {
    $domain = Domain::conditionAdd($domain, ['funding_type', '=', $params['funding_type']]);
}

$params['domain'] = $domain;
$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
