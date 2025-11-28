<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Webklex\PHPIMAP\ClientManager;

use communication\email\Email;
use communication\email\Mailbox;
use documents\Document;

[$params, $providers] = eQual::announce([
    'description'	=>	"Fetch emails from a Gmail/Google Mailbox using OAuth2.",
    'params' 		=>	[
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'communication\email\Mailbox',
            'description'      => 'Identifier of the mailbox to fetch.',
            'required'         => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm'],
    'constants'     => ['BACKEND_URL']
]);

/**
 * @var equal\php\Context                   $context
 * @var equal\orm\ObjectManager             $om
 * @var equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;

$allowed_mime_types = [
        'text/xml',
        'application/xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

// check consistency
$mailbox = Mailbox::id($params['id'])
    ->read(['status', 'auth_type', 'imap_server', 'imap_port', 'login', 'password', 'date_last_sync'])
    ->first();

if(!$mailbox) {
    throw new Exception("unknown_mailbox", EQ_ERROR_INVALID_PARAM);
}

if($mailbox['status'] !== 'validated') {
    throw new Exception("non_validated_mailbox", EQ_ERROR_INVALID_PARAM);
}

if($mailbox['auth_type'] !== 'basic') {
    throw new Exception("non_imap_mailbox", EQ_ERROR_INVALID_PARAM);
}

try {

    $cm = new ClientManager([
        /*
        // #debug
        'options' => [
            'debug' => true,
            'log'   => true,
            'log_channel' => 'imap',
        ]
        */
    ]);

    $client = $cm->make([
        'host'           => $mailbox['imap_server'],
        'port'           => $mailbox['imap_port'],
        'encryption'     => 'ssl',
        'validate_cert'  => false,
        'username'       => $mailbox['login'],
        'password'       => $mailbox['password'],
        'protocol'       => 'imap'
    ]);

    $client->connect();

    // limit requests on main inbox
    $inbox = $client->getFolder('INBOX');

    // limit query to messages received since last sync
    $messages = $inbox->query()->since($mailbox['date_last_sync'])->get();

    // update date of last synchro (before hand so that we don't re-download in case of error)
    Mailbox::id($mailbox['id'])->update(['date_last_sync' => time()]);

    foreach($messages as $message) {
        $message_id = $message->getMessageId();

        $email = Email::search(['message_id', '=', $message_id])->first();

        if($email) {
            continue;
        }

        $email = Email::create([
                'mailbox_id'    => $mailbox['id'],
                'message_id'    => $message_id,
                'subject'       => substr($message->getSubject() ?: '(no subject)', 0, 255),
                'from'          => $message->getFrom()[0]->mail ?? '',
                'to'            => $message->getTo()[0]->mail ?? '',
                'direction'     => 'incoming',
                'date'          => strtotime($message->getDate()),
                'body'          => $message->getHTMLBody() ?? ($message->getTextBody() ?? ''),
            ])
            ->read(['thread_hash'])
            ->first();

        // handle attachments

        // #todo - en cas d'absence de document, réponse automatique pour dire donnant le cadre dans lequel ce mail sera traité (pas lu, uniq. pièce jointe) -> si info importante : envoyer sur autre adresse

        foreach($message->getAttachments() as $attachment) {
            // limit to "doc" attachments : pdf, doc(x), xls(x)
            if(!in_array($attachment->mime, $allowed_mime_types)) {
                continue;
            }

            Document::create([
                    'name'      => $attachment->getName(),
                    'data'      => $attachment->getContent(),
                    'email_id'  => $email['id']
                ])
                // create related DocumentProcess object
                ->do('start_processing');
        }
    }

    $client->disconnect();

}
catch(\Exception $e) {
    trigger_error('APP::Unable to connect to IMAP server: ' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception("imap_connect_error", EQ_ERROR_INVALID_PARAM);
}

$context->httpResponse()
        ->status(204)
        ->send();
