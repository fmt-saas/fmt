<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

[$params, $providers] = eQual::announce([
    'description'   => 'Return the provider-independent registry of tools available to the authenticated agent.',
    'params'        => [],
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

$ownership_id = [
    'type'        => 'integer',
    'description' => 'Ownership identifier returned by get_userinfo for the authenticated user.',
    'minimum'     => 1
];

$fiscal_year_id = [
    'type'        => 'integer',
    'description' => 'Optional fiscal year identifier. The current fiscal year is used when omitted.',
    'minimum'     => 1
];

$tools = [
    [
        'name'        => 'get_userinfo',
        'description' => 'Identify the authenticated user and list the owner, condominium and ownership contexts available to that user. Call this tool before any ownership-specific tool.',
        'parameters'  => [
            'type'                 => 'object',
            'properties'           => new stdClass(),
            'required'             => [],
            'additionalProperties' => false
        ],
        'handler'     => 'automation_agent_tools_get-userinfo',
        'ux'          => [
            'code'    => 'identify_owner_context',
            'running' => "J'identifie votre dossier",
            'done'    => 'Dossier identifié'
        ]
    ],
    [
        'name'        => 'get_account_summary',
        'description' => 'Return the account summary for one ownership during its condominium current fiscal year.',
        'parameters'  => [
            'type'                 => 'object',
            'properties'           => ['ownership_id' => $ownership_id],
            'required'             => ['ownership_id'],
            'additionalProperties' => false
        ],
        'handler'     => 'automation_agent_tools_get-account-summary',
        'ux'          => [
            'code'    => 'load_account_summary',
            'running' => 'Je consulte le solde de votre compte',
            'done'    => 'Solde du compte consulté'
        ]
    ],
    [
        'name'        => 'get_fundings',
        'description' => 'Return fund requests and expense statements for one ownership and fiscal year, including unpaid items.',
        'parameters'  => [
            'type'                 => 'object',
            'properties'           => [
                'ownership_id'   => $ownership_id,
                'fiscal_year_id' => $fiscal_year_id
            ],
            'required'             => ['ownership_id'],
            'additionalProperties' => false
        ],
        'handler'     => 'automation_agent_tools_get-fundings',
        'ux'          => [
            'code'    => 'load_fundings',
            'running' => 'Je recherche vos appels de fonds',
            'done'    => 'Appels de fonds retrouvés'
        ]
    ],
    [
        'name'        => 'get_payments',
        'description' => 'Return payments received for one ownership during a fiscal year.',
        'parameters'  => [
            'type'                 => 'object',
            'properties'           => [
                'ownership_id'   => $ownership_id,
                'fiscal_year_id' => $fiscal_year_id
            ],
            'required'             => ['ownership_id'],
            'additionalProperties' => false
        ],
        'handler'     => 'automation_agent_tools_get-payments',
        'ux'          => [
            'code'    => 'load_payments',
            'running' => 'Je recherche vos paiements',
            'done'    => 'Paiements retrouvés'
        ]
    ],
    [
        'name'        => 'get_expense_statement',
        'description' => 'Return the expenses assigned to one ownership for an issued expense statement.',
        'parameters'  => [
            'type'                 => 'object',
            'properties'           => [
                'ownership_id' => $ownership_id,
                'fiscal_period_id' => [
                    'type'        => 'integer',
                    'description' => 'Optional fiscal period identifier. The latest issued period is used when omitted.',
                    'minimum'     => 1
                ]
            ],
            'required'             => ['ownership_id'],
            'additionalProperties' => false
        ],
        'handler'     => 'automation_agent_tools_get-expense-statement',
        'ux'          => [
            'code'    => 'load_expense_statement',
            'running' => 'Je consulte votre décompte de charges',
            'done'    => 'Décompte de charges consulté'
        ]
    ],
    [
        'name'        => 'get_account_history',
        'description' => 'Return the chronological account history for one ownership during a fiscal year.',
        'parameters'  => [
            'type'                 => 'object',
            'properties'           => [
                'ownership_id'   => $ownership_id,
                'fiscal_year_id' => $fiscal_year_id
            ],
            'required'             => ['ownership_id'],
            'additionalProperties' => false
        ],
        'handler'     => 'automation_agent_tools_get-account-history',
        'ux'          => [
            'code'    => 'load_account_history',
            'running' => "Je consulte l'historique de votre compte",
            'done'    => 'Historique du compte consulté'
        ]
    ]
];

$context->httpResponse()
    ->status(200)
    ->body(['tools' => $tools])
    ->send();
