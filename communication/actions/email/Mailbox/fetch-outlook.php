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
    'description'	=>	"Fetch emails from an Outlook/Microsoft 365 Mailbox using OAuth2.",
    'params' 		=>	[
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'communication\email\Mailbox',
            'description'      => 'Mailbox identifier.',
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
    'providers'     => ['context', 'auth']
]);


/**
 * @var equal\php\Context                   $context
 * @var equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'auth' => $auth] = $providers;


/* ---------------------------------------------------------
   LOAD MAILBOX (VALIDATION)
--------------------------------------------------------- */

$mailbox = Mailbox::id($params['id'])
    ->read(['status', 'auth_type', 'access_token_expiry', 'refresh_token_expiry'])
    ->first();

if(!$mailbox) {
    throw new Exception("unknown_mailbox", EQ_ERROR_INVALID_PARAM);
}

if($mailbox['status'] !== 'validated') {
    throw new Exception("non_validated_mailbox", EQ_ERROR_INVALID_PARAM);
}

if($mailbox['auth_type'] !== 'oauth') {
    throw new Exception("non_oauth_mailbox", EQ_ERROR_INVALID_PARAM);
}

if($mailbox['refresh_token_expiry'] < time()) {
    throw new Exception("expired_refresh_token", EQ_ERROR_INVALID_PARAM);
}

/**
 * If the access token has expired → refresh it using Outlook OAuth
 */
if ($mailbox['access_token_expiry'] < time()) {
    eQual::run('do', 'communication_email_Mailbox_refresh-token-outlook', ['id' => $params['id']]);
}


/* ---------------------------------------------------------
   RELOAD MAILBOX INFO
--------------------------------------------------------- */

$mailbox = Mailbox::id($params['id'])
    ->read(['imap_server', 'imap_port', 'email', 'access_token', 'date_last_sync'])
    ->first();


/* ---------------------------------------------------------
   CONNECT TO OUTLOOK IMAP (XOAUTH2)
--------------------------------------------------------- */

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
        'host'           => $mailbox['imap_server'],     // imap.outlook.com
        'port'           => $mailbox['imap_port'],       // 993
        'encryption'     => 'ssl',
        'validate_cert'  => false,
        'username'       => $mailbox['email'],           // full email address
        'password'       => $mailbox['access_token'],    // OAuth access token
        'authentication' => 'oauth',                     // XOAUTH2
        'protocol'       => 'imap'
    ]);

    $client->connect();


    /* ---------------------------------------------------------
       FETCH EMAILS
    --------------------------------------------------------- */

    $inbox = $client->getFolder('INBOX');

    // Fetch only new messages since last sync
    $messages = $inbox->query()
        ->since($mailbox['date_last_sync'])
        ->get();

    // Update sync timestamp
    Mailbox::id($mailbox['id'])->update(['date_last_sync' => time()]);

    $allowed_mime_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    foreach($messages as $message) {

        $message_id = $message->getMessageId();

        // Skip if already imported
        if(Email::search(['message_id', '=', $message_id])->first()) {
            continue;
        }

        // Create email entry
        $email = Email::create([
                'mailbox_id'    => $mailbox['id'],
                'message_id'    => $message_id,
                'subject'       => $message->getSubject() ?: '(no subject)',
                'from'          => $message->getFrom()[0]->mail ?? '',
                'to'            => $message->getTo()[0]->mail ?? '',
                'direction'     => 'incoming',
                'date'          => strtotime($message->getDate()),
                'body'          => $message->getHTMLBody() ?? ($message->getTextBody() ?? ''),
            ])
            ->read(['thread_hash'])
            ->first();


        /* ---------------------------------------------------------
           ATTACHMENTS
        --------------------------------------------------------- */

        foreach($message->getAttachments() as $attachment) {

            if(!in_array($attachment->mime, $allowed_mime_types)) {
                continue;
            }

            Document::create([
                    'name'      => $attachment->name,
                    'data'      => $attachment->content,
                    'email_id'  => $email['id']
                ])
                ->do('start_processing');
        }
    }

    $client->disconnect();

}
catch(\Exception $e) {
    $error = '';
    if($cm) {
        $errors = $cm->getErrors();
        $error = end($errors);
    }
    trigger_error('APP::Unable to connect to Outlook IMAP: ' . $e->getMessage() . ' ' . $error, EQ_REPORT_ERROR);
    throw new Exception("imap_connect_error", EQ_ERROR_INVALID_PARAM);
}


/* ---------------------------------------------------------
   RESPONSE
--------------------------------------------------------- */

$context->httpResponse()
    ->status(204)
    ->send();
