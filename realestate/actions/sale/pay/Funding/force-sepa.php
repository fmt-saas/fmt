<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\purchase\accounting\invoice\PurchaseInvoice;
use realestate\sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'type'          => 'do',
    'name'          => 'realestate_sale_pay_Funding_force-sepa',
    'package_name'  => 'realestate',
    'description'   => 'Force SEPA generation for an outgoing Funding linked to a purchase invoice.',
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\sale\pay\Funding',
            'description'      => 'Identifier of the Funding.',
            'required'         => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$funding = Funding::id($params['id'])
    ->read([
        'funding_type',
        'purchase_invoice_id',
        'remaining_amount',
        'is_sent',
        'is_exported',
        'sepa_document_id'
    ])
    ->first();

if(!$funding) {
    throw new Exception('unknown_funding', EQ_ERROR_UNKNOWN_OBJECT);
}

if($funding['funding_type'] !== 'purchase_invoice') {
    throw new Exception('sepa_only_for_purchase_invoice', EQ_ERROR_INVALID_PARAM);
}

if(!$funding['purchase_invoice_id']) {
    throw new Exception('missing_purchase_invoice', EQ_ERROR_INVALID_PARAM);
}

if($funding['is_exported']) {
    throw new Exception('funding_already_exported', EQ_ERROR_INVALID_PARAM);
}

if($funding['is_sent']) {
    throw new Exception('funding_already_sent', EQ_ERROR_INVALID_PARAM);
}

if($funding['sepa_document_id']) {
    throw new Exception('sepa_document_already_generated', EQ_ERROR_INVALID_PARAM);
}

if($funding['remaining_amount'] >= 0) {
    throw new Exception('sepa_only_for_outgoing_funding', EQ_ERROR_INVALID_PARAM);
}

PurchaseInvoice::id($funding['purchase_invoice_id'])->update([
    'has_mandate'           => false,
    'has_payment_on_hold'   => false
]);

eQual::run('do', 'sale_pay_Funding_generate-sepa', [
    'id' => $params['id']
]);

eQual::run('do', 'sale_pay_Funding_export-sepa', [
    'id' => $params['id']
]);

$context->httpResponse()
        ->status(205)
        ->send();
