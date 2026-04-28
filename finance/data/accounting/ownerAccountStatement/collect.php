<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use finance\accounting\Account;
use finance\accounting\AccountBalanceChange;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\accounting\OpeningBalance;
use finance\accounting\OpeningBalanceLine;
use realestate\finance\accounting\AccountingEntryLine;
use realestate\ownership\Ownership;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for General Ledger.',
    // #memo - this controller is named `collect` but is provides data from its own logic, not directly from the model
    // 'extends'       => 'core_model_collect',
    'params'        => [


        /* fields from AccountingEntryLine */

        'entry_date' => [
            'type'              => 'date',
            'usage'             => 'date/plain',
            'description'       => 'The date on which the transaction is recorded in the accounting system and affects the fiscal period.',
            'readonly'          => true
        ],

        'description' => [
            'type'              => 'string',
            'description'       => 'Explanation or internal notes about the operation.'
        ],

        'debit' => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => 'Amount to be debited on the account.',
            'default'           => 0.0
        ],

        'credit' => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => 'Amount to be credited on the account.',
            'default'           => 0.0

        ],

        'balance' => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => 'Balance of the line (at given date).',
            'default'           => 0.0
        ],

        /* additional fields for filtering & rendering */

        'ownership_id' => [
            'type'              => 'many2one',
            'description'       => "The ownership that the owner refers to.",
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "First date of the time interval.",
            'required'          => true
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Last date of the time interval.",
            'required'          => true
        ],

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;


$date_from = $params['date_from'];
$date_to = $params['date_to'] ?? null;

$ownership = Ownership::id($params['ownership_id'])
    ->read(['condo_id'])
    ->first();

if(!$ownership) {
    throw new \Exception('unknown_ownership', EQ_ERROR_UNKNOWN_OBJECT);
}

$accounts_ids = Account::search(['ownership_id', '=', $params['ownership_id']])->ids();

if(empty($accounts_ids)) {
    throw new \Exception('ownership_accounts_missing', EQ_ERROR_INVALID_CONFIG);
}

$opening_balance = 0;
$map_opening_balances = [];

$fiscalYear = FiscalYear::search([
        ['condo_id', '=', $ownership['condo_id']],
        ['date_from', '<=', $date_from],
        ['date_to', '>=', $date_from]
    ], ['limit' => 1])
    ->read(['opening_balance_id'])
    ->first();

$opening_balance_id = $fiscalYear['opening_balance_id'] ?? null;

if(!$opening_balance_id) {
    // find first available opening balance (last validated for given condominium)
    $openingBalance = OpeningBalance::search([
                ['condo_id', '=', $ownership['condo_id']],
                ['status', '=', 'validated']
            ],
            [
                'sort'  => ['created' => 'desc'],
                'limit' => 1
            ]
        )
        ->first();

    $opening_balance_id = $openingBalance['id'] ?? null;
}

if($opening_balance_id) {
    $openingLines = OpeningBalanceLine::search([
            ['condo_id', '=', $ownership['condo_id']],
            ['balance_id', '=', $fiscalYear['opening_balance_id']],
            ['account_id', 'in', $accounts_ids]
        ])
        ->read(['account_id', 'debit', 'credit']);

    foreach($openingLines as $openingLine) {
        $map_opening_balances[$openingLine['account_id']] =
            $openingLine['debit'] - $openingLine['credit'];
    }
}

foreach($accounts_ids as $account_id) {
    $snapshot = AccountBalanceChange::search([
            ['account_id', '=', $account_id],
            ['date', '<', $date_from]
        ], ['sort' => ['date' => 'desc'], 'limit' => 1])
        ->read(['debit_balance','credit_balance'])
        ->first();

    if($snapshot) {
        $opening_balance += $snapshot['debit_balance'] - $snapshot['credit_balance'];
    }
    elseif(isset($map_opening_balances[$account_id])) {
        $opening_balance += $map_opening_balances[$account_id];
    }
}

// Add conditions to the domain to consider advanced parameters

$domain = new Domain();
$domain->addCondition(new DomainCondition('condo_id', '=', $ownership['condo_id']));
$domain->addCondition(new DomainCondition('ownership_id', '=', $params['ownership_id']));
$domain->addCondition(new DomainCondition('entry_date', '>=', $date_from));
$domain->addCondition(new DomainCondition('entry_date', '<=', $date_to));
// consider only validated entries
$domain->addCondition(new DomainCondition('status', '=', 'validated'));

$accounting_entry_lines = AccountingEntryLine::search(
        $domain->toArray(),
        ['sort' => ['entry_date' => 'asc', 'id' => 'asc']]
    )
    ->read([
        'entry_date',
        'description',
        'debit',
        'credit'
    ])
    ->adapt('json')
    ->get(true);

$balance = $opening_balance;

$result = [];

/* Owner Account Statement line */

$result[] = [
    'entry_date'  => date('c', $date_from),
    'description' => 'Owner Account Statement',
    'debit'       => 0.0,
    'credit'      => 0.0,
    'balance'     => $balance
];


/* transaction lines */

foreach($accounting_entry_lines as $line) {
    $debit = (float) $line['debit'];
    $credit = (float) $line['credit'];

    $balance += $debit;
    $balance -= $credit;

    $result[] = [
        'entry_date'  => $line['entry_date'],
        'description' => $line['description'],
        'debit'       => $debit,
        'credit'      => $credit,
        'balance'     => $balance
    ];
}


$context->httpResponse()
        ->body($result)
        ->send();
