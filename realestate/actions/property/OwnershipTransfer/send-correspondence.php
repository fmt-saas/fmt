<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Email;
use equal\email\Email as EmailMessage;
use equal\email\EmailAttachment;
use fmt\core\Mail;
use core\Lang;

use documents\Document;
use documents\DocumentType;
use documents\navigation\Node;
use realestate\management\ManagementProcess;
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
        'condo_id' => ['id', 'name'],
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

// create message
$message = new EmailMessage();
$message->setTo($recipient_email)
        ->setSubject("Demande d’informations / Convention de cession du droit de propriété")
        ->setContentType("text/html")
        ->setBody("
            <p>Bonjour,</p>
            <p>
                Dans le cadre de la perspective de vente d’un ou plusieurs lots situés au sein de la copropriété {$ownershipTransfer['condo_id']['name']}, vous trouverez en pièce jointe les informations disponibles à ce jour concernant la situation de la copropriété et des lots concernés.
            </p>
            <p>
                Nous restons bien entendu à disposition pour toute précision complémentaire que vous jugeriez utile dans le cadre de la suite de la procédure.
            </p>
            <p>
                Bien cordialement,<br />
                <strong>L’équipe de gestion</strong><br />
                <em>[Nom de l’organisation ou du syndic]</em><br />
            </p>
        ");

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

$context->httpResponse()
        ->status(204)
        ->send();
