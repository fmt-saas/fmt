<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\Account;
use realestate\sale\pay\Funding;
use realestate\sale\pay\FundingAllocation;
use realestate\sale\pay\Payment;

$fundingTestDescription = 'TEST Funding allocation refresh';

$cleanupFundingTest = function() use($fundingTestDescription) {
    $funding_ids = Funding::search(['description', '=', $fundingTestDescription])->ids();

    if(count($funding_ids)) {
        FundingAllocation::search(['funding_id', 'in', $funding_ids])->delete(true);
        Funding::ids($funding_ids)->delete(true);
    }
};

$tests = [
    '1001' => [
        'description' => 'Check FundingAllocation and Payment model scopes.',
        'return'      => ['array'],
        'act'         => function() {
            return [
                'funding_allocation_scope' => FundingAllocation::getModelScope(),
                'payment_scope'            => Payment::getModelScope()
            ];
        },
        'assert'      => function($result) {
            return $result['funding_allocation_scope'] === null
                && $result['payment_scope'] === Payment::class;
        }
    ],

    '1002' => [
        'description' => 'Recompute Funding amounts and status from allocations and payments.',
        'help'        => 'A FundingAllocation and a posted Payment must be counted, while a pro forma Payment must be ignored.',
        'return'      => ['array'],
        'arrange'     => function() use($cleanupFundingTest, $fundingTestDescription) {
            $cleanupFundingTest();

            $account = Account::search([
                    ['condo_id', '<>', null],
                    ['is_control_account', '=', false]
                ])
                ->read(['id', 'condo_id'])
                ->first(true);

            if(!$account) {
                throw new Exception('No condominium accounting account is available for the Funding test.');
            }

            $funding = Funding::create([
                    'condo_id'              => $account['condo_id'],
                    'description'           => $fundingTestDescription,
                    'funding_type'          => 'misc_operation',
                    'due_amount'            => 100.0,
                    'accounting_account_id' => $account['id'],
                    'due_date'              => time()
                ])
                ->read(['id'])
                ->first(true);

            $fundingAllocation = FundingAllocation::create([
                    'condo_id'    => $account['condo_id'],
                    'description' => $fundingTestDescription,
                    'amount'      => 30.0,
                    'funding_id'  => $funding['id']
                ])
                ->read(['id'])
                ->first(true);

            Payment::create([
                'condo_id'    => $account['condo_id'],
                'description' => $fundingTestDescription,
                'amount'      => 70.0,
                'status'      => 'posted',
                'funding_id'  => $funding['id']
            ]);

            Payment::create([
                'condo_id'    => $account['condo_id'],
                'description' => $fundingTestDescription,
                'amount'      => 50.0,
                'status'      => 'proforma',
                'funding_id'  => $funding['id']
            ]);

            return [
                'funding_id'            => $funding['id'],
                'funding_allocation_id' => $fundingAllocation['id']
            ];
        },
        'act'         => function($fixture) {
            $funding_id = $fixture['funding_id'];

            Funding::id($funding_id)->do('refresh_status');
            $after_assignment_refresh = Funding::id($funding_id)
                ->read([
                    'paid_amount',
                    'remaining_amount',
                    'is_paid',
                    'status',
                    'funding_allocations_ids',
                    'payments_ids'
                ])
                ->first(true);

            FundingAllocation::id($fixture['funding_allocation_id'])->update(['amount' => 20.0]);
            Funding::id($funding_id)->do('refresh_status');
            $after_allocation_refresh = Funding::id($funding_id)
                ->read(['paid_amount', 'remaining_amount', 'is_paid', 'status'])
                ->first(true);

            return [
                'funding_allocation_count'  => count($after_assignment_refresh['funding_allocations_ids']),
                'payment_count'             => count($after_assignment_refresh['payments_ids']),
                'after_assignment_refresh'  => [
                    'paid_amount'      => $after_assignment_refresh['paid_amount'],
                    'remaining_amount' => $after_assignment_refresh['remaining_amount'],
                    'is_paid'          => $after_assignment_refresh['is_paid'],
                    'status'           => $after_assignment_refresh['status']
                ],
                'after_allocation_refresh'  => [
                    'paid_amount'      => $after_allocation_refresh['paid_amount'],
                    'remaining_amount' => $after_allocation_refresh['remaining_amount'],
                    'is_paid'          => $after_allocation_refresh['is_paid'],
                    'status'           => $after_allocation_refresh['status']
                ]
            ];
        },
        'assert'      => function($result) {
            $after_assignment_refresh = $result['after_assignment_refresh'];
            $after_allocation_refresh = $result['after_allocation_refresh'];

            return $result['funding_allocation_count'] === 3
                && $result['payment_count'] === 2
                && abs($after_assignment_refresh['paid_amount'] - 100.0) < 0.01
                && abs($after_assignment_refresh['remaining_amount']) < 0.01
                && $after_assignment_refresh['is_paid'] === true
                && $after_assignment_refresh['status'] === 'balanced'
                && abs($after_allocation_refresh['paid_amount'] - 90.0) < 0.01
                && abs($after_allocation_refresh['remaining_amount'] - 10.0) < 0.01
                && $after_allocation_refresh['is_paid'] === false
                && $after_allocation_refresh['status'] === 'debit_balance';
        },
        'rollback'    => function() use($cleanupFundingTest) {
            $cleanupFundingTest();
        }
    ]
];
