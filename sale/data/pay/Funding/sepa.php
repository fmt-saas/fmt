<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use Digitick\Sepa\DomBuilder\DomBuilderFactory;
use Digitick\Sepa\GroupHeader;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\CustomerCreditTransferFile;
use Digitick\Sepa\TransferInformation\CustomerCreditTransferInformation;
use sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for the Funding: returns a collection of Reports according to extra paramaters.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\pay\Funding',
            'description'       => 'The costumer to which the funding relates to.',
        ]
    ],
    'response'      => [
        'content-type'  => 'text/xml',
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


$funding = Funding::id($params['id'])
    ->read([
        'due_amount',
        'payment_reference',
        'bank_account_id' => ['bank_account_iban', 'bank_account_bic', 'owner_identity_id' => ['name']],
        'counterpart_bank_account_id' => ['bank_account_iban', 'bank_account_bic', 'owner_identity_id' => ['name']]
    ])
    ->first();

if(!$funding || !$funding['bank_account_id'] || !$funding['counterpart_bank_account_id']) {
    throw new Exception('missing_bank_accounts', EQ_ERROR_INVALID_PARAM);
}

$amount = round((float) $funding['due_amount'], 2);

if($amount <= 0) {
    throw new Exception('invalid_amount', EQ_ERROR_INVALID_PARAM);
}

$from_iban = $funding['bank_account_id']['bank_account_iban'];
$from_bic  = $funding['bank_account_id']['bank_account_bic'];
$from_name = $funding['bank_account_id']['owner_identity_id']['name'];

$to_iban   = $funding['counterpart_bank_account_id']['bank_account_iban'];
$to_bic    = $funding['counterpart_bank_account_id']['bank_account_bic'];
$to_name   = $funding['counterpart_bank_account_id']['owner_identity_id']['name'];

$reference = $funding['payment_reference'];

// create Transfer
$transfer = new CustomerCreditTransferInformation($amount, $to_iban, $to_name);
$transfer->setBic($to_bic);
$transfer->setRemittanceInformation($reference);

// add payment (order)
$payment = new PaymentInformation(
    'FUNDING-PAY-' . $funding['id'],
    $from_iban,
    $from_bic,
    $from_name
);
$payment->addTransfer($transfer);

// create SEPA file
$groupHeader = new GroupHeader('FUNDING-' . $funding['id'], $from_name);
$sepaFile = new CustomerCreditTransferFile($groupHeader);
$sepaFile->addPaymentInformation($payment);


$domBuilder = DomBuilderFactory::createDomBuilder($sepaFile);
$xmlOutput = $domBuilder->asXml();

$filename = sprintf("SEPA_TRANSFER_%s_%04d", date('Ymd'), $funding['id']) . '.xml';

$context->httpResponse()
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
        ->body($xmlOutput)
        ->send();
