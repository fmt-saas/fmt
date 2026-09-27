<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalYear;
use fmt\setting\Setting;
use identity\User;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;

[$params, $providers] = eQual::announce([
    'description'   => 'Return the account history for an ownership during a fiscal year, defaulting to the current fiscal year.',
    'params'        => [
        'ownership_id' => [
            'type'              => 'many2one',
            'description'       => 'The ownership for which the account history is requested.',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],
        'fiscal_year_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'description'       => 'Fiscal year for which the account history is requested.',
            'help'              => 'If not provided, falls back on the current fiscal year.'
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

$fiscal_year_id = $params['fiscal_year_id']
    ?? ($condominium['current_fiscal_year_id']['id'] ?? null);

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
    throw new Exception('fiscal_year_ownership_mismatch', EQ_ERROR_INVALID_PARAM);
}

if(!$fiscal_year['date_from'] || !$fiscal_year['date_to']) {
    throw new Exception('invalid_fiscal_year_dates', EQ_ERROR_INVALID_CONFIG);
}

$data = eQual::run('get', 'finance_accounting_ownerAccountStatement_collect', [
    'ownership_id' => $ownership['id'],
    'date_from'    => $fiscal_year['date_from'],
    'date_to'      => $fiscal_year['date_to']
]);

$entries = [];
foreach($data as $line) {
    $entries[] = [
        'entry_date'  => isset($line['entry_date']) ? substr($line['entry_date'], 0, 10) : null,
        'description' => $line['description'] ?? '',
        'debit'       => (float) ($line['debit'] ?? 0),
        'credit'      => (float) ($line['credit'] ?? 0),
        'balance'     => (float) ($line['balance'] ?? 0)
    ];
}

$opening_balance = count($entries) > 0
    ? $entries[0]['balance']
    : 0.0;
$closing_balance = count($entries) > 0
    ? $entries[count($entries) - 1]['balance']
    : 0.0;

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

$format_date = static function($value): ?string {
    if(!$value) {
        return null;
    }

    return is_numeric($value)
        ? date('Y-m-d', (int) $value)
        : substr((string) $value, 0, 10);
};

$context->httpResponse()
    ->status(200)
    ->body([
        'ownership_id'    => $ownership['id'],
        'date_from'       => $format_date($fiscal_year['date_from']),
        'date_to'         => $format_date($fiscal_year['date_to']),
        'currency'        => $currency,
        'opening_balance' => $opening_balance,
        'closing_balance' => $closing_balance,
        'entries'         => $entries
    ])
    ->send();

/*
{
    "ownership_id": 0,
    "date_from": "YYYY-MM-DD",
    "date_to": "YYYY-MM-DD",
    "currency": "EUR",
    "opening_balance": 0.0,
    "closing_balance": 0.0,
    "entries": [
        {
            "entry_date": "YYYY-MM-DD",
            "description": "",
            "debit": 0.0,
            "credit": 0.0,
            "balance": 0.0
        }
    ]
}
*/
