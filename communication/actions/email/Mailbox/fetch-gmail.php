<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

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
    ->read(['status', 'auth_type', 'imap_server', 'imap_port', 'email', 'access_token', 'access_token_expiry', 'date_last_sync'])
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


$imap = imap_open(
    '{' . $mailbox['imap_server'] . ':' . $mailbox['imap_port'] . '/imap/ssl}INBOX',
    $mailbox['imap_server'],
    $mailbox['access_token'],
    OP_READONLY,
    1,
    [
        'DISABLE_AUTHENTICATOR' => ['PLAIN', 'LOGIN']
    ]
);

if(!$imap) {
    trigger_error('APP::Unable to connect to IMAP server' . imap_last_error(), EQ_REPORT_ERROR);
    throw new Exception("imap_connect_error", EQ_ERROR_INVALID_PARAM);
}

Mailbox::id($mailbox['id'])->update(['date_last_sync' => time()]);


$messages = imap_search($imap, 'SINCE "' . date('d-M-Y', $mailbox['date_last_sync']) . '"');


foreach($messages ?? [] as $num) {
    $overview = imap_fetch_overview($imap, $num, 0)[0];
    $structure  = imap_fetchstructure($imap, $num);

    $email = Email::create([
            'subject'   => isset($overview->subject) ? imap_utf8($overview->subject) : '(no subject)',
            'from'      => $overview->from ?? '',
            'to'        => $overview->to ?? '',
            'direction' => 'incoming',
            'date'      => $overview->date ?? ''
        ])
        ->first();

    if(!isset($structure->parts)) {
        $body = imap_body($imap, $num);
        Email::id($email['id'])->update(['body' => $body]);
    }
    else {
        foreach($structure->parts as $i => $part) {
            if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') {
                $filename = $part->dparameters[0]->value ?? "attachment_$i";
                $content = imap_fetchbody($imap, $num, $i+1);
                $data = base64_decode($content);
                Document::create([
                        'name'      => $filename,
                        'data'      => $data,
                        'email_id'  => $email['id']
                    ])
                    ->do('start_processing');
            }
        }
    }
}


$context->httpResponse()
        ->status(204)
        ->send();
