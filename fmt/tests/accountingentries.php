<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\accounting\AccountingEntry;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\accounting\Account;
use finance\accounting\AccountingEntryLine;
use realestate\property\Condominium;
use realestate\property\PropertyLot;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

    '1101' => [
            'description'       => "Accounting entry with non-balanced lines.",
            'help'              => "Create an accounting entry, with 2 balanced lines. Entry balance test is expected to return true.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $condo = Condominium::create(['name' => 'test condo', 'managing_agent_id' => 1])->first(true);
                    $fiscalYear = FiscalYear::create([
                            'date_from' => strtotime(date('Y-01-01')),
                            'date_to'   => strtotime(date('Y-12-31')),
                            'condo_id'  => $condo['id']
                        ])
                        ->first(true);
                    return [$condo, $fiscalYear];
                },
            'act'               => function($params) use($providers) {
                    [$condo, $fiscalYear] = $params;

                    $accountingEntry = AccountingEntry::create([
                            'condo_id'          => $condo['id'],
                            'status'            => 'pending',
                            'journal_id'        => current(Journal::search(['code', '=', 'PUR'])->ids()),
                            'fiscal_year_id'    => $fiscalYear['id'],
                            'entry_date'        => time(),
                        ])
                        ->first(true);

                    AccountingEntryLine::create([
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'account_id'            => current(Account::search(['code', '=', '6100003'])->ids()),
                        'debit'                 => 1000.0,
                        'credit'                => 0.0,
                    ]);

                    AccountingEntryLine::create([
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'account_id'            => current(Account::search(['code', '=', '440'])->ids()),
                        'debit'                 => 0.0,
                        'credit'                => 1000.0,
                    ]);

                    return $accountingEntry['id'];
                },
            'assert'            => function($accounting_entry_id) use($providers) {
                    $accountingEntry = AccountingEntry::id($accounting_entry_id)->read(['is_balanced'])->first();
                    return $accountingEntry['is_balanced'];
                },
            'rollback'          => function() use($providers) {
                    ['orm' => $orm] = $providers;
                    $condo = Condominium::search(['name', '=', 'test condo'])->first();
                    $fiscal_years_ids = FiscalYear::search(['condo_id', '=', $condo['id']])->ids();
                    $orm->delete(FiscalYear::getType(), $fiscal_years_ids, true);
                    $orm->delete(Condominium::getType(), $condo['id'], true);
                }
        ],

        '1102' => [
            'description'       => "Accounting entry with non-balanced lines.",
            'help'              => "Create an accounting entry, with 2 non-balanced lines. Entry balance test is expected to return false.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {
                    $condo = Condominium::create(['name' => 'test condo', 'managing_agent_id' => 1])->first(true);
                    $fiscalYear = FiscalYear::create([
                            'date_from' => strtotime(date('Y-01-01')),
                            'date_to'   => strtotime(date('Y-12-31')),
                            'condo_id'  => $condo['id']
                        ])
                        ->first(true);
                    return [$condo, $fiscalYear];
                },
            'act'               => function($params) use($providers) {
                    [$condo, $fiscalYear] = $params;

                    $accountingEntry = AccountingEntry::create([
                            'condo_id'          => $condo['id'],
                            'status'            => 'pending',
                            'journal_id'        => current(Journal::search(['code', '=', 'PUR'])->ids()),
                            'fiscal_year_id'    => $fiscalYear['id'],
                            'entry_date'        => time(),
                        ])
                        ->first(true);

                    AccountingEntryLine::create([
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'account_id'            => current(Account::search(['code', '=', '6100003'])->ids()),
                        'debit'                 => 1000.0,
                        'credit'                => 0.0,
                    ]);

                    AccountingEntryLine::create([
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'account_id'            => current(Account::search(['code', '=', '440'])->ids()),
                        'debit'                 => 0.0,
                        'credit'                => 500.0,
                    ]);

                    return $accountingEntry['id'];
                },
            'assert'            => function($accounting_entry_id) use($providers) {
                    $accountingEntry = AccountingEntry::id($accounting_entry_id)->read(['is_balanced'])->first();
                    return !$accountingEntry['is_balanced'];
                },
            'rollback'          => function() use($providers) {
                    ['orm' => $orm] = $providers;
                    $condo = Condominium::search(['name', '=', 'test condo'])->first();
                    $fiscal_years_ids = FiscalYear::search(['condo_id', '=', $condo['id']])->ids();
                    $orm->delete(FiscalYear::getType(), $fiscal_years_ids, true);
                    $orm->delete(Condominium::getType(), $condo['id'], true);
                }
        ]
];