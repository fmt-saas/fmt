<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use finance\accounting\Account;
use finance\accounting\AccountChart;
use finance\accounting\AccountChartTemplate;
use realestate\property\Apportionment;

[$params, $providers] = eQual::announce([
    'description'    => 'Create accounts from a template chart of accounts.',
    'params'         => [
        'id' => [
            'type'           => 'many2one',
            'foreign_object' => 'finance\accounting\AccountChart',
            'description'    => 'Chart of Accounts to populate.',
            'required'       => true
        ],
        'chart_template_id' => [
            'label'          => 'Chart template',
            'type'           => 'many2one',
            'foreign_object' => 'finance\accounting\AccountChartTemplate',
            'description'    => 'Template to use to populate Chart of Accounts.',
            'required'       => true
        ]
    ],
    'response'       => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'providers'      => ['context', 'auth']
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\auth\AuthenticationManager $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$template = AccountChartTemplate::id($params['chart_template_id'])
    ->read([
        'accounts_ids' => [
            'name',
            'code',
            'description',
            'level',
            'account_class',
            'account_type',
            'account_nature',
            'account_category',
            'parent_account_id',
            'is_visible',
            'is_control_account',
            'is_tier_balance',
            'operation_assignment',
            'tenant_share',
            'owner_share',
            'apportionment_code'
        ]
    ])
    ->first();

if(!$template) {
    throw new Exception('unknown_template', EQ_ERROR_INVALID_PARAM);
}

$accountChart = AccountChart::id($params['id'])->read(['condo_id', 'status'])->first();

if($accountChart['status'] != 'draft') {
    throw new Exception('invalid_status', EQ_ERROR_NOT_ALLOWED);
}

// remove any previously existing account attached to the chart
Account::search([['account_chart_id', '=', $params['id']]])->delete(true);

$apportionments = Apportionment::search(['condo_id', '=', $accountChart['condo_id']])->read(['apportionment_code']);

foreach($apportionments as $id => $apportionment) {
    $map_apportionments[$apportionment['apportionment_code']] = $id;
}

foreach($template['accounts_ids'] as $id => $account) {
    $values = [
            'condo_id'              => $accountChart['condo_id'],
            'account_chart_id'      => $params['id'],
            'name'                  => $account['name'],
            'code'                  => $account['code'],
            'description'           => $account['description'],
            'level'                 => $account['level'],
            'account_class'         => $account['account_class'],
            'account_type'          => $account['account_type'],
            'account_nature'        => $account['account_nature'],
            'account_category'      => $account['account_category'],
            'is_visible'            => $account['is_visible'],
            'is_control_account'    => $account['is_control_account'],
            'is_tier_balance'       => $account['is_tier_balance'],
            'operation_assignment'  => $account['operation_assignment'],
            'tenant_share'          => $account['tenant_share'],
            'owner_share'           => $account['owner_share']
        ];

    if(isset($map_apportionments[$account['apportionment_code']])) {
        $values['apportionment_id'] = $map_apportionments[$account['apportionment_code']];
    }
    Account::create($values);
}

$context->httpResponse()
        ->status(201)
        ->send();
