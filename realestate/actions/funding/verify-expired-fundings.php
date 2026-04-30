<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalYear;
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

$map_ownership_balances = [];
$now = strtotime('today');

foreach($condominiums as $condo_id => $condominium) {

    // remove any existing non-sent PaymentReminder
    PaymentReminder::search([['condo_id', '=', $condo_id], ['status', '<>', 'sent']])->delete(true);

    $fiscalYear = FiscalYear::search([
            ['condo_id', '=', $condo_id],
            ['date_from', '<=', $now],
        ], ['sort' => ['date_from' => 'desc'], 'limit' => 1])
        ->read(['date_from'])
        ->first();

    if(!$fiscalYear) {
        continue;
    }

    $date_from = $fiscalYear['date_from'] ?? $now;

    $overdueFundings = Funding::search([
            ['status', 'in', ['pending', 'debit_balance']],
            ['condo_id', '=', $condo_id],
            ['funding_type', 'in', ['fund_request', 'expense_statement', 'misc_operation']],
            ['due_amount', '>', 0],
            ['due_date', '<=', $now]
        ])
        ->read(['condo_id', 'ownership_id', 'due_date', 'due_amount']);

    if($overdueFundings->count() > 0) {
        // we'll need to know afterwards how many lines have been created
        $created_lines = 0;

        $map_payment_reminder_ownership = [];

        $paymentReminder = PaymentReminder::create([
                'condo_id'          => $condo_id,
                'emission_date'     => time()
            ])
            ->first();

        foreach($overdueFundings as $funding_id => $funding) {

            $previousReminderOwnerLines = PaymentReminderOwnerLine::search([
                    ['payment_reminder_status', '=', 'sent'],
                    ['funding_id', '=', $funding_id],
                ])
                ->read(['due_date']);

            // ignore fundings for which a non-expired reminder has been sent
            foreach($previousReminderOwnerLines as $previousReminderOwnerLine) {
                if($previousReminderOwnerLine['due_date'] <= $now) {
                    continue 2;
                }
            }

            $ownership_id = $funding['ownership_id'];

            if(!isset($map_ownership_balances[$ownership_id])) {
                $map_ownership_balances[$ownership_id] = 0;

                $data = \eQual::run('get', 'finance_accounting_ownerAccountStatement_collect', [
                    'ownership_id'      => $ownership_id,
                    'date_from'         => $date_from,
                    'date_to'           => $now
                ]);

                if(count($data)) {
                    $map_ownership_balances[$ownership_id] = end($data)['balance'] ?? 0;
                }
            }

            $current_balance = $map_ownership_balances[$ownership_id];

            if(!isset($map_payment_reminder_ownership[$ownership_id])) {
                $map_payment_reminder_ownership[$ownership_id] = PaymentReminderOwner::create([
                        'condo_id'              => $condo_id,
                        'ownership_id'          => $ownership_id,
                        'payment_reminder_id'   => $paymentReminder['id'],
                        'due_balance'           => $current_balance
                    ])
                    ->first();
            }

            $paymentReminderOwner = $map_payment_reminder_ownership[$ownership_id];

            $reminder_level = $previousReminderOwnerLines->count() + 1;

            PaymentReminderOwnerLine::create([
                    'condo_id'                      => $condo_id,
                    'ownership_id'                  => $ownership_id,
                    'funding_id'                    => $funding_id,
                    'payment_reminder_id'           => $paymentReminder['id'],
                    'payment_reminder_owner_id'     => $paymentReminderOwner['id'],
                    'due_amount'                    => $funding['due_amount'],
                    'reminder_level'                => $reminder_level
                ]);

            ++$created_lines;
        }

        // remove reminder if empty
        if($created_lines <= 0) {
            PaymentReminder::id($paymentReminder['id'])->delete(true);
        }
    }
}


$context
    ->httpResponse()
    ->send();
