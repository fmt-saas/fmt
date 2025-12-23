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

/* -------------------------------------------------------------------------
 * Domain construction
 * ------------------------------------------------------------------------- */

$domain = new Domain($params['domain']);


// Condo filter
if(!isset($params['condo_id'])) {
    throw new Exception('missing_condo_id', EQ_ERROR_MISSING_PARAM);
}

if(!empty($params['condo_id'])) {
    $domain->addCondition(new DomainCondition('condo_id', '=', $params['condo_id']));
}

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
    ->read(['code', 'parent_account_id', 'description', 'account_nature']);

foreach($accounts as $account_id => $account) {
    $map_accounts[$account_id] = [
        'id'                => $account_id,
        'code'              => (string) $account['code'],
        'parent_account_id' => $account['parent_account_id'] ?? null,
        'description'       => $account['description'],
        'account_nature'    => $account['account_nature'],
    ];
}


$map_storage = [];

foreach($map_accounts as $account_id => $account) {

    $code      = $account['code'];
    $parent_id = $account['parent_account_id'];

    // Case 1: this account is a level-3 account (exactly 3 digits)
    if(strlen($code) <= 3) {
        $map_storage[$account_id] = $account_id;
        continue;
    }
    $target_account_id = $account_id;

    // Case 2: parent exists and parent is level-3
    while($parent_id) {
        $target_account_id = $parent_id;
        if(!isset($map_accounts[$parent_id])) {
            break;
        }
        $parent_code = $map_accounts[$parent_id]['code'];
        if(strlen($parent_code) <= 3) {
            break;
        }
        $parent_id = $map_accounts[$parent_id]['parent_account_id'];
    }

    $map_storage[$account_id] = $target_account_id;
}


/* -------------------------------------------------------------------------
 * Retrieve accounting entry lines
 * ------------------------------------------------------------------------- */

$lines = AccountingEntryLine::search($domain->toArray())
    ->read([
        'account_id',
        'debit',
        'credit'
    ]);



$balances_asset     = [];
$balances_liability = [];

foreach($lines as $line) {

    $leaf_account_id = $line['account_id'];
    $storage_account_id = $map_storage[$leaf_account_id] ?? null;

    if(!$storage_account_id || !isset($map_accounts[$storage_account_id])) {
        continue;
    }

    $storage = $map_accounts[$storage_account_id];
    $code    = $storage['code'];
    $nature  = $storage['account_nature'];

    $raw = (float) $line['debit'] - (float) $line['credit'];

    $asset_delta     = 0.0;
    $liability_delta = 0.0;

    /* ---------------------------------------------------------
     * SPECIAL CASES: 410 / 440 (DUAL-SIDE ACCOUNTS)
     * --------------------------------------------------------- */

    if(strpos($code, '410') === 0) {
        // Clients
        $asset_delta     = -abs($raw);
        $liability_delta =  abs($raw);
    }
    elseif(strpos($code, '440') === 0) {
        // Suppliers
        $asset_delta     =  abs($raw);
        $liability_delta = -abs($raw);
    }
    else {
        // NORMAL CASES
        if($nature === 'asset') {
            $asset_delta = $raw;
        }
        else { // liability
            $liability_delta = -$raw;
        }
    }

    /* ---------------------------------------------------------
     * AGGREGATE
     * --------------------------------------------------------- */

    if($asset_delta != 0.0) {
        if(!isset($balances_asset[$code])) {
            $balances_asset[$code] = [
                'account_id'   => $storage_account_id,
                'account_code' => $code,
                'description'  => $storage['description'],
                'balance'      => 0.0,
            ];
        }
        $balances_asset[$code]['balance'] += $asset_delta;
    }

    if ($liability_delta != 0.0) {
        if (!isset($balances_liability[$code])) {
            $balances_liability[$code] = [
                'account_id'   => $storage_account_id,
                'account_code' => $code,
                'description'  => $storage['description'],
                'balance'      => 0.0,
            ];
        }
        $balances_liability[$code]['balance'] += $liability_delta;
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
        // ASSET (left column)
        'asset_account_code'        => $asset['account_code'] ?? null,
        'asset_account_description' => $asset['description']  ?? null,
        'asset_account_balance'     => $asset['balance']      ?? null,

        // LIABILITY (right column)
        'liability_account_code'        => $liability['account_code'] ?? null,
        'liability_account_description' => $liability['description']  ?? null,
        'liability_account_balance'     => $liability['balance']      ?? null,
    ];
}

$context->httpResponse()
    ->body($result)
    ->send();
