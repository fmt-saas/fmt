<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\Account;
use finance\accounting\AccountingEntry;
use finance\accounting\AccountingEntryLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Balance Lines: returns a collection of Reports according to extra parameters.',
    'extends'       => 'core_model_collect',
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
    throw new Exception('unknown_accounting_entry', EQ_ERROR_UNKNOWN_OBJECT);
}

AccountingEntryLine::search([
        ['condo_id', '=', $account['condo_id']],
        ['account_id', '=', $account['id']],
        ['matching_level', 'in', ['none', 'part']]
    ])
    ->read([]);



$context->httpResponse()
        ->body($result)
        ->send();
