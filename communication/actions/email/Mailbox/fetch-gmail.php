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
    'description'	=>	"Refresh the access token of a given Mailbox.",
    'params' 		=>	[
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'communication\email\Mailbox',
            'description'      => 'Identifier of the Assembly item (resolution).',
            'required'          => true
        ]
    ],
    'constants'     => ['BACKEND_URL'],
    'access'        => [
        'visibility' => 'public'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm'],
    'constants'     => ['BACKEND_URL', 'AUTH_ACCESS_TOKEN_VALIDITY', 'AUTH_TOKEN_HTTPS', 'FMT_INSTANCE_TYPE']
]);

/**
 * @var equal\php\Context                   $context
 * @var equal\orm\ObjectManager             $om
 * @var equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;


$mailbox = Mailbox::id($params['id'])
    ->read(['status', 'auth_type', 'imap_server', 'imap_port', 'email', 'access_token', 'access_token_expiry', 'refresh_token_expiry', 'date_last_sync'])
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

try {

    $cm = new ClientManager([
        /*
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
        'username'       => $mailbox['email'],
        'password'       => $mailbox['access_token'],
        'authentication' => "oauth",
        'protocol'       => 'imap'
    ]);

    $client->connect();

    // Récupère la boîte de réception
    $inbox = $client->getFolder('INBOX');

    // Recherche des messages reçus depuis la dernière synchro
    $messages = $inbox->query()->since($mailbox['date_last_sync'])->get();

    // Met à jour la date de synchro
    Mailbox::id($mailbox['id'])->update(['date_last_sync' => time()]);

    foreach ($messages as $message) {
        $email = Email::create([
                'subject'   => $message->getSubject() ?: '(no subject)',
                'from'      => $message->getFrom()[0]->mail ?? '',
                'to'        => $message->getTo()[0]->mail ?? '',
                'direction' => 'incoming',
                'date'      => strtotime($message->getDate()),
                'body'      => $message->getTextBody() ?? $message->getHTMLBody(),
            ])
            ->first();

        // Gestion des pièces jointes
        foreach($message->getAttachments() as $attachment) {
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
    trigger_error('APP::Unable to connect to IMAP server: ' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception("imap_connect_error", EQ_ERROR_INVALID_PARAM);
}

$context->httpResponse()
        ->status(204)
        ->send();
