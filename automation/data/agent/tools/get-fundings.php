<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalYear;
use identity\User;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => 'Return fund requests and expense statements issued during a fiscal year, plus any unpaid ones, defaulting to the current fiscal year.',
    'params'        => [
        'ownership_id' => [
            'type'              => 'many2one',
            'description'       => 'The ownership for which fundings are requested.',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],
        'fiscal_year_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'description'       => 'Fiscal year for which fundings are requested.',
            'help'              => 'If not provided, falls back on current fiscal year.'
        ],
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
    ->adapt('json')
    ->first(true);

if(!$fiscal_year) {
    throw new Exception('unknown_fiscal_year', EQ_ERROR_UNKNOWN_OBJECT);
}

if($fiscal_year['condo_id'] !== $condominium['id']) {
    throw new Exception('fiscal_year_ownership_mismatch', EQ_ERROR_INVALID_PARAM);
}

if(!$fiscal_year['date_from'] || !$fiscal_year['date_to']) {
    throw new Exception('invalid_fiscal_year_dates', EQ_ERROR_INVALID_CONFIG);
}

$fundings = Funding::search([
        ['ownership_id', '=', $ownership['id']],
        ['funding_type', 'in', ['fund_request', 'expense_statement']]
    ])
    ->read([
        'id',
        'name',
        'description',
        'funding_type',
        'issue_date',
        'due_date',
        'due_amount',
        'paid_amount',
        'remaining_amount',
        'is_paid',
        'is_cancelled',
        'status',
        'payment_reference'
    ])
    ->adapt('json')
    ->get(true);

$fundings_payload = [];
foreach($fundings as $funding) {
    $issue_date = $funding['issue_date'] ?? null;
    $is_within_fiscal_year = $issue_date
        && $issue_date >= $fiscal_year['date_from']
        && $issue_date <= $fiscal_year['date_to'];

    if(!$is_within_fiscal_year && ($funding['is_paid'] ?? false)) {
        continue;
    }

    $fundings_payload[] = [
        'id'                => $funding['id'],
        'name'              => $funding['name'] ?? '',
        'description'       => $funding['description'] ?? '',
        'funding_type'      => $funding['funding_type'],
        'issue_date'        => $funding['issue_date'] ?? null,
        'due_date'          => $funding['due_date'] ?? null,
        'due_amount'        => $funding['due_amount'] ?? 0,
        'paid_amount'       => $funding['paid_amount'] ?? 0,
        'remaining_amount'  => $funding['remaining_amount'] ?? 0,
        'is_paid'           => $funding['is_paid'] ?? false,
        'is_cancelled'      => $funding['is_cancelled'] ?? false,
        'status'            => $funding['status'] ?? '',
        'payment_reference' => $funding['payment_reference'] ?? ''
    ];
}

usort($fundings_payload, function(array $first, array $second): int {
    $date_comparison = strcmp((string) $second['issue_date'], (string) $first['issue_date']);
    return $date_comparison !== 0
        ? $date_comparison
        : $second['id'] <=> $first['id'];
});

$context->httpResponse()
    ->status(200)
    ->body([
        'ownership_id' => $ownership['id'],
        'fundings'      => $fundings_payload,
        'count'         => count($fundings_payload)
    ])
    ->send();

/*
{
    "ownership_id": 0,
    "fundings": [
        {
            "id": 0,
            "name": "",
            "description": "",
            "funding_type": "",
            "issue_date": "YYYY-MM-DD",
            "due_date": "YYYY-MM-DD",
            "due_amount": 0.0,
            "paid_amount": 0.0,
            "remaining_amount": 0.0,
            "is_paid": false,
            "is_cancelled": false,
            "status": "",
            "payment_reference": ""
        }
    ],
    "count": 0
}
*/
