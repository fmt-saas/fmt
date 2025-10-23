<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use finance\accounting\Account;
use finance\accounting\AccountingEntry;
use finance\accounting\AccountingEntryLine;
use finance\accounting\ClosingBalance;
use finance\accounting\FiscalYear;
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Balance Lines: returns a collection of Reports according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'finance\accounting\BalanceLine',
            'help'              => 'This value should be relayed from view and be either CurrentBalanceLine or ClosingBalanceLine.'
        ],

        'nolimit' => [
            'description'   => 'Explicit request for ignoring limit and return all matching objects.',
            'help'          => 'When activated start and limit parameters are ignored.',
            'type'          => 'boolean',
            'default'       => true
        ],

        'has_fiscal_year' => [
            'type'              => 'boolean',
            'label'             => 'Fiscal Year',
            'description'       => "Toggle to specify fiscal year or use arbitrary dates.",
            'default'           => true
        ],

        'condo_id' => [
            'type'              => 'many2one',
            'description'       => "The condominium the fiscal year refers to.",
            'help'              => "When a fiscal year is not linked to a condominium, it relates to the organisation itself.",
            'foreign_object'    => 'realestate\property\Condominium',
            'default'           => function($domain=[]) {
                // #memo - in some cases fiscal_year_id is provided in $domain and is not valid for Condominium schema
                $condo_id = null;

                // $user_id = $this->am->userId();
                // Setting::get_value('fmt', 'organization', 'user.condo_id', null, ['user_id' => $user_id]);

                $origDomain = new Domain($domain);
                foreach($origDomain->getClauses() as $clause) {
                    foreach($clause->getConditions() as $condition) {
                        if($condition->getOperand() === 'condo_id') {
                            $condo_id = $condition->getValue();
                            break 2;
                        }
                    }
                }
                return $condo_id;
            }
        ],

        'fiscal_year_id' => [
            'type'              => 'many2one',
            'description'       => "The fiscal year the balance refers to.",
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'domain'            => ['condo_id', '=', 'object.condo_id'],
            'default'           => function($condo_id=null) {
                $fiscal_year_ids = FiscalYear::search([
                        ['status', '=', 'open'],
                        ['condo_id', '=', $condo_id],
                    ],  ['sort' => ['date_from' => 'desc']])
                    ->ids();
                if(count($fiscal_year_ids) <= 0) {
                    $fiscal_year_ids = FiscalYear::search([
                            ['status', '=', 'preopen'],
                            ['condo_id', '=', $condo_id],
                        ],  ['sort' => ['date_from' => 'asc']])
                        ->ids();
                }
                return count($fiscal_year_ids) ? current($fiscal_year_ids) : null;
            }
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => 'First date of the time range.',
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => 'Last date of the time range.'
        ],

        'use_collectors' => [
            'type'              => 'boolean',
            'label'             => 'Collectors',
            'description'       => "Toggle to group accounts into collectors.",
            'default'           => true
        ],

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);
/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;


$result = [];

// flag for marking the date interval as a specific fiscal year
$is_fiscal_year = false;
if(isset($params['fiscal_year_id']) && $params['fiscal_year_id'] > 0) {
    $fiscalYear = FiscalYear::id($params['fiscal_year_id'])->read(['date_from', 'date_to'])->first();
    $date_from = $fiscalYear['date_from'];
    $date_to = $fiscalYear['date_to'];
    $is_fiscal_year = true;
}
elseif(isset($params['date_from'], $params['date_to'])) {
    $date_from = $params['date_from'];
    $date_to = $params['date_to'];
}
else {
    // missing mandatory param
    throw new Exception('missing_fiscal_year_or_dates', EQ_ERROR_MISSING_PARAM);
}

if($date_from <= 0 || $date_to <= 0) {
    // invalid param
    throw new Exception('invalid_dates', EQ_ERROR_INVALID_PARAM);
}

$map_accounts_ids = [];
$totals = [];
$accounting_lines = [];

