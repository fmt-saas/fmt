<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\broadcast\Broadcast;
use equal\email\Email;
use fmt\core\Mail;

[$params, $providers] = eQual::announce([
    'description'	=>	"Processes a broadcast, for the moment only the sending sending of emails is handled.",
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
    ->read(['status', 'reply_to', 'subject', 'body', 'identities_ids' => ['email']])
    ->first();

if(!$broadcast) {
    throw new Exception("unknown_broadcast", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($broadcast['status'], ['ready', 'scheduled'])) {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

Broadcast::id($params['id'])->update(['status' => 'processing']);

$mails_ids = [];
foreach($broadcast['identities_ids'] as $identity) {
    $message = new Email();

    if(!empty($broadcast['reply_to'])) {
        $message->setReplyTo($broadcast['reply_to']);
    }

    $message
        ->setTo($identity['email'])
        ->setSubject($broadcast['subject'])
        ->setContentType("text/html")
        ->setBody($broadcast['body']);

    $mails_ids[] = Mail::queue($message, 'communication\broadcast\Broadcast', $broadcast['id']);
}

Broadcast::id($params['id'])
    ->update([
        'status'    => 'processed',
        'mails_ids' => $mails_ids
    ]);

$context
    ->httpResponse()
    ->status(200)
    ->send();
