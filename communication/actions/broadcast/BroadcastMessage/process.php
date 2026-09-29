<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\broadcast\BroadcastMessage;
use communication\email\Email;
use communication\email\Mailbox;
use equal\email\Email as EmailMessage;
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
        'mails_ids'         => ['to'],
    ])
    ->first();

if(!$broadcast) {
    throw new Exception("unknown_broadcast", EQ_ERROR_UNKNOWN_OBJECT);
}

if($broadcast['status'] !== 'scheduled') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

try {
    BroadcastMessage::id($broadcast['id'])->transition('start_processing');

    $managementProcess = ManagementProcess::search(['code', '=', 'communication'])
        ->read(['mailbox_id'])
        ->first();

    if(!$managementProcess || !$managementProcess['mailbox_id']) {
        throw new Exception('missing_mandatory_mailbox', EQ_ERROR_INVALID_CONFIG);
    }

    $mailbox = Mailbox::id($managementProcess['mailbox_id'])
        ->read(['id', 'status', 'can_send'])
        ->first();

    if(!$mailbox) {
        throw new Exception('missing_mandatory_mailbox', EQ_ERROR_INVALID_CONFIG);
    }

    if($mailbox['status'] !== 'validated' || !$mailbox['can_send']) {
        throw new Exception('invalid_sending_mailbox', EQ_ERROR_INVALID_CONFIG);
    }

    $queued_recipients = [];
    foreach($broadcast['mails_ids'] as $email) {
        $recipient_email = strtolower(trim((string) ($email['to'] ?? '')));
        if($recipient_email !== '') {
            $queued_recipients[$recipient_email] = true;
        }
    }

    foreach($broadcast['identities_ids'] as $identity) {
        $recipient_email = trim((string) ($identity['email'] ?? ''));
        if($recipient_email === '') {
            throw new Exception('invalid_recipient_email', EQ_ERROR_INVALID_CONFIG);
        }

        $recipient_key = strtolower($recipient_email);
        if(isset($queued_recipients[$recipient_key])) {
            continue;
        }

        $message = new EmailMessage();

        if(!empty($broadcast['reply_to'])) {
            $message->setReplyTo($broadcast['reply_to']);
        }

        $message
            ->setTo($recipient_email)
            ->setSubject($broadcast['subject'])
            ->setContentType("text/html")
            ->setBody($broadcast['body']);

        $email_id = Mail::queue(
            $message,
            'communication\broadcast\BroadcastMessage',
            $broadcast['id']
        );

        if(!$email_id) {
            throw new Exception('email_not_queued', EQ_ERROR_INVALID_CONFIG);
        }

        Email::id($email_id)->update([
            'mailbox_id'                => $mailbox['id'],
            'attachment_documents_ids'  => $broadcast['documents_ids']->ids()
        ]);

        $queued_recipients[$recipient_key] = true;
    }

    BroadcastMessage::id($broadcast['id'])
        ->transition('end_processing');
}
catch(Throwable $e) {
    try {
        $failed_broadcast = BroadcastMessage::id($broadcast['id'])
            ->read(['status'])
            ->first();

        if($failed_broadcast && $failed_broadcast['status'] === 'processing') {
            BroadcastMessage::id($broadcast['id'])
                ->transition('fail_processing');
        }
    }
    catch(Throwable $transition_error) {
        trigger_error(
            "APP::Unable to recover broadcast {$broadcast['id']} after processing failure: {$transition_error->getMessage()}",
            EQ_REPORT_ERROR
        );
    }

    trigger_error("APP::broadcast_processing_failed: " . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('broadcast_processing_failed', EQ_ERROR_UNKNOWN);
}

$context
    ->httpResponse()
    ->status(200)
    ->send();