if(!$is_fiscal_year) {
    // retrieve most recent previous fiscal year
    $prevFiscalYear = FiscalYear::search([
            ['condo_id', '=', $params['condo_id']],
            ['date_to', '<', $date_from],
            ['status', 'in', ['closed', 'preclosed']]
        ], ['sort' => ['date_to' => 'desc'], 'limit' => 1])
        ->read(['id', 'date_to'])
        ->first();

    if($prevFiscalYear) {
        // read ClosingBalance + ClosingBalanceLine
        $closingBalance = ClosingBalance::search(['fiscal_year_id', '=', $prevFiscalYear['id']])
            ->read(['balance_lines_ids' => ['account_id', 'debit', 'credit']])
            ->first();

        // 'account_id', 'debit', 'credit'
        if($closingBalance) {
            foreach($closingBalance['balance_lines_ids'] as $balance_line_id => $balanceLine) {
                $map_accounts_ids[$balanceLine['account_id']] = true;
                $accounting_lines[] = [
                    'account_id'    => $balanceLine['account_id'],
                    'debit'         => $balanceLine['debit'],
                    'credit'        => $balanceLine['credit']
                ];
            }
        }

        // update date_from to day following last day of $prevFiscalYear
        $date_from = strtotime(date('Y-m-d 00:00:00', $prevFiscalYear['date_to']) . ' +1 day');
    }

}


// Add conditions to the domain to consider advanced parameters

// #memo - condo_id is expected to be in the domain
$domain = $params['domain'];

$domainEntries = new Domain($domain);
$domainEntries->addCondition(new DomainCondition('entry_date', '>=', $date_from));
$domainEntries->addCondition(new DomainCondition('entry_date', '<=', $date_to));


$entries_ids = AccountingEntry::search($domainEntries->toArray())->ids();

$accountingEntryLines = AccountingEntryLine::search(['accounting_entry_id', 'in', $entries_ids])
    ->read(['account_id', 'debit', 'credit']);

// append involved accounts
foreach($accountingEntryLines as $accountingEntryLine) {
    $map_accounts_ids[$line['account_id']] = true;
    $accounting_lines[] = [
        'account_id'    => $accountingEntryLine['account_id'],
        'debit'         => $accountingEntryLine['debit'],
        'credit'        => $accountingEntryLine['credit']
    ];
}

if($params['use_collectors']) {
    // fetch all accounts at once
    // #memo - parent_account_id targets the first collector (bottom-up) amongst the parents (based on account code hierarchy)
    $accounts = Account::ids(array_keys($map_accounts_ids))->read(['parent_account_id'])->get();
}

// reset accounts map
$map_accounts_ids = [];

// compute totals
foreach($accounting_lines as $line) {
    $account_id = $line['account_id'];

    if($params['use_collectors']) {
        if(isset($accounts[$account_id]) && isset($accounts[$account_id]['parent_account_id'])) {
            $account_id = $accounts[$account_id]['parent_account_id'];
        }
    }

    $map_accounts_ids[$account_id] = true;

    $debit  = $line['debit'];
    $credit = $line['credit'];
    $delta  = $debit - $credit;

    $totals[$account_id]['debit']  = ($totals[$account_id]['debit'] ?? 0) + $debit;
    $totals[$account_id]['credit'] = ($totals[$account_id]['credit'] ?? 0) + $credit;

    $totals[$account_id]['debit_balance']  = ($totals[$account_id]['debit_balance'] ?? 0) + max($delta, 0.0);
    $totals[$account_id]['credit_balance'] = ($totals[$account_id]['credit_balance'] ?? 0) + max(-$delta, 0.0);
}

// fetch all (final) accounts at once
$accounts = Account::ids(array_keys($map_accounts_ids))->read(['id', 'name', 'code'])->get();

// generate virtual fields
$i = 1;
foreach($totals as $account_id => &$line) {

    $debit_balance  = $line['debit_balance']  ?? 0.0;
    $credit_balance = $line['credit_balance'] ?? 0.0;
    $delta = abs($debit_balance - $credit_balance);

    // ignore balanced accounts
    if ($delta < 0.01) {
        unset($totals[$account_id]);
        continue;
    }

    $line['id'] = $i++;
    $line['fiscal_year_id'] = null;
    $line['account_code'] = $accounts[$account_id]['code'];
    $line['account_id'] = [
        'id'    => $account_id,
        'name'  => $accounts[$account_id]['name']
    ];
}

$result = array_values($totals);

usort($result, function($a, $b) {
    return strcmp($a['account_code'], $b['account_code']);
});

$context->httpResponse()
        ->body($result)
        ->send();
