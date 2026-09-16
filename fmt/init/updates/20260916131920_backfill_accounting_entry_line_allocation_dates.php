<?php

use finance\accounting\AccountingEntryLine;

$accounting_entry_line_ids =
    AccountingEntryLine::search([
        [ ['allocation_date_from', 'is', null] ],
        [ ['allocation_date_to', 'is', null] ]
    ])
    ->ids();

$accountingEntryLines = AccountingEntryLine::ids($accounting_entry_line_ids)
    ->read([
        'allocation_date_from',
        'allocation_date_to',
        'accounting_entry_id' => [
            'fiscal_period_id' => ['date_from', 'date_to']
        ]
    ]);

$accounting_entry_line_ids_by_date_from = [];
$accounting_entry_line_ids_by_date_to = [];

foreach($accountingEntryLines as $accounting_entry_line_id => $accountingEntryLine) {
    $fiscalPeriod = $accountingEntryLine['accounting_entry_id']['fiscal_period_id'] ?? null;

    if(
        is_null($accountingEntryLine['allocation_date_from'])
        && isset($fiscalPeriod['date_from'])
    ) {
        $accounting_entry_line_ids_by_date_from[$fiscalPeriod['date_from']][] = $accounting_entry_line_id;
    }

    if(
        is_null($accountingEntryLine['allocation_date_to'])
        && isset($fiscalPeriod['date_to'])
    ) {
        $accounting_entry_line_ids_by_date_to[$fiscalPeriod['date_to']][] = $accounting_entry_line_id;
    }
}

foreach($accounting_entry_line_ids_by_date_from as $date_from => $accounting_entry_line_ids) {
    AccountingEntryLine::ids($accounting_entry_line_ids)
        ->write(['allocation_date_from' => $date_from]);
}

foreach($accounting_entry_line_ids_by_date_to as $date_to => $accounting_entry_line_ids) {
    AccountingEntryLine::ids($accounting_entry_line_ids)
        ->write(['allocation_date_to' => $date_to]);
}
