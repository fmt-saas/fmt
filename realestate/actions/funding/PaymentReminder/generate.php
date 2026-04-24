<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\sale\pay\Funding;
use realestate\funding\PaymentReminder;

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

$overdueFundings = Funding::search([
        ['status', 'in', ['pending', 'debit_balance']],
        ['funding_type', 'in', ['fund_request', 'expense_statement']],
        ['due_date', '<=', time()]
    ])
    ->read(['condo_id', 'ownership_id', 'due_date', 'due_amount'])
    ->get();

$overdue_fundings_ids = array_keys($overdueFundings);

$existing_reminders = PaymentReminder::search(['funding_id', 'in', $overdue_fundings_ids])
    ->read(['funding_id'])
    ->get(true);

$reminded_fundings_ids = array_unique(array_column($existing_reminders, 'funding_id'));

$result = [];
foreach($overdueFundings as $id => $funding) {
    if(in_array($funding['id'], $reminded_fundings_ids)) {
        // reminder already exists
        continue;
    }

    $reminder = PaymentReminder::create([
            'condo_id'      => $funding['condo_id'],
            'ownership_id'  => $funding['ownership_id'],
            'funding_id'    => $id,
            'due_date'      => $funding['due_date'],
            'due_amount'    => $funding['due_amount']
        ])
        ->read(['id'])
        ->first();

    $result[] = $reminder['id'];
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
