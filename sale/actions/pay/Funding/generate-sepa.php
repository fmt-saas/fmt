<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use finance\bank\CondominiumBankAccount;
use realestate\sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a SEPA XML file for multiple Fundings according to ISO 20022 pain.001.001.03.',
    'help'          => 'Expected param is either a single Funding id or a list of Funding ids via the "ids" parameter. A maximum of 50 fundings per SEPA file is enforced.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\pay\Funding',
            'description'       => 'The Funding for which the SEPA is requested.',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'dispatch' ]
]);

/**
 * @var \equal\php\Context              $context
 * @var \equal\orm\ObjectManager        $orm
 * @var \equal\dispatch\Dispatcher      $dispatch
 */
['context' => $context, 'orm' => $orm, 'dispatch' => $dispatch] = $providers;

// ensure object exists and is readable
$funding = Funding::id($params['id'])
    ->read([
        'name',
        'bank_account_id',
        'counterpart_bank_account_id',
        'is_generated',
        'is_sent',
        'is_exported',
        'sepa_document_id',
        'has_payment_on_hold',
        'has_mandate',
        'remaining_amount',
        'condo_id' => ['code']
    ])
    ->first();

if(!$funding || !$funding['bank_account_id'] || !$funding['counterpart_bank_account_id']) {
    throw new Exception('missing_bank_accounts', EQ_ERROR_INVALID_PARAM);
}

if($funding['is_generated']) {
    throw new Exception("funding_already_generated", EQ_ERROR_INVALID_PARAM);
}

if($funding['is_exported']) {
    throw new Exception("funding_already_exported", EQ_ERROR_INVALID_PARAM);
}

if($funding['is_sent']) {
    throw new Exception("funding_already_sent", EQ_ERROR_INVALID_PARAM);
}

if($funding['sepa_document_id']) {
    throw new Exception("sepa_document_already_generated", EQ_ERROR_INVALID_PARAM);
}

if($funding['remaining_amount'] >= 0.0) {
    throw new Exception('sepa_only_for_outgoing_funding', EQ_ERROR_INVALID_PARAM);
}

if($funding['has_payment_on_hold']) {
    throw new Exception("aborted_has_payment_on_hold", 0);
}

if($funding['has_mandate']) {
    throw new Exception("aborted_has_mandate", 0);
}

$condominiumBankAccount = CondominiumBankAccount::id($funding['bank_account_id'])->read(['available_balance'])->first();

if($condominiumBankAccount['available_balance'] + $funding['remaining_amount'] < 0.0) {
    $dispatch->dispatch('finance.accounting.payment.insufficient_funds', Funding::getType(), $params['id'], 'important');
}
else {

    // get the SEPA XML data for the given fundings
    $output = eQual::run('get', 'sale_pay_Funding_sepa', [
            'ids' => [$params['id']]
        ]);

    // store final result as a document (not visible through EDMS)
    $document = Document::create([
            'name'          => 'Export SEPA - ' . date('Y-m-d_H-i-s') . ' - ' . $funding['condo_id']['code'] . ' - ' . $funding['id'],
            'content_type'  => 'application/xml',
            'data'          => $output,
            'condo_id'      => $funding['condo_id']['id']
        ])
        ->first();

    Funding::id($params['id'])
        ->update([
            'sepa_document_id'  => $document['id'],
            'is_generated'      => true
        ]);
}

$context->httpResponse()
        ->status(201)
        ->send();
