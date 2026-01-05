<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use finance\accounting\Account;
use realestate\finance\accounting\AccountingEntryLine;
use finance\accounting\FiscalYear;

[$params, $providers] = eQual::announce([
    'description' => 'Advanced search for General Balance (Asset / Liability).',
    'params' => [

        /* Rendering fields */

        'asset_account_code' => [
            'type'     => 'string',
            'readonly' => true
        ],

        'asset_account_description' => [
            'type'     => 'string',
            'readonly' => true
        ],

        'asset_account_balance' => [
            'type'    => 'float',
            'usage'   => 'amount/money:4',
            'default' => 0.0,
            'readonly'=> true
        ],

        'liability_account_code' => [
            'type'     => 'string',
            'readonly' => true
        ],

        'liability_account_description' => [
            'type'     => 'string',
            'readonly' => true
        ],

        'liability_account_balance' => [
            'type'    => 'float',
            'usage'   => 'amount/money:4',
            'default' => 0.0,
            'readonly'=> true
        ],

        'domain' => [
            'type'              => 'array',
            'description'       => "Conditional domain.",
            'default'           => []
        ],

        /* Filters */

        'date_from' => [
            'type'    => 'date',
            'default' => null
        ],

        'date_to' => [
            'type'    => 'date',
            'default' => null
        ],

        'condo_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\Condominium',
            'default'        => function ($domain = []) {
                $condo_id = null;
                $origDomain = new Domain($domain);

                foreach ($origDomain->getClauses() as $clause) {
                    foreach ($clause->getConditions() as $condition) {
                        if ($condition->getOperand() === 'condo_id') {
                            $condo_id = $condition->getValue();
                            break 2;
                        }
                    }
                }
                return $condo_id;
            }
        ],

        'fiscal_year_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'finance\accounting\FiscalYear',
            'domain'         => ['condo_id', '=', 'object.condo_id'],
            'default'        => function ($condo_id = null) {
                $ids = FiscalYear::search([
                    ['status', '=', 'open'],
                    ['condo_id', '=', $condo_id],
                ], ['sort' => ['date_from' => 'desc']])->ids();

                if (!$ids) {
                    $ids = FiscalYear::search([
                        ['status', '=', 'preopen'],
                        ['condo_id', '=', $condo_id],
                    ], ['sort' => ['date_from' => 'asc']])->ids();
                }

                return $ids ? current($ids) : null;
            }
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;


if(!isset($params['condo_id'])) {
    throw new Exception('missing_condo_id', EQ_ERROR_MISSING_PARAM);
}

// build domain
$domain = new Domain($params['domain']);

$domain->addCondition(new DomainCondition('condo_id', '=', $params['condo_id']));

// Resolve date interval
$dateFrom = null;
$dateTo   = null;

if(!empty($params['fiscal_year_id'])) {
    $fiscalYear = FiscalYear::id($params['fiscal_year_id'])
        ->read(['date_from', 'date_to'])
        ->first();

    if($fiscalYear) {
        $dateFrom = $fiscalYear['date_from'];
        $dateTo   = $fiscalYear['date_to'];
    }
}

if(!empty($params['date_from']) && (!$dateFrom || $params['date_from'] > $dateFrom)) {
    $dateFrom = $params['date_from'];
}

if(!empty($params['date_to']) && (!$dateTo || $params['date_to'] < $dateTo)) {
    $dateTo = $params['date_to'];
}

if($dateFrom && $dateTo) {
    $domain->addCondition(new DomainCondition('entry_date', '>=', $dateFrom));
    $domain->addCondition(new DomainCondition('entry_date', '<=', $dateTo));
}

// Only validated entries
$domain->addCondition(new DomainCondition('status', '=', 'validated'));


// load Chart of Accounts of the condominium
$accounts = Account::search([
        ['condo_id', '=', $params['condo_id']]
    ])
    ->read(['code', 'parent_account_id', 'description', 'account_nature', 'is_control_account']);

foreach($accounts as $account_id => $account) {
    $map_accounts[$account_id] = [
        'id'                => $account_id,
        'code'              => (string) $account['code'],
        'parent_account_id' => $account['parent_account_id'] ?? null,
        'description'       => $account['description'],
        'account_nature'    => $account['account_nature'],
        'is_control_account'=> $account['is_control_account']
    ];
}

// retrieve storage accounts (collectors) and map with each account
$map_storage = [];

foreach($map_accounts as $account_id => $account) {
    $code = $account['code'];
    $parent_account_id = $account['parent_account_id'];

    // account is a control account (collector)
    if($account['is_control_account']) {
        /*
        // #todo
        $map_storage[$account_id] = $account_id;
        continue;
        */
    }

    // account is a level-3 account (or less)
    if(strlen($code) <= 3) {
        $map_storage[$account_id] = $account_id;
        continue;
    }
    $target_account_id = $account_id;

    // retrieve first level-3 parent
    while($parent_account_id) {
        if(!isset($map_accounts[$parent_account_id])) {
            break;
        }
        $parent_code = $map_accounts[$parent_account_id]['code'];
        $target_account_id = $parent_account_id;
        if(strlen($parent_code) <= 3) {
            break;
        }
        $parent_account_id = $map_accounts[$parent_account_id]['parent_account_id'];
    }

    $map_storage[$account_id] = $target_account_id;
}


// Retrieve accounting entry lines
$lines = AccountingEntryLine::search($domain->toArray())
    ->read([
        'account_id',
        'debit',
        'credit'
    ]);



$balances           = [];
$balances_asset     = [];
$balances_liability = [];

// 1) aggregate raw balances first
foreach($lines as $line) {

    $leaf_account_id = $line['account_id'];
    $storage_account_id = $map_storage[$leaf_account_id] ?? $leaf_account_id;

    if(!isset($map_accounts[$storage_account_id])) {
        // this shouldn't occur
        trigger_error("APP::unable to resolve account with id {$storage_account_id}", EQ_REPORT_ERROR);
        continue;
    }

    $storage = $map_accounts[$storage_account_id];
    $code    = $storage['code'];
    $nature  = $storage['account_nature'];

    $raw = round($line['debit'], 2) - round($line['credit'], 2);

    // aggregate raw balance per account
    if(!isset($balances[$code])) {
        $balances[$code] = [
            'account_id'        => $storage_account_id,
            'account_code'      => $code,
            'description'       => $storage['description'],
            'account_nature'    => $nature,
            'raw'               => 0.0
        ];
    }

    $balances[$code]['raw'] += $raw;
}


// 2) split balances into asset / liability
foreach($balances as $code => $balance) {

    $raw    = round($balance['raw'], 2);
    $nature = $balance['account_nature'];

    if($raw == 0.0) {
        continue;
    }

    // special cases: 410 / 440 (DUAL-SIDE ACCOUNTS)
    if(in_array(substr($code, 0, 3), ['410', '440'])) {

        if($raw > 0) {
            $balances_asset[$code] = [
                'account_id'   => $balance['account_id'],
                'account_code' => $code,
                'description'  => $balance['description'],
                'balance'      => $raw,
            ];
        }
        else {
            $balances_liability[$code] = [
                'account_id'   => $balance['account_id'],
                'account_code' => $code,
                'description'  => $balance['description'],
                'balance'      => abs($raw),
            ];
        }
    }
    // normal case
    else {
        // liability - increases by credit
        if($nature === 'liability') {
            $balances_liability[$code] = [
                'account_id'   => $balance['account_id'],
                'account_code' => $code,
                'description'  => $balance['description'],
                'balance'      => abs($raw),
            ];
        }
        // asset - increases by debit
        else {
            $balances_asset[$code] = [
                'account_id'   => $balance['account_id'],
                'account_code' => $code,
                'description'  => $balance['description'],
                'balance'      => $raw,
            ];
        }
    }
}


$result = [];

$assets = array_values($balances_asset);
$liabilities = array_values($balances_liability);

$max_lines = max(count($balances_asset), count($balances_liability));

for($i = 0; $i < $max_lines; $i++) {

    $asset     = $assets[$i]     ?? null;
    $liability = $liabilities[$i] ?? null;

    $result[] = [
        'id'                            => $i + 1,
        // ASSET (left column)
        'asset_account_code'            => $asset['account_code'] ?? null,
        'asset_account_description'     => $asset['description']  ?? null,
        'asset_account_balance'         => $asset['balance']      ?? null,

        // LIABILITY (right column)
        'liability_account_code'        => $liability['account_code'] ?? null,
        'liability_account_description' => $liability['description']  ?? null,
        'liability_account_balance'     => $liability['balance']      ?? null,
    ];
}

$context->httpResponse()
    ->body($result)
    ->send();
