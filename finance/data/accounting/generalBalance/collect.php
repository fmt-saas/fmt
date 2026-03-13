<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use finance\accounting\AccountBalanceChange;
use realestate\finance\accounting\AccountingEntryLine;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for General Balance.',
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
            'usage'             => 'amount/money:4',
            'description'       => 'Amount to be debited on the account.',
            'default'           => 0.0
        ],

        'credit' => [
            'type'              => 'float',
            'usage'             => 'amount/money:4',
            'description'       => 'Amount to be credited on the account.',
            'default'           => 0.0
        ],

        'balance' => [
            'type'              => 'float',
            'usage'             => 'amount/money:4',
            'description'       => 'Amount to be credited on the account.',
            'default'           => 0.0
        ],

        'domain' => [
            'type'              => 'array',
            'description'       => "Conditional domain.",
            'default'           => []
        ],

        /* additional fields for filtering & rendering */

        'accounting_entry_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\finance\accounting\AccountingEntry',
            'description'       => "Accounting entry the line relates to.",
            'readonly'          => true
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "First date of the time interval.",
            'default'           => null
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Last date of the time interval.",
            'default'           => null
        ],

        'suppliers_only' => [
            'type'              => 'boolean',
            'description'       => "Show only entries relating to suppliers.",
            'default'           => false
        ],

        'ownerships_only' => [
            'type'              => 'boolean',
            'description'       => "Show only entries relating to suppliers.",
            'default'           => false
        ],

        'condo_id' => [
            'type'              => 'many2one',
            'description'       => "The condominium the fiscal year refers to.",
            'help'              => "When a fiscal year is not linked to a condominium, it relates to the organisation itself.",
            'foreign_object'    => 'realestate\property\Condominium',
            'default'           => function($domain = []) {
                // #memo - in some cases fiscal_year_id is provided in $domain and is not valid for Condominium schema
                $condo_id = null;

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
            'default'           => function($condo_id = null, $domain = []) {
                if(is_null($condo_id)) {
                    $origDomain = new Domain($domain);
                    foreach($origDomain->getClauses() as $clause) {
                        foreach($clause->getConditions() as $condition) {
                            if($condition->getOperand() === 'condo_id') {
                                $condo_id = $condition->getValue();
                                break 2;
                            }
                        }
                    }
                }
                if($condo_id) {
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
                return null;
            }
        ],

        'journal_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\Journal',
            'description'       => "The journal the accounting entry relates to.",
            'domain'            => ['condo_id', '=', 'object.condo_id'],
        ],

        'account_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\Account',
            'description'       => "The account the accounting entry line relates to.",
            'domain'            => ['condo_id', '=', 'object.condo_id'],
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/** @var \equal\php\Context $context **/
/** @var \equal\orm\ObjectManager $orm **/
['context' => $context, 'orm' => $orm] = $providers;


$map_accounts_ids = [];
$map_journals_ids = [];
$map_entries_ids = [];

//   Add conditions to the domain to consider advanced parameters
$domain = new Domain($params['domain']);

if(!isset($params['condo_id'])) {
    throw new Exception('missing_mandatory_condo', EQ_ERROR_MISSING_PARAM);
}

// index-1 condition on condominium
$domain->addCondition(new DomainCondition('condo_id', '=', $params['condo_id']));

if(isset($params['date_from'], $params['date_to'])) {
    $date_from = $params['date_from'];
    $date_to = $params['date_to'];
}
elseif(isset($params['fiscal_year_id']) && $params['fiscal_year_id'] > 0) {
    $fiscalYear = FiscalYear::id($params['fiscal_year_id'])
        ->read(['date_from', 'date_to'])
        ->first();

    $date_from = $fiscalYear['date_from'];
    $date_to = $fiscalYear['date_to'];
}
else {
    $fiscalYear = FiscalYear::search([
            ['status', '=', 'open'],
            ['condo_id', '=', $params['condo_id']],
        ], ['sort' => ['date_from' => 'desc'], 'limit' => 1])
        ->read(['date_from', 'date_to'])
        ->first();

    if(!$fiscalYear) {
        throw new Exception('missing_fiscal_year_or_dates', EQ_ERROR_MISSING_PARAM);
    }

    $date_from = $fiscalYear['date_from'];
    $date_to = $fiscalYear['date_to'];
}

// index-2 condition on account
if(isset($params['account_id']) && $params['account_id'] > 0) {
    $domain->addCondition(new DomainCondition('account_id', '=', $params['account_id']));
}
else {
    $changes = AccountBalanceChange::search([['condo_id', '=', $params['condo_id']], ['date', '>=', $date_from], ['date', '<=', $date_to]])->read(['account_id']);
    foreach($changes as $change) {
        $map_accounts_ids[$change['account_id']] = true;
    }
    $domain->addCondition(new DomainCondition('account_id', 'in', array_keys($map_accounts_ids)));
}

// index-3 add condition on dates
$domain->addCondition(new DomainCondition('entry_date', '>=', $date_from));
$domain->addCondition(new DomainCondition('entry_date', '<=', $date_to));

if($params['suppliers_only']) {
    $domain->addCondition(new DomainCondition('suppliership_id', '<>', null));
}
elseif($params['ownerships_only']) {
    $domain->addCondition(new DomainCondition('ownership_id', '<>', null));
}

if(isset($params['journal_id']) && $params['journal_id'] > 0) {
    $journal = Journal::id($params['journal_id'])->read(['journal_type'])->first();
    if($journal && $journal['journal_type'] !== 'LEDG') {
        $domain->addCondition(new DomainCondition('journal_id', '=', $params['journal_id']));
    }
}

// consider only validated entries
$domain->addCondition(new DomainCondition('status', '=', 'validated'));

/*
$result = AccountingEntryLine::search($domain->toArray())
    ->read([
        'condo_id' => ['name'],
        'account_id' => ['name', 'ownership_id' => ['name'], 'suppliership_id' => ['name']],
        'journal_id' => ['name', 'mnemo'],
        'accounting_entry_id' => ['name'],
        'entry_date',
        'description',
        'debit',
        'credit',
        'status'
    ])
    ->adapt('json')
    ->get(true);
*/

$accounting_entry_lines_ids = $orm->search(
        AccountingEntryLine::getType(),
        $domain->toArray(),
        [
            'account_id' => 'asc',
            'entry_date' => 'asc',
            'id' => 'asc'
        ]
    );

$lines = $orm->read(AccountingEntryLine::getType(), $accounting_entry_lines_ids,
    [
        'condo_id',
        'account_id',
        'journal_id',
        'accounting_entry_id',
        'entry_date',
        'description',
        'debit',
        'credit',
        'status'
    ]
);

foreach($lines as $line) {
    $map_accounts_ids[$line['account_id']] = true;
    $map_journals_ids[$line['journal_id']] = true;
    $map_entries_ids[$line['accounting_entry_id']] = true;
}

$accounts = $orm->read(
    'finance\\accounting\\Account',
    array_keys($map_accounts_ids),
    ['name']
);

$journals = $orm->read(
    'finance\\accounting\\Journal',
    array_keys($map_journals_ids),
    ['name','mnemo']
);

$entries = $orm->read(
    'realestate\\finance\\accounting\\AccountingEntry',
    array_keys($map_entries_ids),
    ['name']
);

$result = [];

foreach($lines as &$line) {
    // #memo - name of the target (ownership/suppliership) is already in the Account name
    $account_id = $line['account_id'];
    $journal_id = $line['journal_id'];
    $entry_id   = $line['accounting_entry_id'];

    $row = $line->toArray();

    if(isset($accounts[$account_id])) {
        $line['account_id'] = $accounts[$account_id]->toArray();
    }
    if(isset($journals[$journal_id])) {
        $line['journal_id'] = $journals[$journal_id]->toArray();
    }
    if(isset($entries[$entry_id])) {
        $line['accounting_entry_id'] = $entries[$entry_id]->toArray();
    }
    $line['balance'] = $line['debit'] - $line['credit'];

    $result[] = $row;
}

$context->httpResponse()
        ->body($result)
        ->send();
