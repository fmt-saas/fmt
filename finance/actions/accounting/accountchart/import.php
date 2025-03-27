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

AccountChart::id($params['id'])
    ->do('import_accounts', ['chart_template_id' => $params['chart_template_id']]);

$context->httpResponse()
        ->status(201)
        ->send();
