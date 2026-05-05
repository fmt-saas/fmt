<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use equal\orm\Domain;
use equal\orm\DomainCondition;
use finance\accounting\Account;
use finance\accounting\AccountingEntryLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Search Accounting Entry Lines candidates to a matching based on a given Accounting Account (record).',
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\Account',
            'required'          => true
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => 'First date of the date range.',
            'default'           => null
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Last date of the date range.',
            'default'           => null
        ],
        'limit' => [
            'type'              => 'integer',
            'description'       => 'Last date of the date range.',
            'default'           => 100
        ]
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

$account = Account::id($params['id'])
    ->read([
        'condo_id'
    ])
    ->first();

if(!$account) {
    throw new Exception('unknown_accounting_account', EQ_ERROR_UNKNOWN_OBJECT);
}

$domain = new Domain([
        [
            ['condo_id', '=', $account['condo_id']],
            ['account_id', '=', $account['id']],
            ['matching_id', 'is', null]
        ],
        [
            ['condo_id', '=', $account['condo_id']],
            ['account_id', '=', $account['id']],
            ['matching_level', 'in', ['none', 'part']]
        ]
    ]);

if($params['date_from'] ?? null) {
    $domain->addCondition(new DomainCondition('entry_date', '>=', $params['date_from']));
}

if($params['date_to'] ?? null) {
    $domain->addCondition(new DomainCondition('entry_date', '<=', $params['date_to']));
}

$result = AccountingEntryLine::search($domain->toArray(), ['sort' => ['entry_date' => 'asc'], 'limit' => $params['limit']])
    ->read([
        'id', 'name', 'description', 'entry_date', 'entry_number', 'matching_id', 'funding_id', 'debit', 'credit'
    ])
    ->adapt('json')
    ->get(true);


$context->httpResponse()
        ->body($result)
        ->send();
