<?php

use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;

$fiscalYears = FiscalYear::search([
        ['status', '<>', 'closed']
    ])
    ->read(['date_from', 'date_to']);

$cross_calendar_fiscal_years_ids = [];

foreach($fiscalYears as $fiscal_year_id => $fiscalYear) {
    if(
        !$fiscalYear['date_from']
        || !$fiscalYear['date_to']
        || date('Y', $fiscalYear['date_from']) === date('Y', $fiscalYear['date_to'])
    ) {
        continue;
    }

    $cross_calendar_fiscal_years_ids[] = $fiscal_year_id;
}

if(count($cross_calendar_fiscal_years_ids)) {
    $open_fiscal_periods_ids = FiscalPeriod::search([
            ['fiscal_year_id', 'in', $cross_calendar_fiscal_years_ids],
            ['status', '=', 'open']
        ])
        ->ids();

    if(count($open_fiscal_periods_ids)) {
        FiscalPeriod::ids($open_fiscal_periods_ids)
            ->do('renumber_accounting_entries');
    }
}
