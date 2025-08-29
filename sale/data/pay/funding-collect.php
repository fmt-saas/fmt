<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use sale\accounting\invoice\Invoice;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for the Funding: returns a collection of Reports according to extra paramaters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'sale\pay\Funding'
        ],
        'customer_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\Customer',
            'description'       => 'The customer to which the funding relates to.',
        ],
        'invoice_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\accounting\invoice\Invoice',
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
list($context, $orm) = [ $providers['context'], $providers['orm'] ];

$domain = $params['domain'];

if(isset($params['due_amount_min']) && $params['due_amount_min'] > 0) {
    $domain = Domain::conditionAdd($domain, ['due_amount', '>=', $params['due_amount_min']]);
}

if(isset($params['due_amount_max']) && $params['due_amount_max'] > 0) {
    $domain = Domain::conditionAdd($domain, ['due_amount', '<=', $params['due_amount_max']]);
}

if(isset($params['invoice_id']) && $params['invoice_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['invoice_id', '=', $params['invoice_id']]);
}

if(isset($params['customer_id']) && $params['customer_id'] > 0) {
    $invoices_ids = [];
    $invoices_ids = Invoice::search(['customer_id', '=', $params['customer_id']])->ids();
    if(count($invoices_ids)) {
        $domain = Domain::conditionAdd($domain, ['invoice_id', 'in', $invoices_ids]);
    }
}

if(isset($params['payment_reference']) && strlen($params['payment_reference']) > 0 ) {
    $domain = Domain::conditionAdd($domain, ['payment_reference', 'like', '%'. $params['payment_reference'].'%']);
}

$params['domain'] = $domain;
$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
