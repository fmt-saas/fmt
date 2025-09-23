<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\Account;
use finance\accounting\AccountingEntryLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Balance Lines: returns a collection of Reports according to extra parameters.',
    'help'          => 'When linking a bank statement line, the accounting entry line does exist yet.',
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\Account',
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

$result = AccountingEntryLine::search([
        [
            ['condo_id', '=', $account['condo_id']],
            ['account_id', '=', $account['id']],
            ['matching_level', 'is', null]
        ],
        [
            ['condo_id', '=', $account['condo_id']],
            ['account_id', '=', $account['id']],
            ['matching_level', 'in', ['none', 'part']]
        ]
    ])
    ->read([
        'id', 'name', 'entry_date', 'entry_number', 'matching_id', 'funding_id', 'debit', 'credit'
    ])
    ->adapt('json')
    ->get(true);


$context->httpResponse()
        ->body($result)
        ->send();
