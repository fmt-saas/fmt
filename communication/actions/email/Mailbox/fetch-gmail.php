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
 * Returns the list of ids of email that where received after the given timestamp
 *
 * @param string $access_token  Gmail Google API token
 * @param int $after            Unix timestamp
 * @return int[]                List of messages ids
 * @throws Exception
 */
$getMessagesIds = function($access_token, $after) {
    $messages_ids = [];
    $next_page_token  = null;

    do {
        $params = [
            'q' => "after:$after"
        ];

        if($next_page_token) {
            $params['pageToken'] = $next_page_token;
        }

        $url = "https://gmail.googleapis.com/gmail/v1/users/me/messages?".http_build_query($params);

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

        foreach($data['messages'] as $message) {
            $messages_ids[] = $message['id'];
        }

        $next_page_token = $data['nextPageToken'] ?? null;
    }
    while ($next_page_token);

    return $messages_ids;
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
        trigger_error("APP::Graph API error: " . json_encode($data), EQ_REPORT_ERROR);
        throw new Exception("graph_api_error", EQ_ERROR_INVALID_PARAM);
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
    $html = '';
    foreach($payload['parts'] ?? [] as $part) {
        if($part['mimeType'] === 'text/plain' && !empty($part['body']['data'])) {
            return base64_decode(strtr($part['body']['data'], '-_', '+/'));
        }
        if($part['mimeType'] === 'text/html' && !empty($part['body']['data'])) {
            $html = base64_decode(strtr($part['body']['data'], '-_', '+/'));
        }
        // Recurse into nested parts
        if(!empty($part['parts'])) {
            $nested = $extractMessageBody($part);
            if($nested) return $nested;
        }
    }

    return $html;
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
        throw new Exception("expired_refresh_token", EQ_ERROR_INVALID_PARAM);
    }

    if($mailbox['access_token_expiry'] < time()) {
        eQual::run('do', 'communication_email_Mailbox_refresh-token-gmail', ['id' => $params['id']]);
    }
}
catch(Exception $e) {
    // refresh token failed : force the need for OAuth renewal
    Mailbox::id($params['id'])->update(['status' => 'pending']);
    throw $e;
}

$messages_ids = $getMessagesIds($mailbox['access_token'], $mailbox['date_last_sync']);

foreach($messages_ids as $message_id) {
    $message = $getMessage($mailbox['access_token'], $message_id);

    $headers = $message['payload']['headers'];
    $body = $extractMessageBody($message['payload']);

    $email = Email::create([
        'mailbox_id'    => $mailbox['id'],
        'message_id'    => $message_id,
        'subject'       => substr($headers['Subject'] ?? '(no subject)', 0, 255),
        'from'          => $headers['From'] ?? '',
        'to'            => $headers['To'] ?? '',
        'direction'     => 'incoming',
        'date'          => $headers['Date'] ?? '',
        'body'          => $body
    ])
        ->read(['thread_hash'])
        ->first();

    $attachments = $extractMessageAttachments($message['payload']);

    foreach($attachments as $attachment) {
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
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
