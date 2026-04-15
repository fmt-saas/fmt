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
            'description'     => 'Identifier of the mailbox to fetch.',
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


$allowed_mime_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
$max_messages_per_fetch = 50;
$graph_page_size = 50;


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
try {
    if($mailbox['access_token_expiry'] < time()) {
        eQual::run('do', 'communication_email_Mailbox_refresh-token-outlook', ['id' => $params['id']]);

        $mailbox = Mailbox::id($params['id'])
            ->read(['access_token', 'email', 'date_last_sync'])
            ->first();
    }
}
catch(Exception $e) {
    // refresh token failed : force the need for OAuth renewal
    Mailbox::id($params['id'])->update(['status' => 'pending']);
    throw $e;
}


// GRAPH API REQUEST : FETCH NEW EMAILS

$since = str_replace('+00:00', 'Z', gmdate('c', $mailbox['date_last_sync']));
$query = http_build_query([
    '$filter'   => "receivedDateTime ge $since",
    '$orderby'  => 'receivedDateTime asc',
    '$top'      => $graph_page_size
]);

$url = "https://graph.microsoft.com/v1.0/me/messages?$query";
$new_date_last_sync = time();
$imported_messages_count = 0;
$fetch_limit_reached = false;
$last_processed_message_date = null;

do {
    $http = new HttpRequest("GET $url");

    $response = $http
        ->header("Authorization", "Bearer " . $mailbox['access_token'])
        ->send();

    $data = $response->body();
    $status = $response->getStatusCode();

    if($status < 200 || $status > 299) {
        trigger_error("APP::Graph API error: " . json_encode($data), EQ_REPORT_ERROR);
        throw new Exception("graph_api_error", EQ_ERROR_INVALID_PARAM);
    }

    $messages = $data['value'] ?? [];

    foreach($messages as $msg) {

        $message_id = $msg['id'];
        $internet_id = $msg['internetMessageId'] ?? ('graph-' . $message_id);

        // Skip already imported
        if(Email::search(['message_id', '=', $internet_id])->first()) {
            continue;
        }

        $message_date = strtotime($msg['receivedDateTime'] ?? '');

        // create email record
        $email = Email::create([
                'mailbox_id' => $mailbox['id'],
                'message_id' => $internet_id,
                'subject'    => substr($msg['subject'] ?: '(no subject)', 0, 255),
                'from'       => $msg['from']['emailAddress']['address'] ?? '',
                'to'         => $msg['toRecipients'][0]['emailAddress']['address'] ?? '',
                'direction'  => 'incoming',
                'date'       => $message_date,
                'body'       => $msg['body']['content'] ?? ''
            ])
            ->read(['thread_hash'])
            ->first();

        // handle attachments
        $attUrl = "https://graph.microsoft.com/v1.0/me/messages/{$message_id}/attachments";

        $attReq = new HttpRequest("GET $attUrl");
        $attRes = $attReq
            ->header("Authorization", "Bearer " . $mailbox['access_token'])
            ->send();

        $attachments = $attRes->body()['value'] ?? [];

        // #todo - en cas d'absence de document, reponse automatique pour dire donnant le cadre dans lequel ce mail sera traite (pas lu, uniq. piece jointe) -> si info importante : envoyer sur autre adresse

        foreach($attachments as $att) {

            if(!isset($att['contentBytes'])) {
                continue;
            }

            // limit to "doc" attachments : pdf, doc(x), xls(x)
            $mime = $att['contentType'] ?? null;

            if(!in_array($mime, $allowed_mime_types, true)) {
                continue;
            }

            Document::create([
                    'name'     => $att['name'],
                    'data'     => base64_decode($att['contentBytes']),
                    'email_id' => $email['id']
                ])
                ->do('start_processing');
        }

        ++$imported_messages_count;
        if($message_date !== false) {
            $last_processed_message_date = $message_date;
        }

        if($imported_messages_count >= $max_messages_per_fetch) {
            $fetch_limit_reached = true;
            break;
        }
    }

    $url = $data['@odata.nextLink'] ?? null;
}
while($url && !$fetch_limit_reached);

if($fetch_limit_reached && $last_processed_message_date !== null) {
    Mailbox::id($mailbox['id'])->update(['date_last_sync' => max(0, $last_processed_message_date - 1)]);
}
else {
    Mailbox::id($mailbox['id'])->update(['date_last_sync' => $new_date_last_sync]);
}

$context->httpResponse()
    ->status(204)
    ->send();
