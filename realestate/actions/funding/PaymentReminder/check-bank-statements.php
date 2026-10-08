<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\bank\CondominiumBankAccount;
use realestate\funding\PaymentReminder;

[$params, $providers] = eQual::announce([
    'description'   => "Checks that the active primary and tier condominium bank accounts have recent bank statements.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'description'       => 'Identifier of the payment reminder to check.',
            'foreign_object'    => 'realestate\funding\PaymentReminder',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context         $context
 * @var \equal\dispatch\Dispatcher $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

$paymentReminder = PaymentReminder::id($params['id'])
    ->read(['condo_id'])
    ->first();

if(!$paymentReminder) {
    throw new Exception('unknown_payment_reminder', EQ_ERROR_UNKNOWN_OBJECT);
}

$threshold_date = strtotime('today') - (7 * 86400);
$outdated_bank_account_ids = [];

$bankAccounts = [];

$primaryBankAccount = CondominiumBankAccount::search([
        ['condo_id', '=', $paymentReminder['condo_id']],
        ['is_active', '=', true],
        ['is_primary', '=', true]
    ], ['limit' => 1])
    ->read(['created', 'last_statement_date'])
    ->first();

if($primaryBankAccount) {
    $bankAccounts[$primaryBankAccount['id']] = $primaryBankAccount;
}

$tierBankAccount = CondominiumBankAccount::search([
        ['condo_id', '=', $paymentReminder['condo_id']],
        ['is_active', '=', true],
        ['bank_account_type', '=', 'bank_tier']
    ], ['limit' => 1])
    ->read(['created', 'last_statement_date'])
    ->first();

if($tierBankAccount) {
    $bankAccounts[$tierBankAccount['id']] = $tierBankAccount;
}

foreach($bankAccounts as $bank_account_id => $bankAccount) {
    $last_statement_date = $bankAccount['last_statement_date'] ?? null;
    $reference_date = $last_statement_date ?: ($bankAccount['created'] ?? null);

    if($reference_date && $reference_date <= $threshold_date) {
        $outdated_bank_account_ids[] = $bank_account_id;
    }
}

$message_model = 'realestate.funding.payment_reminder.outdated_bank_statements';
$object_class = PaymentReminder::getType();

if(count($outdated_bank_account_ids)) {
    $dispatch->dispatch(
        $message_model,
        $object_class,
        $params['id'],
        'important',
        'realestate_funding_PaymentReminder_check-bank-statements',
        ['id' => $params['id']]
    );
}
else {
    $dispatch->cancel($message_model, $object_class, $params['id']);
}

$context->httpResponse()
    ->status(200)
    ->body($outdated_bank_account_ids)
    ->send();
