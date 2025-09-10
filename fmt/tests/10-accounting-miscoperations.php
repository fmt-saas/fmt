<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\MiscOperation;
use finance\accounting\MiscOperationLine;
use finance\bank\BankStatement;
use finance\bank\BankStatementImport;
use finance\bank\CondominiumBankAccount;
use realestate\finance\accounting\MoneyTransfer;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

    '1101' => [
            'description'       => "Create Misc Operation.",
            'help'              => "Create an accounting entry, with 2 balanced lines. Entry balance test is expected to return true.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {

                    $bankAccount = CondominiumBankAccount::search([
                            ['condo_id', '=', 1],
                            ['bank_account_type', '=', 'bank_savings']
                        ])
                        ->read(['accounting_account_id'])
                        ->first();

                    $miscOperation = MiscOperation::create([
                            'condo_id'          => 1,
                            'description'       => 'reprise de compte epargne',
                            'posting_date'      => strtotime('2024-01-01T00:00:00Z'),
                            'journal_id'        => 11,
                            'operation_type'    => 'misc'
                        ])
                        ->first();

                    MiscOperationLine::create([
                            'condo_id'          => 1,
                            'misc_operation_id' => $miscOperation['id'],
                            'account_id'        => 676,
                            'credit'            => 5000
                        ]);

                    MiscOperationLine::create([
                            'condo_id'          => 1,
                            'misc_operation_id' => $miscOperation['id'],
                            'account_id'        => $bankAccount['accounting_account_id'],
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
                    $bankAccount = CondominiumBankAccount::search([['condo_id', '=', 1], ['bank_account_type', '=', 'bank_savings']])
                        ->read(['current_balance'])
                        ->first();

                    return $bankAccount && $bankAccount['current_balance'] == 5000;
                },
            'rollback'          => function() use($providers) {
                }
        ],

    '1102' => [
            'description'       => "Create Money transfer.",
            'help'              => "Create an accounting entry, with 2 balanced lines. Entry balance test is expected to return true.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $currentAccount = CondominiumBankAccount::search([['condo_id', '=', 1], ['bank_account_type', '=', 'bank_current']])
                        ->first();

                    $savingsAccount = CondominiumBankAccount::search([['condo_id', '=', 1], ['bank_account_type', '=', 'bank_savings']])
                        ->first();

                    $moneyTransfer = MoneyTransfer::create([
                            'condo_id'                      => 1,
                            'description'                   => 'Money Transfer',
                            'posting_date'                  => time(),
                            'amount'                        => 5000,
                            'bank_account_id'               => $savingsAccount['id'],
                            'counterpart_bank_account_id'   => $currentAccount['id']
                        ])
                        ->first();

                    return $moneyTransfer;
                },
            'act'               => function($moneyTransfer) use($providers) {
                    MoneyTransfer::id($moneyTransfer['id'])
                        ->transition('publish')
                        ->transition('send');
                },
            'assert'            => function() use($providers) {
                    $bankAccount = CondominiumBankAccount::search([['condo_id', '=', 1], ['bank_account_type', '=', 'bank_savings']])
                        ->read(['available_balance'])
                        ->first();

                    return $bankAccount && $bankAccount['available_balance'] == 0.0;
                },
            'rollback'          => function() use($providers) {
                }
        ],
    '1103' => [
            'description'       => "Import Bank statement with Money transfer.",
            'help'              => "Load a bank statement, validate it, and account for the validation of the Money Transfer.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_isabel_demo.xlsx');
                    BankStatementImport::create()
                        ->update(['name' => 'Bank statement import'])
                        ->update(['data' => $data]);
                },
            'act'               => function() use($providers) {
                    BankStatement::search(['condo_id', '=', 1], ['sort' => ['date' => 'desc'], 'limit' => 2])
                        ->do('attempt_reconcile')
                        ->transition('post');
                },
            'assert'            => function() use($providers) {
                    $moneyTransfer = MoneyTransfer::search(['condo_id', '=', 1])->read(['status'])->first();
                    return $moneyTransfer['status'] == 'posted';
                },
            'rollback'          => function() use($providers) {
                }
        ]

];