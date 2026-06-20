<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\PaymentReminder;
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => "Generate reminders of overdue fundings of funding requests and expense statements.",
    'params'        => [],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$condominiums = Condominium::search()
    ->read(['id', 'name']);

foreach($condominiums as $condo_id => $condominium) {

    // remove any existing non-validated PaymentReminder
    PaymentReminder::search([['condo_id', '=', $condo_id], ['status', '=', 'draft']])->delete(true);

    $paymentReminder = PaymentReminder::create([
            'condo_id'      => $condo_id,
            'emission_date' => time()
        ])
        ->first();

    $payment_reminder_id = $paymentReminder['id'];

    PaymentReminder::id($payment_reminder_id)
        ->do('generate_reminder');

    $paymentReminder = PaymentReminder::id($payment_reminder_id)
        ->read(['payment_reminder_owners_ids'])
        ->first();

    if(!$paymentReminder || !count($paymentReminder['payment_reminder_owners_ids'])) {
        PaymentReminder::id($payment_reminder_id)->delete(true);
    }
}


$context
    ->httpResponse()
    ->send();
