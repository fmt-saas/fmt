<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\broadcast\BroadcastMessage;
use communication\email\Email;
use core\Task;
use equal\email\Email as EmailMessage;
use equal\email\EmailAttachment;
use fmt\core\Mail;
use realestate\management\ManagementProcess;

[$params, $providers] = eQual::announce([
    'description'	=>	"Processes a broadcast, for the moment only the sending sending of emails is handled.",
    'params' 		=>	[
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'communication\broadcast\BroadcastMessage',
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

$broadcast = BroadcastMessage::id($params['id'])
    ->read([
        'status',
        'reply_to',
        'subject',
        'body',
        'documents_ids',
        'identities_ids'    => ['email'],
    ])
    ->first();

if(!$broadcast) {
    throw new Exception("unknown_broadcast", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($broadcast['status'], ['ready', 'scheduled'])) {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

$managementProcess = ManagementProcess::search(['code', '=', 'communication'])->read(['mailbox_id'])->first();
if(!$managementProcess || !$managementProcess['mailbox_id']) {
    throw new Exception('missing_mandatory_mailbox', EQ_ERROR_INVALID_CONFIG);
}

BroadcastMessage::id($broadcast['id'])->transition('start_processing');

foreach($broadcast['identities_ids'] as $identity) {
    if(!$identity['email'] || strlen($identity['email']) <= 0) {
        continue;
    }

    $message = new EmailMessage();

    if(!empty($broadcast['reply_to'])) {
        $message->setReplyTo($broadcast['reply_to']);
    }

    $message
        ->setTo($identity['email'])
        ->setSubject($broadcast['subject'])
        ->setContentType("text/html")
        ->setBody($broadcast['body']);

    $email_id = Mail::queue(
        $message,
        'communication\broadcast\BroadcastMessage',
        $broadcast['id']
    );

    Email::id($email_id)->update([
        'mailbox_id'                => $managementProcess['mailbox_id'],
        'attachment_documents_ids'  => $broadcast['documents_ids']
    ]);
}

BroadcastMessage::id($broadcast['id'])
    ->transition('end_processing');

$context
    ->httpResponse()
    ->status(200)
    ->send();
