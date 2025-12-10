<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Digitick\Sepa\DomBuilder\DomBuilderFactory;
use Digitick\Sepa\GroupHeader;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\CustomerCreditTransferFile;
use Digitick\Sepa\TransferInformation\CustomerCreditTransferInformation;
use sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => 'Generates a SEPA xml doc for a given Funding.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\pay\Funding',
            'description'       => 'The Funding for which the SEPA is requested.',
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

// #memo - SEPA are supposed to be outgoing payment, so funding amount should be negative
$amount = abs(round((float) $funding['due_amount'] * 100, 2));

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
