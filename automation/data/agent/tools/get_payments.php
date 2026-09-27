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
use realestate\sale\pay\Funding;
use realestate\sale\pay\Payment;

[$params, $providers] = eQual::announce([
    'description'   => 'Return payments received for an ownership during a fiscal year, defaulting to the current fiscal year.',
    'params'        => [
        'ownership_id' => [
            'type'              => 'many2one',
            'description'       => 'The ownership for which payments are requested.',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],
        'fiscal_year_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'description'       => 'Fiscal year for which payments are requested.',
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

$funding_ids = Funding::search([
        ['ownership_id', '=', $ownership['id']],
        ['funding_type', 'in', ['fund_request', 'expense_statement']]
    ])
    ->ids();

$payments = [];
if(count($funding_ids) > 0) {
    $payments = Payment::search([
            ['funding_id', 'in', $funding_ids],
            ['receipt_date', '>=', $fiscal_year['date_from']],
            ['receipt_date', '<', $fiscal_year['date_to'] + 86400]
        ])
        ->read([
            'id',
            'receipt_date',
            'amount',
            'description',
            'status',
            'payment_method',
            'payment_origin',
            'voucher_ref',
            'funding_id'
        ])
        ->adapt('json')
        ->get(true);
}

$payments_payload = [];
$total_amount = 0.0;
foreach($payments as $payment) {
    $amount = (float) ($payment['amount'] ?? 0);
    $total_amount += $amount;

    $payments_payload[] = [
        'id'             => $payment['id'],
        'receipt_date'   => isset($payment['receipt_date'])
            ? substr($payment['receipt_date'], 0, 10)
            : null,
        'amount'         => $amount,
        'description'    => $payment['description'] ?? '',
        'status'         => $payment['status'] ?? '',
        'payment_method' => $payment['payment_method'] ?? '',
        'payment_origin' => $payment['payment_origin'] ?? '',
        'voucher_ref'    => $payment['voucher_ref'] ?? '',
        'funding_id'     => $payment['funding_id'] ?? null
    ];
}

usort($payments_payload, function(array $first, array $second): int {
    $date_comparison = strcmp((string) $second['receipt_date'], (string) $first['receipt_date']);
    return $date_comparison !== 0
        ? $date_comparison
        : $second['id'] <=> $first['id'];
});

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

$context->httpResponse()
    ->status(200)
    ->body([
        'ownership_id' => $ownership['id'],
        'currency'     => $currency,
        'payments'     => $payments_payload,
        'count'        => count($payments_payload),
        'total_amount' => round($total_amount, 2)
    ])
    ->send();

/*
{
    "ownership_id": 0,
    "currency": "EUR",
    "payments": [
        {
            "id": 0,
            "receipt_date": "YYYY-MM-DD",
            "amount": 0.0,
            "description": "",
            "status": "",
            "payment_method": "",
            "payment_origin": "",
            "voucher_ref": "",
            "funding_id": 0
        }
    ],
    "count": 0,
    "total_amount": 0.0
}
*/
