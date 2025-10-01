<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use finance\accounting\AccountChartTemplate;

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
            'default'           => function ($id=null) {
                $accountCharts = AccountChartTemplate::search();
                if($accountCharts->count() === 1) {
                    return ($accountCharts->first())['id'];
                }
                return null;
            }
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
