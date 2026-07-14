<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\navigation\Node;
use equal\orm\Domain;
use identity\User;
use realestate\ownership\Owner;

[$params, $providers] = eQual::announce([
    'description'   => 'Returns navigation nodes visible to the current user.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'   => 'Full name of the entity to collect.',
            'type'          => 'string',
            'default'       => 'documents\navigation\Node'
        ],
        'domain' => [
            'description'   => 'Criterias that results have to match (series of conjunctions).',
            'type'          => 'array',
            'default'       => []
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth', 'access']
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\auth\AuthenticationManager $auth
 * @var \fmt\access\AccessController      $access
 */
['context' => $context, 'auth' => $auth, 'access' => $access] = $providers;

$params['entity'] = 'documents\navigation\Node';

$user_id = $auth->userId();
$user = User::id($user_id)->read(['identity_id', 'employee_id'])->first();

$is_root = ($user_id === EQ_ROOT_USER_ID);
$is_admin = $access->hasGroup('admins');

if(!$user['employee_id'] && !$is_root && !$is_admin) {
    $owners = Owner::search(['identity_id', '=', $user['identity_id']])->read(['condo_id', 'ownership_id']);

    $owner_ids = [];
    $map_condo_ids = [];
    $map_ownership_ids = [];

    foreach($owners as $owner_id => $owner) {
        $owner_ids[] = $owner_id;

        if(isset($owner['condo_id'])) {
            $map_condo_ids[$owner['condo_id']] = true;
        }

        if(isset($owner['ownership_id'])) {
            $map_ownership_ids[$owner['ownership_id']] = true;
        }
    }

    $map_allowed_nodes_ids = [];

    if(count($map_condo_ids)) {
        foreach(Node::search([
                ['node_visibility', '=', 'condo'],
                ['condo_id', 'in', array_keys($map_condo_ids)]
            ])
            ->ids() as $node_id) {
            $map_allowed_nodes_ids[$node_id] = true;
        }
    }

    if(count($map_ownership_ids)) {
        foreach(Node::search([
                ['node_visibility', '=', 'ownership'],
                ['ownership_id', 'in', array_keys($map_ownership_ids)]
            ])
            ->ids() as $node_id) {
            $map_allowed_nodes_ids[$node_id] = true;
        }
    }

    if(count($owner_ids)) {
        foreach(Node::search([
                ['node_visibility', '=', 'owner'],
                ['owner_id', 'in', $owner_ids]
            ])
            ->ids() as $node_id) {
            $map_allowed_nodes_ids[$node_id] = true;
        }
    }

    $params['domain'] = Domain::conditionAdd(
        $params['domain'],
        count($map_allowed_nodes_ids) ? ['id', 'in', array_keys($map_allowed_nodes_ids)] : ['id', '=', 0]
    );
}

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
