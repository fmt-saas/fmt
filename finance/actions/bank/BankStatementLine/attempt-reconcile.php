<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\bank\BankStatementLine;
use realestate\sale\pay\Funding;

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

        'condo_id' => [
            'type'              => 'many2one',
            'description'       => "The condominium the fiscal year refers to.",
            'help'              => "When a fiscal year is not linked to a condominium, it relates to the organisation itself.",
            'foreign_object'    => 'realestate\property\Condominium',
            'required'          => true
        ],

        'accounting_account_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\Account',
            'description'       => 'Accounting account the statement line relates to.',
            'required'          => true
        ],

        'has_manual_funding' => [
            'type'              => 'boolean',
            'description'       => 'Allow manual selection of a specific Funding.',
            'default'           => false
        ],

        'funding_id' => [
            'type'              => 'many2one',
            'label'             => 'Funding',
            'description'       => 'The fiscal year the balance refers to.',
            'foreign_object'    => 'realestate\sale\pay\Funding',
            'visible'           => ['has_manual_funding', '=', true],
            'domain'            => [
                ['condo_id', '=', 'object.condo_id'],
                ['accounting_account_id', '=', 'object.accounting_account_id'],
                ['is_cancelled', '=', false],
                ['status', '<>', 'balanced'],
                ['funding_type', '<>', 'due_balance']
            ],
            'order'             => 'due_date',
            'sort'              => 'asc',
            'required'          => false,
            'help'              => "If given, the reconcile attempt is made exclusively on that Funding. Required only if `has_manual_funding` is set to true."
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


$bankStatementLine = BankStatementLine::id($params['id'])
    ->read([
        'condo_id',
        'status',
        'accounting_account_id'
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

if($params['funding_id']) {
    $funding = Funding::id($params['funding_id'])
        ->read(['id'])
        ->first();
}

if($funding ?? null) {
    BankStatementLine::id($params['id'])->do('reconcile_with_fundings', ['funding_ids' => [$funding['id']]]);
}
else {
    BankStatementLine::id($params['id'])->do('attempt_reconcile');
}

$context->httpResponse()
        ->status(204)
        ->send();
