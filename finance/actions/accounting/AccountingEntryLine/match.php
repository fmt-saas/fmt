<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\Matching;
use realestate\finance\accounting\AccountingEntryLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Match a given series of accounting entry lines and attach the given Accounting Entry Line to it.',
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\AccountingEntryLine',
            'required'          => true
        ],
        'selected_ids' => [
            'description'       => 'List of unique identifiers of the objects to read.',
            'type'              => 'array',
            'required'          => true
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

$accountingEntryLine = AccountingEntryLine::id($params['id'])
    ->read(['account_id'])
    ->first();


if(!$accountingEntryLine) {
    throw new Exception('unknown_bank_statement_line', EQ_ERROR_UNKNOWN_OBJECT);
}

$accountingEntryLines = AccountingEntryLine::ids($params['selected_ids'])
    ->read([
        'matching_id'
    ]);

if($accountingEntryLines->count() <= 0) {
    throw new Exception('invalid_empty_selection', EQ_ERROR_INVALID_PARAM);
}

$map_matchings_ids = [];

foreach($accountingEntryLines as $accounting_entry_line_id => $entryLine) {
    if(!$entryLine['matching_id']) {
        continue;
    }
    $map_matchings_ids[$entryLine['matching_id']] = true;
}

$events = $orm->disableEvents();

// 1) create a new Matching

$matching = Matching::create([
        'condo_id'              => $bankStatementLine['condo_id'],
        'accounting_account_id' => $bankStatementLine['accounting_account_id']
    ])
    ->first();

$accountingEntryLines->update(['matching_id' => $matching['id']]);
AccountingEntryLine::id($accountingEntryLine['id'])->update(['matching_id' => $matching['id']]);

$orm->enableEvents($events);

// 2) refresh lines & impacted matchings

if(count($map_matchings_ids)) {
    // #memo - this will cascade-update accounting entry lines still linked to theses matchings
    Matching::ids(array_keys($map_matchings_ids))->do('refresh_matching_level');
}

// This should result in a non-balanced state, but consistent with given selection (the accounting entry line from the bank statement line is still missing)
$accountingEntryLines->do('refresh_matching_level');
AccountingEntryLine::id($accountingEntryLine['id'])->do('refresh_matching_level');

$context->httpResponse()
        ->body($result)
        ->send();
