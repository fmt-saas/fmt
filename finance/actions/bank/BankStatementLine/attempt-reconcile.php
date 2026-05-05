<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\AccountingEntryLine;
use finance\accounting\Matching;
use finance\bank\BankStatementLine;
use realestate\sale\pay\Funding;
use realestate\sale\pay\Payment;

[$params, $providers] = eQual::announce([
    'description'   => 'Match a given series of accounting entry lines and match them with given pending Bank Statement Line.',
    'help'          => "This controller creates a partial Matching (`matching_level` = `part`) and attach it to the Bank Statement Line.
        Upon validation of the BankStatementLine, the generated AccountingEntryLines will be linked to that Matching.
        Note: When linking a bank statement line, its Accounting Entry Line do exist yet.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\bank\BankStatementLine',
            'required'          => true
        ],
        'selected_ids' => [
            'description'       => 'List of unique identifiers of Accounting Entry lines to be linked to the statement line.',
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


// #todo - check consistency (has been done in UI)

$bankStatementLine = BankStatementLine::id($params['id'])
    ->read([
        'condo_id',
        'status',
        'amount',
        'communication',
        'date',
        'accounting_account_id',
        'bank_statement_id' => ['bank_account_id']
    ])
    ->first();

if(!$bankStatementLine) {
    throw new Exception('unknown_bank_statement_line', EQ_ERROR_UNKNOWN_OBJECT);
}

if($bankStatementLine['status'] !== 'pending') {
    throw new Exception('posted_bank_statement_line', EQ_ERROR_INVALID_PARAM);
}

if(!isset($bankStatementLine['condo_id'])) {
    throw new Exception('missing_bank_statement_line_condo', EQ_ERROR_INVALID_PARAM);
}

if(!isset($bankStatementLine['accounting_account_id'])) {
    throw new Exception('missing_bank_statement_line_account', EQ_ERROR_INVALID_PARAM);
}


$context->httpResponse()
        ->status(204)
        ->send();
