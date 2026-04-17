<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\AccountBalanceChange;
use finance\accounting\FiscalYear;
use finance\accounting\OpeningBalance;
use finance\accounting\OpeningBalanceLine;
use realestate\finance\accounting\AccountingEntry;
use realestate\finance\accounting\AccountingEntryLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Rebuild cumulative account balances for a fiscal year.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifiers of the targeted Fiscal Year.',
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'required'          => true
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth', 'access']
]);

/**
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \fmt\access\AccessController        $access
 * @var \equal\php\Context                  $context
 */
['access' => $access, 'auth' => $auth, 'context' => $context] = $providers;

$fiscal_year_id = $params['id'];

$fiscalYear = FiscalYear::id($fiscal_year_id)
    ->read(['status', 'condo_id', 'date_from', 'date_to'])
    ->first();

if(!$fiscalYear) {
    throw new Exception('invalid_fiscal_year_id', EQ_ERROR_INVALID_PARAM);
}

if(!in_array($fiscalYear['status'], ['open', 'preopen'])) {
    trigger_error("APP::Account balances can only be rebuild on open fiscal year.", EQ_REPORT_ERROR);
    throw new Exception('invalid_fiscal_year_id', EQ_ERROR_INVALID_PARAM);
}

if(!$fiscalYear['condo_id']) {
    throw new Exception('missing_condo_id', EQ_ERROR_INVALID_PARAM);
}

if(!$fiscalYear['date_from']) {
    throw new Exception('missing_date_from', EQ_ERROR_INVALID_PARAM);
}

if(!$fiscalYear['date_to']) {
    throw new Exception('missing_date_to', EQ_ERROR_INVALID_PARAM);
}

$condo_id = $fiscalYear['condo_id'];
$date_from = $fiscalYear['date_from'];
$date_to = $fiscalYear['date_to'];

AccountBalanceChange::search([
        ['condo_id', '=', $condo_id],
        ['date', '>=', $date_from],
        ['date', '<=', $date_to]
    ])
    ->delete(true);

$openingBalance = OpeningBalance::search([
        ['condo_id','=', $condo_id],
        ['fiscal_year_id','=', $fiscal_year_id]
    ])
    ->first();

if($openingBalance) {
    // #memo - there might be accounting entries on the same day as the opening - do not create AccountBalanceChange for these (handled by Accounting Entries)
}

if(!$openingBalance) {
    // #todo - there should always be an opening balance
}

$accountingEntries = AccountingEntry::search([
            ['condo_id', '=', $condo_id],
            ['status', '=', 'validated'],
            ['entry_date', '>=', $date_from],
            ['entry_date', '<=', $date_to]
        ],
        ['sort' => ['entry_date' => 'asc', 'created' => 'asc', 'id' => 'asc']]
    );

$accounting_entries_ids = $accountingEntries->ids();

if(!empty($accounting_entries_ids)) {
    AccountingEntryLine::search([
            ['accounting_entry_id', 'in', $accounting_entries_ids],
            ['is_posted', '=', true]
        ])
        ->update([
            'is_posted' => false
        ]);

    $accountingEntries->do('update_balance_change');
}

$context->httpResponse()
        ->status(204)
        ->send();
