<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\governance\Assembly;

[$params, $providers] = eQual::announce([
    'description'   => "Add an attendee to the target assembly.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the invitation refers to.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],

        'owner_id' => [
            'type'              => 'many2one',
            'description'       => "The owner concerned by the invitation.",
            'help'              => 'Is expected to be an Ownership representative owner.',
            'foreign_object'    => 'realestate\ownership\Owner',
            'visible'           => ['has_show', '=', true]
        ],

        'has_show' => [
            'type'              => 'boolean',
            'description'       => "Full name of the contact (must be a person, not a role).",
            'default'           => false
        ]
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

$context->httpResponse()
        ->body($attendee)
        ->send();
