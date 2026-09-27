<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\FiscalPeriod;
use identity\User;
use realestate\funding\ExpenseStatement;
use realestate\funding\ExpenseStatementOwner;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;

[$params, $providers] = eQual::announce([
    'description'   => 'Return the expenses assigned to an ownership for an issued expense statement, defaulting to the latest issued period.',
    'params'        => [
        'ownership_id' => [
            'type'              => 'many2one',
            'description'       => 'The ownership for which the expense statement is requested.',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],
        'fiscal_period_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalPeriod',
            'description'       => 'Fiscal period for which the expense statement is requested.',
            'help'              => 'If not provided, falls back on the latest period for which an expense statement was issued.'
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

$buildOwnerExpenses = function(array $owner): array {
    $expenses = [];
    $is_first_lot = true;

    foreach($owner['property_lots'] as $lot) {
        foreach($lot['expenses'] as $expense) {
            $expense_type = $expense['name'];

            if(!isset($expenses[$expense_type])) {
                $expenses[$expense_type] = [
                    'name'           => $expense['name'],
                    'apportionments' => [],
                    'lines'          => $expense['lines'] ?? []
                ];
            }
            elseif(count($expense['lines'] ?? [])) {
                $expenses[$expense_type]['lines'] = array_merge(
                    $expenses[$expense_type]['lines'],
                    $expense['lines']
                );
            }

            foreach($expense['apportionments'] as $apportionment) {
                $apportionment_id = $apportionment['id'];

                if(!isset($expenses[$expense_type]['apportionments'][$apportionment_id])) {
                    $expenses[$expense_type]['apportionments'][$apportionment_id] = [
                        'id'           => $apportionment['id'],
                        'name'         => $apportionment['name'],
                        'code'         => $apportionment['code'] ?? '',
                        'total_shares' => $apportionment['total_shares'],
                        'shares'       => $apportionment['shares'],
                        'accounts'     => [],
                        'total_amount' => 0.0,
                        'total_vat'    => 0.0,
                        'total_owner'  => 0.0,
                        'total_tenant' => 0.0
                    ];
                }
                else {
                    $expenses[$expense_type]['apportionments'][$apportionment_id]['shares']
                        += $apportionment['shares'];
                }

                foreach($apportionment['accounts'] as $account) {
                    $account_code = $account['code'];

                    if(!isset(
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code]
                    )) {
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code] = [
                            'id'           => $account['id'],
                            'name'         => $account['name'],
                            'code'         => $account['code'],
                            'total_amount' => $account['total_amount'],
                            'owner'        => $account['owner'],
                            'tenant'       => $account['tenant'],
                            'vat'          => $account['vat']
                        ];
                    }
                    else {
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code]['owner']
                            += $account['owner'];
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code]['tenant']
                            += $account['tenant'];
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code]['vat']
                            += $account['vat'];
                    }

                    if($is_first_lot) {
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['total_amount']
                            += $account['total_amount'];
                    }

                    $expenses[$expense_type]['apportionments'][$apportionment_id]['total_vat']
                        += $account['vat'];
                    $expenses[$expense_type]['apportionments'][$apportionment_id]['total_owner']
                        += $account['owner'];
                    $expenses[$expense_type]['apportionments'][$apportionment_id]['total_tenant']
                        += $account['tenant'];
                }
            }
        }

        $is_first_lot = false;
    }

    foreach($expenses as &$expense) {
        uasort(
            $expense['apportionments'],
            static function($first, $second) {
                return strcmp($first['code'] ?? '', $second['code'] ?? '')
                    ?: strcmp($first['name'] ?? '', $second['name'] ?? '')
                    ?: (($first['id'] ?? 0) <=> ($second['id'] ?? 0));
            }
        );
    }
    unset($expense);

    return $expenses;
};

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
    ->read(['id', 'condo_id'])
    ->first();

if(!$ownership) {
    throw new Exception('unknown_ownership', EQ_ERROR_UNKNOWN_OBJECT);
}

$owner_record = Owner::search([
        ['identity_id', '=', $user['identity_id']],
        ['ownership_id', '=', $ownership['id']]
    ])
    ->read(['id'])
    ->first();

if(!$owner_record) {
    throw new Exception('ownership_access_denied', EQ_ERROR_NOT_ALLOWED);
}

if(!$ownership['condo_id']) {
    throw new Exception('missing_ownership_condominium', EQ_ERROR_INVALID_CONFIG);
}

if(isset($params['fiscal_period_id'])) {
    $fiscal_period = FiscalPeriod::id($params['fiscal_period_id'])
        ->read(['id', 'condo_id'])
        ->first();

    if(!$fiscal_period) {
        throw new Exception('unknown_fiscal_period', EQ_ERROR_UNKNOWN_OBJECT);
    }

    if($fiscal_period['condo_id'] !== $ownership['condo_id']) {
        throw new Exception('fiscal_period_ownership_mismatch', EQ_ERROR_INVALID_PARAM);
    }
}

$map_statement_owner_ids = [];
$statementOwners = ExpenseStatementOwner::search([
        ['ownership_id', '=', $ownership['id']]
    ])
    ->read(['id', 'expense_statement_id']);

foreach($statementOwners as $statementOwner) {
    if($statementOwner['expense_statement_id']) {
        $map_statement_owner_ids[$statementOwner['expense_statement_id']] = $statementOwner['id'];
    }
}

$statement = null;
if(count($map_statement_owner_ids) > 0) {
    $statement_domain = [
        ['id', 'in', array_keys($map_statement_owner_ids)],
        ['condo_id', '=', $ownership['condo_id']],
        ['invoice_type', '=', 'expense_statement'],
        ['status', '=', 'posted']
    ];

    if(isset($params['fiscal_period_id'])) {
        $statement_domain[] = ['fiscal_period_id', '=', $params['fiscal_period_id']];
    }

    $statement = ExpenseStatement::search(
            $statement_domain,
            [
                'sort'  => ['posting_date' => 'desc', 'id' => 'desc'],
                'limit' => 1
            ]
        )
        ->read(['id'])
        ->first();
}

if(!$statement) {
    throw new Exception('no_matching_statement', EQ_ERROR_UNKNOWN_OBJECT);
}

$statementOwner = ExpenseStatementOwner::id($map_statement_owner_ids[$statement['id']])
    ->read(['schema'])
    ->first();

if(!$statementOwner || !is_array($statementOwner['schema'] ?? null)) {
    throw new Exception('no_matching_owner', EQ_ERROR_UNKNOWN_OBJECT);
}

$owner = $statementOwner['schema'];
$owner['expenses'] = $buildOwnerExpenses($owner);

$context->httpResponse()
    ->status(200)
    ->body($owner)
    ->send();
