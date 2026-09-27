<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalYear;
use finance\bank\BankStatementLine;
use fmt\setting\Setting;
use identity\User;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;

[$params, $providers] = eQual::announce([
    'description'   => 'Return the account summary for an ownership during its condominium current fiscal year.',
    'params'        => [
        'ownership_id' => [
            'type'              => 'many2one',
            'description'       => 'The ownership for which the account summary is requested.',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\auth\AuthenticationManager $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$user = User::id($auth->userId())
    ->read(['identity_id'])
    ->first();

if(!$user) {
    throw new Exception('unknown_user', EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$user['identity_id']) {
    throw new Exception('missing_user_identity', EQ_ERROR_INVALID_CONFIG);
}

$ownership = Ownership::id($params['ownership_id'])
    ->read([
        'id',
        'condo_id' => [
            'id',
            'current_fiscal_year_id' => ['id']
        ]
    ])
    ->first();

if(!$ownership) {
    throw new Exception('unknown_ownership', EQ_ERROR_UNKNOWN_OBJECT);
}

$owner = Owner::search([
        ['identity_id', '=', $user['identity_id']],
        ['ownership_id', '=', $ownership['id']]
    ])
    ->read(['id'])
    ->first();

if(!$owner) {
    throw new Exception('ownership_access_denied', EQ_ERROR_NOT_ALLOWED);
}

$condominium = $ownership['condo_id'] ?? null;
if(!$condominium) {
    throw new Exception('missing_ownership_condominium', EQ_ERROR_INVALID_CONFIG);
}

$fiscal_year_id = $condominium['current_fiscal_year_id']['id'] ?? null;
if(!$fiscal_year_id) {
    throw new Exception('missing_current_fiscal_year', EQ_ERROR_INVALID_CONFIG);
}

$fiscal_year = FiscalYear::id($fiscal_year_id)
    ->read(['id', 'condo_id', 'date_from', 'date_to'])
    ->first();

if(!$fiscal_year) {
    throw new Exception('unknown_fiscal_year', EQ_ERROR_UNKNOWN_OBJECT);
}

if($fiscal_year['condo_id'] !== $condominium['id']) {
    throw new Exception('fiscal_year_ownership_mismatch', EQ_ERROR_INVALID_CONFIG);
}

if(!$fiscal_year['date_from'] || !$fiscal_year['date_to']) {
    throw new Exception('invalid_fiscal_year_dates', EQ_ERROR_INVALID_CONFIG);
}

$data = eQual::run('get', 'finance_accounting_ownerAccountStatement_collect', [
    'ownership_id' => $ownership['id'],
    'date_from'    => $fiscal_year['date_from'],
    'date_to'      => $fiscal_year['date_to']
]);

$total_debit = 0.0;
$total_credit = 0.0;

foreach($data as $line) {
    $total_debit += (float) ($line['debit'] ?? 0);
    $total_credit += (float) ($line['credit'] ?? 0);
}

$closing_balance = 0.0;
if(count($data)) {
    $closing_balance = (float) (end($data)['balance'] ?? 0);
}

$bank_statement_lines = BankStatementLine::search([
        ['ownership_id', '=', $ownership['id']],
        ['date', '>=', $fiscal_year['date_from']],
        ['date', '<=', $fiscal_year['date_to']],
        ['amount', '>', 0]
    ])
    ->read(['amount']);

$paid_amount = 0.0;
foreach($bank_statement_lines as $bank_statement_line) {
    $paid_amount += (float) ($bank_statement_line['amount'] ?? 0);
}

$total_debit = round($total_debit, 2);
$total_credit = round($total_credit, 2);
$closing_balance = round($closing_balance, 2);
$paid_amount = round($paid_amount, 2);

$balance_type = 'balanced';
if($closing_balance > 0) {
    $balance_type = 'debit';
}
elseif($closing_balance < 0) {
    $balance_type = 'credit';
}

$currency = Setting::get_value(
    'core',
    'locale',
    'currency.code',
    'EUR',
    [
        [
            'condo_id'     => $condominium['id'],
            'ownership_id' => $ownership['id']
        ],
        [
            'condo_id'     => $condominium['id'],
            'ownership_id' => null
        ],
        []
    ]
);

$as_of = is_numeric($fiscal_year['date_to'])
    ? date('Y-m-d', (int) $fiscal_year['date_to'])
    : substr((string) $fiscal_year['date_to'], 0, 10);

$context->httpResponse()
    ->status(200)
    ->body([
        'ownership_id'     => $ownership['id'],
        'as_of'            => $as_of,
        'currency'         => $currency,
        'total_debit'      => $total_debit,
        'total_credit'     => $total_credit,
        'balance'          => $closing_balance,
        'balance_type'     => $balance_type,
        'paid_amount'      => $paid_amount,
        'remaining_amount' => $closing_balance
    ])
    ->send();

/*
{
    "ownership_id": 0,
    "as_of": "YYYY-MM-DD",
    "currency": "EUR",
    "total_debit": 0.0,
    "total_credit": 0.0,
    "balance": 0.0,
    "balance_type": "debit|credit|balanced",
    "paid_amount": 0.0,
    "remaining_amount": 0.0
}
*/
