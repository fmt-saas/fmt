<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Email;
use communication\email\Mailbox;
use documents\Document;
use equal\http\HttpRequest;

[$params, $providers] = eQual::announce([
    'description' => "Fetch emails from Outlook using Microsoft Graph API.",
    'params' => [
        'id' => [
            'type'            => 'many2one',
            'foreign_object'  => 'communication\\email\\Mailbox',
            'required'        => true
        ]
    ],
    'access' => [
        'visibility' => 'protected'
    ],
    'response' => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context', 'auth']
]);


/**
 * @var equal\php\Context                $context
 * @var equal\auth\AuthenticationManager $auth
 */
['context' => $context, 'auth' => $auth] = $providers;


/* ---------------------------------------------------------
   VALIDATION
--------------------------------------------------------- */

$mailbox = Mailbox::id($params['id'])
    ->read(['status', 'auth_type', 'access_token', 'access_token_expiry', 'refresh_token_expiry', 'email', 'date_last_sync'])
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

/* Refresh token if needed */
if($mailbox['access_token_expiry'] < time()) {
    eQual::run('do', 'communication_email_Mailbox_refresh-token-outlook', ['id' => $params['id']]);

    $mailbox = Mailbox::id($params['id'])
        ->read(['access_token', 'email', 'date_last_sync'])
        ->first();
}


/* ---------------------------------------------------------
   GRAPH API REQUEST : FETCH NEW EMAILS
--------------------------------------------------------- */

$since = str_replace('+00:00', 'Z', gmdate('c', $mailbox['date_last_sync']));
/*
$url = "https://graph.microsoft.com/v1.0/me/messages?"
    . '$filter=receivedDateTime ge ' . "'$since'"
    . '&$orderby=receivedDateTime desc'
    . '&$top=50';
*/
$url = "https://graph.microsoft.com/v1.0/me/messages";

$http = new HttpRequest("GET $url");

$response = $http
    ->header("Authorization", "Bearer " . $mailbox['access_token'])
    ->send();

$data = $response->body();
$status = $response->getStatusCode();

if($status < 200 || $status > 299) {
    trigger_error("Graph API error: " . json_encode($data), EQ_REPORT_ERROR);
    throw new Exception("graph_api_error", EQ_ERROR_INVALID_PARAM);
}

$messages = $data['value'] ?? [];

/* Update sync time */
Mailbox::id($mailbox['id'])->update(['date_last_sync' => time()]);


/* ---------------------------------------------------------
   PROCESS EACH MESSAGE
--------------------------------------------------------- */

foreach($messages as $msg) {

    $message_id = $msg['id'];
    $internet_id = $msg['internetMessageId'] ?? ('graph-' . $message_id);

    // Skip already imported
    if(Email::search(['message_id', '=', $internet_id])->first()) {
        continue;
    }

    /* Create email record */
    $email = Email::create([
            'mailbox_id' => $mailbox['id'],
            'message_id' => $internet_id,
            'subject'    => $msg['subject'] ?: '(no subject)',
            'from'       => $msg['from']['emailAddress']['address'] ?? '',
            'to'         => $msg['toRecipients'][0]['emailAddress']['address'] ?? '',
            'direction'  => 'incoming',
            'date'       => strtotime($msg['receivedDateTime']),
            'body'       => $msg['body']['content'] ?? ''
        ])
        ->read(['thread_hash'])
        ->first();


    /* ---------------------------------------------------------
       ATTACHMENTS
    --------------------------------------------------------- */

    $attUrl = "https://graph.microsoft.com/v1.0/me/messages/$message_id/attachments";

    $attReq = new HttpRequest("GET $attUrl");
    $attRes = $attReq
        ->header("Authorization", "Bearer " . $mailbox['access_token'])
        ->send();

    $atts = $attRes->body()['value'] ?? [];

    foreach($atts as $att) {

        if(!isset($att['contentBytes'])) {
            continue;
        }

        Document::create([
                'name'     => $att['name'],
                'data'     => base64_decode($att['contentBytes']),
                'email_id' => $email['id']
            ])
            ->do('start_processing');
    }
}


/* ---------------------------------------------------------
   RESPONSE
--------------------------------------------------------- */

$context->httpResponse()
    ->status(204)
    ->send();
