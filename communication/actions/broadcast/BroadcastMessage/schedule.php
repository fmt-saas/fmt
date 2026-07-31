<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\broadcast\BroadcastMessage;
use core\Task;

[$params, $providers] = eQual::announce([
    'description'	=>	"Schedules a broadcast processing.",
    'params' 		=>	[
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'communication\broadcast\BroadcastMessage',
            'description'      => "The broadcast concerned by the scheduling.",
            'required'         => true
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
    'providers'     => ['context']
]);

/**
 * @var equal\php\Context   $context
 */
['context' => $context] = $providers;

$broadcast = BroadcastMessage::id($params['id'])
    ->read(['status'])
    ->first();

if(!$broadcast) {
    throw new Exception("unknown_broadcast", EQ_ERROR_UNKNOWN_OBJECT);
}

if($broadcast['status'] !== 'ready') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

BroadcastMessage::id($params['id'])->transition('schedule');

$context
    ->httpResponse()
    ->status(200)
    ->send();
