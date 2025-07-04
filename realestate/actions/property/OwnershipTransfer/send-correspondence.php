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
    'constants'     => ['EMAIL_SMTP_ACCOUNT_EMAIL'],
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

$ownershipTransfer = OwnershipTransfer::id($params['id'])
    ->read(['condo_id' => ['id', 'name'], 'old_ownership_id' => ['name'], 'status', 'attached_documents_ids', 'contacts_ids' => ['email']])
    ->first(true);

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
        'name'                  => 'Courrier de Mutation - ' . $ownershipTransfer['condo_id']['name'] . ' - ' . $ownershipTransfer['old_ownership_id']['name'] . ' - ' . $ownershipTransfer['status'],
        'data'                  => $data,
        'document_type_id'      => $documentType['id'],
        // link document to ownership transfer
        'ownership_transfer_id' => $ownershipTransfer['id']
    ])
    // assign condo_id in a second pass, so that document is sent to EDMS
    ->update(['condo_id' => $ownershipTransfer['condo_id']['id']])
    ->first();


$attachment_documents_ids = $ownershipTransfer['attached_documents_ids'];

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

$recipients_emails = array_map(function ($a) { return $a['email']; }, $ownershipTransfer['contacts_ids']);
$recipient_email = array_shift($recipients_emails);

// #todo - bypass real recipient while testing
$recipient_email = constant('EMAIL_SMTP_ACCOUNT_EMAIL');
$sender_email = constant('EMAIL_SMTP_ACCOUNT_EMAIL');

// create message
$message = new Email();
$message->setTo($recipient_email)
        ->setReplyTo($sender_email)
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

/*
// #todo - don't send while testing
if(count($recipients_emails)) {
    foreach($recipients_emails as $email) {
        $message->addCc($email);
    }
}
*/

// append attachments to message
foreach($attachments as $attachment) {
    $message->addAttachment($attachment);
}

// queue message
Mail::queue($message, 'realestate\property\OwnershipTransfer', $ownershipTransfer['id']);


$context->httpResponse()
        ->body($result)
        ->send();
