<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\DocumentSignature;
use realestate\governance\Assembly;
use realestate\ownership\Owner;
use identity\Identity;
use identity\User;
use realestate\governance\AssemblyAttendee;

[$params, $providers] = eQual::announce([
    'description'   => "Create a User account for a single Owner or a group of Owners.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the invitation refers to.",
            'foreign_object'    => 'realestate\ownership\Owner',
            'required'          => true
        ],
        'ids' => [
            'type'              => 'one2many',
            'foreign_object'    => 'realestate\ownership\Owner',
            'description'       => 'Identifier of the targeted receivables queues.',
            'default'           => []
        ],
    ],
    'constants'     => ['AUTH_SECRET_KEY'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;


$ids = array_merge((array) ($params['id'] ?? []), $params['ids'] ?? []);

$owners = Owner::ids($ids)->read(['identity_id'])->first();

foreach($owners as $owner_id => $owner) {
    $identity = Identity::id($owner['identity_id'])->read(['email', 'user_id'])->first();

    // #memo in cas the user already exists, simply ignore the request
    if(!$identity['user_id'] && $identity['email']) {
        // search for an email address
        $user = User::create([
                'identity_id'   => $identity['id'],
                'login'         => $identity['email'],
                'language'      => 'fr',
                'validated'     => true,
                // users
                'groups_ids'    => [2]
            ])
            ->first();
    }
}

$context->httpResponse()
        ->status(201)
        ->send();
