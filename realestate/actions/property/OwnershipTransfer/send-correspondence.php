<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Email;
use communication\template\Template;
use equal\email\Email as EmailMessage;
use equal\email\EmailAttachment;
use fmt\core\Mail;
use core\Lang;

use documents\Document;
use documents\DocumentType;
use documents\navigation\Node;
use realestate\management\ManagementProcess;
use realestate\property\OwnershipTransfer;
use realestate\property\OwnershipTransferHistoryEntry;


[$params, $providers] = eQual::announce([
    'description'   => 'Send a correspondence of an ownership transfer, according to its status.',
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'description'       => "The ownership transfer the correspondence refers to.",
            'foreign_object'    => 'realestate\property\OwnershipTransfer',
            'required'          => true
        ]

    ],
    'constants'     => ['EMAIL_SMTP_ACCOUNT_EMAIL'],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$ownershipTransfer = OwnershipTransfer::id($params['id'])
    ->read([
        'status',
        'ownership_transfer_attachments_ids' => ['document_id'],
        'condo_id' => [
            'id',
            'name',
            'managing_agent_id' => ['name']
        ],
        'old_ownership_id' => ['name'],
        'contacts_ids' => ['email']
    ])
    ->first(true);

if(!$ownershipTransfer) {
    throw new Exception('unknown_ownership_transfer', EQ_ERROR_UNKNOWN_OBJECT);
}

$documentType = DocumentType::search(['code', '=', 'ownership_transfer_document'])
    ->read(['folder_code'])
    ->first();

if(!$documentType) {
    throw new Exception('unknown_document_type', EQ_ERROR_INVALID_CONFIG);
}

// generate PDF content, using render-pdf
$data = eQual::run('get', 'realestate_property_OwnershipTransfer_render-pdf', ['id' => $params['id']], false, true);

// create a Document (no processing)
$document = Document::create([
        'name'                  => 'Courrier de Mutation - ' . $ownershipTransfer['condo_id']['name'] . ' - ' . $ownershipTransfer['old_ownership_id']['name'] . ' - ' . $ownershipTransfer['status'],
        'data'                  => $data,
        'condo_id'              => $ownershipTransfer['condo_id']['id'],
        'document_type_id'      => $documentType['id'],
        'document_visibility'   => 'ownership'
    ])
    ->update([
        // link document to ownership transfer
        'ownership_transfer_id' => $ownershipTransfer['id'],
        'ownership_id'          => $ownershipTransfer['old_ownership_id']['id']
    ])
    ->first();


$attachment_documents_ids = array_column(
    $ownershipTransfer['ownership_transfer_attachments_ids'],
    'document_id'
);
$attachment_documents_ids[] = $document['id'];

$recipients_emails = array_map(function ($a) { return $a['email']; }, $ownershipTransfer['contacts_ids']);
$recipient_email = array_shift($recipients_emails);

if(!$recipient_email || $recipient_email === '') {
    throw new \Exception('missing_mandatory_email', EQ_ERROR_INVALID_CONFIG);
}

$subject = '';
$body = '';

$template = Template::search([
        ['code', '=', 'ownership_transfer_correspondence'],
        ['type', '=', 'email']
    ])
    ->read(['id', 'parts_ids' => ['name', 'value']])
    ->first(true);

$map_values = [
    'condo'          => $ownershipTransfer['condo_id']['name'],
    'managing_agent' => $ownershipTransfer['condo_id']['managing_agent_id']['name'] ?? ''
];

foreach($template['parts_ids'] as $part) {
    if($part['name'] === 'subject') {
        $subject = strip_tags($part['value']);
    }
    elseif($part['name'] === 'body') {
        $body = $part['value'];
    }
}

$interpolate = function ($matches) use ($map_values) {
    $key = $matches[1];
    return $map_values[$key] ?? '';
};

$subject = preg_replace_callback('/\{(\w+)\}/', $interpolate, $subject);
$body = preg_replace_callback('/\{(\w+)\}/', $interpolate, $body);

// create message
$message = new EmailMessage();
$message->setTo($recipient_email)
        ->setSubject($subject)
        ->setContentType("text/html")
        ->setBody($body);

if(count($recipients_emails)) {
    foreach($recipients_emails as $email) {
        $message->addCc($email);
    }
}

$managementProcess = ManagementProcess::search(['code', '=', 'legal'])->read(['mailbox_id'])->first();
if(!$managementProcess || !$managementProcess['mailbox_id']) {
    throw new Exception('missing_mandatory_mailbox', EQ_ERROR_INVALID_CONFIG);
}

// queue message
$email_id = Mail::queue(
    $message,
    'realestate\property\OwnershipTransfer',
    $ownershipTransfer['id']
);

if($email_id === 0) {
    throw new Exception('email_not_queued', EQ_ERROR_INVALID_CONFIG);
}

Email::id($email_id)->update([
        'mailbox_id'                => $managementProcess['mailbox_id'],
        'attachment_documents_ids'  => $attachment_documents_ids
    ]);

OwnershipTransferHistoryEntry::create([
    'ownership_transfer_id' => $ownershipTransfer['id'],
    'email_id'               => $email_id,
    'sent_at'                => time(),
    'transfer_status'        => $ownershipTransfer['status']
]);

$context->httpResponse()
        ->status(204)
        ->send();
