<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\ownership\Owner;
use identity\Identity;
use identity\Group;
use identity\User;

[$params, $providers] = eQual::announce([
    'description'   => "Create a User account for a single Owner or a group of Owners.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The specific Owner the requests refers to.",
            'foreign_object'    => 'realestate\ownership\Owner',
            'required'          => true
        ],
        'ids' => [
            'type'              => 'one2many',
            'foreign_object'    => 'realestate\ownership\Owner',
            'description'       => 'List of Owners the requests refers to.',
            'default'           => []
        ],
    ],
    'constants'     => ['AUTH_SECRET_KEY'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch', 'auth', 'orm']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 * @var \equal\auth\AuthenticationManager  $auth
 * @var \equal\orm\ObjectManager           $orm
 */
['context' => $context, 'dispatch' => $dispatch, 'auth' => $auth, 'orm' => $orm] = $providers;

$user_id = $auth->userId();

// we need root privilege
$auth->su();

$ids = array_merge((array) ($params['id'] ?? []), $params['ids'] ?? []);

$owners = Owner::ids($ids)->read(['identity_id']);

$groups_ids = Group::search(['name', 'in', ['users']])->ids();

foreach($owners as $owner_id => $owner) {
    $identity = Identity::id($owner['identity_id'])->read(['email', 'user_id'])->first();

    // #memo in case the user already exists, simply ignore the request
    if(!$identity['user_id'] && $identity['email']) {

        $new_user_id = $orm->create(User::getType(), [
                // #memo - Capabilities for EQ_R_UPDATE are based on 'creator' context
                'creator'       => $user_id,
                'login'         => $identity['email'],
                'language'      => 'fr',
                'validated'     => true,
                'is_employee'   => false,
                'is_owner'      => true,
                'groups_ids'    => $groups_ids
            ]);

        if($new_user_id <= 0) {
            trigger_error("APP::error at new user creation for identity {$identity['name']}.", EQ_REPORT_WARNING);
            continue;
        }

        User::id($new_user_id)
            ->update(['identity_id' => $identity['id']])
            ->do('sync_from_identity');

        Owner::id($owner_id)->update(['user_id' => $new_user_id]);

        eQual::run('do', 'identity_User_send-confirmation', ['id' => $new_user_id]);
    }
}

$auth->su($user_id);

$context->httpResponse()
        ->status(201)
        ->send();
