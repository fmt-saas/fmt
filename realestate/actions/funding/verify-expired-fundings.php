<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\PaymentReminder;
use realestate\funding\PaymentReminderOwner;
use realestate\funding\PaymentReminderOwnerLine;
use realestate\property\Condominium;
use realestate\sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => "Generate reminders of overdue fundings of funding requests and expense statements.",
    'params'        => [
    ],
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

    $overdueFundings = Funding::search([
            ['status', 'in', ['pending', 'debit_balance']],
            ['condo_id', '=', $condo_id],
            ['funding_type', 'in', ['fund_request', 'expense_statement']],
            ['due_date', '<=', time()]
        ])
        ->read(['condo_id', 'ownership_id', 'due_date', 'due_amount']);

    if($overdueFundings->count() > 0) {

        $map_payment_reminder_ownership = [];

        $paymentReminder = PaymentReminder::create([
                'condo_id'          => $condo_id,
                'emission_date'     => time()
            ])
            ->first();

        foreach($overdueFundings as $funding_id => $funding) {

            $ownership_id = $funding['ownership_id'];

            if(!isset($map_payment_reminder_ownership[$ownership_id])) {
                $map_payment_reminder_ownership[$ownership_id] = PaymentReminderOwner::create([
                        'condo_id'              => $condo_id,
                        'ownership_id'          => $ownership_id,
                        'payment_reminder_id'   => $paymentReminder
                    ]);
            }

            $paymentReminderOwner = $map_payment_reminder_ownership[$ownership_id];

            PaymentReminderOwnerLine::create([
                    'condo_id'                      => $condo_id,
                    'ownership_id'                  => $ownership_id,
                    'funding_id'                    => $funding_id,
                    'payment_reminder_id'           => $paymentReminder['id'],
                    'payment_reminder_owner_id'     => $paymentReminderOwner['id'],
                    'days_overdue'                  => floor((strtotime('today') - $funding['due_date']) / 86400),
                    'due_amount'                    => $funding['due_amount'],
                ]);

        }
    }
}


$context
    ->httpResponse()
    ->send();
