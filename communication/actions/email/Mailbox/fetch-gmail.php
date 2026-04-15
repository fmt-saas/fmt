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
    'description'	=>	"Fetch emails from a Gmail/Google Mailbox using Gmail API with OAuth2.",
    'params' 		=>	[
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'communication\email\Mailbox',
            'description'      => "Identifier of the mailbox to fetch.",
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

/*************
 * Functions *
 *************/

/**
 * Returns a page of emails that were received after the given timestamp
 *
 * @param string $access_token      Gmail Google API token
 * @param int $after                Unix timestamp
 * @param string|null $page_token   Gmail pagination token
 * @param int $max_results          Maximum number of messages for the page
 * @return array                    Gmail list response subset
 * @throws Exception
 */
$getMessagesPage = function($access_token, $after, $page_token = null, $max_results = 50) {
    $params = ['maxResults' => $max_results];
    if($after > 0) {
        $params['q'] = "after:$after";
    }
    if($page_token) {
        $params['pageToken'] = $page_token;
    }

    $url = "https://gmail.googleapis.com/gmail/v1/users/me/messages";
    if(!empty($params)) {
        $url .= '?'.http_build_query($params);
    }

    $http = new \equal\http\HttpRequest("GET $url");

    $response = $http
        ->header("Authorization", "Bearer $access_token")
        ->send();

    $data = $response->body();
    $status = $response->getStatusCode();

    if($status < 200 || $status > 299) {
        trigger_error("APP::Gmail API error: " . json_encode($data), EQ_REPORT_ERROR);
        throw new Exception("gmail_api_error", EQ_ERROR_INVALID_PARAM);
    }

    return [
        'messages'       => $data['messages'] ?? [],
        'nextPageToken'  => $data['nextPageToken'] ?? null
    ];
};

/**
 * Returns a specific email fetched from Gmail API
 *
 * @param string $access_token  Gmail Google API token
 * @param int $id               Gmail API id the email to fetch
 * @return array                HTML body
 * @throws Exception
 */
$getMessage = function($access_token, $id) {
    $url = "https://gmail.googleapis.com/gmail/v1/users/me/messages/$id?format=full";

    $http = new \equal\http\HttpRequest("GET $url");

    $response = $http
        ->header("Authorization", "Bearer $access_token")
        ->send();

    $data = $response->body();
    $status = $response->getStatusCode();

    if($status < 200 || $status > 299) {
        trigger_error("APP::Gmail API error: " . json_encode($data), EQ_REPORT_ERROR);
        throw new Exception("gmail_api_error", EQ_ERROR_INVALID_PARAM);
    }

    return $data;
};

/**
 * Extracts an HTML body from the given Gmail message payload
 *
 * @param array $payload    Gmail API message payload
 * @return string           HTML body
 */
$extractMessageBody = function($payload) use(&$extractMessageBody) {
    // Simple (non-multipart) body
    if(!empty($payload['body']['data'])) {
        return base64_decode(strtr($payload['body']['data'], '-_', '+/'));
    }

    // Multipart — search parts for text/plain or text/html
    $body = '';
    foreach($payload['parts'] ?? [] as $part) {
        // HTML part prioritized
        if($part['mimeType'] === 'text/html' && !empty($part['body']['data'])) {
            return base64_decode(strtr($part['body']['data'], '-_', '+/'));
        }

        if($part['mimeType'] === 'text/plain' && !empty($part['body']['data'])) {
            $body =  base64_decode(strtr($part['body']['data'], '-_', '+/'));
        }

        // Recurse into nested parts
        if(!empty($part['parts'])) {
            $nested = $extractMessageBody($part);
            if($nested) return $nested;
        }
    }

    return $body;
};

/**
 * Extracts first email address found in given string (based on rfc822)
 *
 * @param string $address_header raw imap email address (e.g.: "Google <no-reply@accounts.google.com>", "fmtsolutions.yb@gmail.com")
 * @return mixed|string
 */
$extractEmailAddress = function($address_header) {
    if (preg_match('/<([^>]+)>/', $address_header, $matches)) {
        return $matches[1];
    }
    return filter_var($address_header, FILTER_VALIDATE_EMAIL) ? $address_header : '';
};

/**
 * Extracts attachments from the given Gmail message payload
 *
 * @param array $payload    Gmail API message payload
 * @return array            List of attachements
 */
$extractMessageAttachments = function($payload) use (&$extractMessageAttachments) {
    $attachments = [];

    $filename = $payload['filename'] ?? '';
    $size = $payload['body']['size'] ?? 0;
    $disposition = '';
    foreach($payload['headers'] ?? [] as $header) {
        if(strtolower($header['name']) === 'content-disposition') {
            $disposition = strtolower($header['value']);
            break;
        }
    }

    $is_attachment = str_contains($disposition, 'attachment') && $filename;
    if($is_attachment && $size > 0) {
        $attachment_id = $payload['body']['attachmentId'] ?? null;
        $inline_data = $payload['body']['data'] ?? null;

        $attachments[] = [
            'filename'      => $filename,
            'mimeType'      => $payload['mimeType'] ?? 'application/octet-stream',
            'size'          => $size,
            'attachmentId'  => $attachment_id,  // null if inline
            'data'          => $inline_data,    // null if external
        ];
    }

    foreach($payload['parts'] ?? [] as $part) {
        $sub_attachments = $extractMessageAttachments($part);
        foreach($sub_attachments as $sub_attachment) {
            $attachments[] = $sub_attachment;
        }
    }

    return $attachments;
};

/**
 * Returns a specific attachment fetched from Gmail API
 *
 * @param string $access_token  Gmail Google API token
 * @param int $message_id       Gmail API email's id of the attachment to fetch
 * @param int $id               Gmail API id of the attachment to fetch
 * @return mixed|string
 * @throws Exception
 */
$getAttachment = function($access_token, $message_id, $id) {
    $url = "https://gmail.googleapis.com/gmail/v1/users/me/messages/$message_id/attachments/$id";

    $http = new \equal\http\HttpRequest("GET $url");

    $response = $http
        ->header("Authorization", "Bearer $access_token")
        ->send();

    $data = $response->body();
    $status = $response->getStatusCode();

    if($status < 200 || $status > 299) {
        trigger_error("APP::Graph API error: " . json_encode($data), EQ_REPORT_ERROR);
        throw new Exception("graph_api_error", EQ_ERROR_INVALID_PARAM);
    }

    return $data['data'] ?? '';
};

/**********
 * Action *
 **********/

$allowed_mime_types = [
        'text/xml',
        'application/xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

$max_messages_per_fetch = 50;
$gmail_page_size = 50;

// check consistency
$mailbox = Mailbox::id($params['id'])
    ->read(['status', 'auth_type', 'access_token', 'access_token_expiry', 'refresh_token_expiry', 'date_last_sync'])
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

try {
    if($mailbox['refresh_token_expiry'] < time()) {
        // #todo - dispatch an alert to notify user to re-connect
        throw new Exception("expired_oauth_refresh_token", EQ_ERROR_INVALID_PARAM);
    }

    if($mailbox['access_token_expiry'] < time()) {
        eQual::run('do', 'communication_email_Mailbox_refresh-token-gmail', ['id' => $params['id']]);

        $mailbox = Mailbox::id($params['id'])
            ->read(['status', 'auth_type', 'access_token', 'access_token_expiry', 'refresh_token_expiry', 'date_last_sync'])
            ->first();
    }
}
catch(Exception $e) {
    // refresh token failed : force the need for OAuth renewal
    Mailbox::id($params['id'])->update(['status' => 'pending']);
    trigger_error("Gmail OAuth token expired: " . $e->getMessage(), EQ_REPORT_ERROR);
    throw new \Exception('expired_oauth_token', EQ_ERROR_UNKNOWN);
}

$new_date_last_sync = time();
$imported_messages_count = 0;
$fetch_limit_reached = false;
$next_page_token = null;
$message_refs = [];
$last_processed_message_date = null;

do {
    $page = $getMessagesPage($mailbox['access_token'], $mailbox['date_last_sync'], $next_page_token, $gmail_page_size);

    foreach($page['messages'] as $message_ref) {
        $message_refs[] = $message_ref;
    }

    $next_page_token = $page['nextPageToken'];
}
while($next_page_token);

$message_refs = array_reverse($message_refs);

foreach($message_refs as $message_ref) {
    $message_id = $message_ref['id'];

    // skip already imported
    if(Email::search(['message_id', '=', $message_id])->first()) {
        continue;
    }

    $message = $getMessage($mailbox['access_token'], $message_id);

    $headers = [
        'Subject'   => '(no subject)',
        'From'      => '',
        'To'        => '',
        'Date'      => '',
    ];
    foreach($message['payload']['headers'] as $header) {
        if(in_array($header['name'], ['Subject', 'From', 'To', 'Date'])) {
            $headers[$header['name']] = $header['value'];
        }
    }

    $body = $extractMessageBody($message['payload']);
    $message_date = strtotime($headers['Date']);
    $message_internal_date = !empty($message['internalDate']) ? intval($message['internalDate'] / 1000) : null;

    $email = Email::create([
            'mailbox_id'    => $mailbox['id'],
            'message_id'    => $message_id,
            'subject'       => substr($headers['Subject'], 0, 255),
            'from'          => $extractEmailAddress($headers['From']),
            'to'            => $extractEmailAddress($headers['To']),
            'direction'     => 'incoming',
            'date'          => $message_date,
            'body'          => $body
        ])
        ->read(['thread_hash'])
        ->first();

    $attachments = $extractMessageAttachments($message['payload']);

    foreach($attachments as $attachment) {
        if(!in_array($attachment['mimeType'], $allowed_mime_types, true)) {
            continue;
        }

        if(!$attachment['data'] && !$attachment['attachmentId']) {
            continue;
        }

        $data = $attachment['data'];
        if(!$data && $attachment['attachmentId']) {
            $data = $getAttachment($mailbox['access_token'], $message_id, $attachment['attachmentId']);
        }

        Document::create([
                'name'     => $attachment['filename'],
                'data'     => base64_decode($data),
                'email_id' => $email['id']
            ])
            ->do('start_processing');
    }

    ++$imported_messages_count;
    if($message_internal_date !== null) {
        $last_processed_message_date = $message_internal_date;
    }

    if($imported_messages_count >= $max_messages_per_fetch) {
        $fetch_limit_reached = true;
        break;
    }
}

if($fetch_limit_reached && $last_processed_message_date !== null) {
    Mailbox::id($mailbox['id'])->update(['date_last_sync' => max(0, $last_processed_message_date - 1)]);
}
else {
    Mailbox::id($mailbox['id'])->update(['date_last_sync' => $new_date_last_sync]);
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
