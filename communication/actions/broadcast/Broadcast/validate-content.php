<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\broadcast\Broadcast;

[$params, $providers] = eQual::announce([
    'description'	=>	"Validates the body, subject, reply_to etc.",
    'params' 		=>	[
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'communication\broadcast\Broadcast',
            'description'      => "The broadcast concerned by the validation.",
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

$broadcast = Broadcast::id($params['id'])
    ->read(['step', 'status', 'body'])
    ->first();

if(!$broadcast) {
    throw new Exception("unknown_broadcast", EQ_ERROR_UNKNOWN_OBJECT);
}

if($broadcast['step'] !== 'content_edition') {
    throw new Exception("invalid_step", EQ_ERROR_INVALID_PARAM);
}

if($broadcast['status'] !== 'draft') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

if(empty($broadcast['body'])) {
    throw new Exception("invalid_body", EQ_ERROR_INVALID_PARAM);
}

Broadcast::id($broadcast['id'])->update(['status' => 'ready']);

$context
    ->httpResponse()
    ->status(200)
    ->send();
