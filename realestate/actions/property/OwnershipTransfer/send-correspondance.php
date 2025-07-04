<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\email\Email;
use equal\email\EmailAttachment;
use core\Mail;
use core\Lang;

use documents\Document;
use documents\DocumentType;
use documents\navigation\Node;
use realestate\property\OwnershipTransfer;


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
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$ownershipTransfer = OwnershipTransfer::id($params['id'])->read(['condo_id'])->first();

if(!$ownershipTransfer) {
    throw new Exception('unknown_ownership_transfer', EQ_ERROR_UNKNOWN_OBJECT);
}

$documentType = DocumentType::search(['code', '=', 'ownership_transfer_correspondence'])
    ->read(['folder_code'])
    ->first();

if(!$documentType) {
    throw new Exception('unknown_document_type', EQ_ERROR_INVALID_CONFIG);
}

// generate PDF content, using render-pdf
$data = eQual::run('get', 'realestate_property_OwnershipTransfer_render-pdf', ['id' => $params['id']], false, true);

// create a Document (no processing)
$document = Document::create([
        'name' => $documentProcess['name'],
        'data' => $data,
        'document_type_id' => $documentType['id'],
        // link document to ownership transfer
        'ownership_transfer_id' => $ownershipTransfer['id']
    ])
    // assign condo_id in a second pass, so that document is sent to EDMS
    ->update(['condo_id' => $ownershipTransfer['condo_id']])
    ->first();

// generate an email for sending

$attachment_documents_ids = [];

$attachment_documents_ids[] = $document['id'];

$map_processed_documents_ids = [];

/** @var EmailAttachment[] */
$emailAttachments = [];

// add attachments based on documents selected in ownership transfer file

foreach($attachment_documents_ids as $document_id) {
    if(isset($map_processed_documents_ids[$document_id])) {
        continue;
    }
    $map_processed_documents_ids[$document_id] = true;

    $document = Document::id($document_id)->read(['name', 'data', 'content_type']);
    $emailAttachments[] = new EmailAttachment($document['name'], $document['data'], $document['content_type']);
}

// create message
$message = new Email();
$message->setTo($params['recipient_email'])
        ->setReplyTo($params['sender_email'])
        ->setSubject($params['title'])
        ->setContentType("text/html")
        ->setBody($params['message']);


// if testing, send all emails to outgoing test address

$bcc = isset($booking['center_id']['center_office_id']['email_bcc']) ? $booking['center_id']['center_office_id']['email_bcc'] : '';

if(strlen($bcc)) {
    $message->addBcc($bcc);
}

if(isset($params['recipients_emails'])) {
    $recipients_emails = array_diff($params['recipients_emails'], (array) $params['recipient_email']);
    foreach($recipients_emails as $address) {
        $message->addCc($address);
    }
}

// append attachments to message
foreach($attachments as $attachment) {
    $message->addAttachment($attachment);
}

// queue message
Mail::queue($message, 'realestate\property\OwnershipTransfer', $ownershipTransfer['id']);


$context->httpResponse()
        ->body($result)
        ->send();
