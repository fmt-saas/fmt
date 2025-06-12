<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\accounting\MiscOperation;
use finance\accounting\MiscOperationLine;
use finance\bank\CondominiumBankAccount;
use realestate\finance\accounting\MoneyTransfer;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

    '1101' => [
            'description'       => "Create Misc Operation.",
            'help'              => "Create an accounting entry, with 2 balanced lines. Entry balance test is expected to return true.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $miscOperation = MiscOperation::create([
                            'condo_id'          => 1,
                            'description'       => 'reprise de compte epargne',
                            'posting_date'      => time(),
                            'fiscal_year_id'    => 1,
                            'fiscal_period_id'  => 1,
                            'journal_id'        => 5,
                            'operation_type'    => 'misc'
                        ])
                        ->first();

                    MiscOperationLine::create([
                            'condo_id'          => 1,
                            'misc_operation_id' => $miscOperation['id'],
                            'account_id'        => 676,
                            'journal_id'        => 11,
                            'credit'            => 5000
                        ]);

                    MiscOperationLine::create([
                            'condo_id'          => 1,
                            'misc_operation_id' => $miscOperation['id'],
                            'account_id'        => 468,
                            'journal_id'        => 11,
                            'debit'             => 5000
                        ]);

                    return $miscOperation;
                },
            'act'               => function($miscOperation) use($providers) {
                    MiscOperation::id($miscOperation['id'])
                        ->transition('publish')
                        ->transition('post');
                },
            'assert'            => function() use($providers) {
                    $bankAccount = CondominiumBankAccount::search([['condo_id', '=', '1'], ['bank_account_type', '=', 'bank_savings']])
                        ->read(['available_balance'])
                        ->first();
                    return $bankAccount && $bankAccount['available_balance'] == 5000;
                },
            'rollback'          => function() use($providers) {
                }
        ],

    '1102' => [
            'description'       => "Create Money transfer.",
            'help'              => "Create an accounting entry, with 2 balanced lines. Entry balance test is expected to return true.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $currentAccount = CondominiumBankAccount::search([['condo_id', '=', '1'], ['bank_account_type', '=', 'bank_current']])
                        ->read(['available_balance'])
                        ->first();

                    $savingsAccount = CondominiumBankAccount::search([['condo_id', '=', '1'], ['bank_account_type', '=', 'bank_savings']])
                        ->read(['available_balance'])
                        ->first();

                    $moneyTransfer = MoneyTransfer::create([
                            'condo_id'          => 1,
                            'description'       => 'Money Transfer',
                            'posting_date'      => time(),
                            'fiscal_year_id'    => 3,
                            'fiscal_period_id'  => 10,
                            'amount'            => 5000,
                            'bank_account_id'   => $savingsAccount['id'],
                            'counterpart_bank_account_id' => $currentAccount['id']
                        ])
                        ->first();

                    return $moneyTransfer;
                },
            'act'               => function($moneyTransfer) use($providers) {
                    MoneyTransfer::id($moneyTransfer['id'])
                        ->transition('publish')
                        ->transition('post');
                },
            'assert'            => function() use($providers) {
                    $bankAccount = CondominiumBankAccount::search([['condo_id', '=', '1'], ['bank_account_type', '=', 'bank_savings']])
                        ->read(['available_balance'])
                        ->first();

                    return $bankAccount && $bankAccount['available_balance'] == 0.0;
                },
            'rollback'          => function() use($providers) {
                }
        ]

];