<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use core\Mail;
use identity\Organisation;
use equal\email\Email;
use equal\email\EmailAttachment;
use realestate\governance\AssemblyInvitationCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Send a single email for a given Assembly Invitation.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\governance\AssemblyInvitationCorrespondence',
            'description'      => 'Identifier of the Assembly item (resolution).',
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context',]
]);

/**
 * @var \equal\php\Context                  $context
 */
['context' => $context] = $providers;

if(!isset($params['id'])) {
    throw new Exception("missing_id", EQ_ERROR_INVALID_PARAM);
}

/*
    Sending an email is one possible type of invitation.
    The email is treated as a channel that serves as an envelope to send the personalized General Assembly invitation document.
*/

// generate signature
$organisation = Organisation::id(1)->read(['signature'])->first();

$signature = '';

if($organisation) {
    $signature = $organisation['signature'];
}

$assemblyInvitationCorrespondence = AssemblyInvitationCorrespondence::id($params['id'])
    ->read([
        'condo_id' => ['name'],
        'name',
        'communication_method',
        'owner_id' => ['firstname', 'lastname', 'email', 'email_alt', 'lang_id'],
        'ownership_id' => ['name'],
        'assembly_id' => ['name', 'assembly_date', 'assembly_type', 'is_second_session'],
        'document_id' => ['data']
    ])
    ->first();

if(!$assemblyInvitationCorrespondence) {
    throw new Exception("unknown_assembly_invitation", EQ_ERROR_INVALID_PARAM);
}

if($assemblyInvitationCorrespondence['communication_method'] !== 'email') {
    throw new Exception("invalid_communication_method", EQ_ERROR_INVALID_PARAM);
}

if($assemblyInvitationCorrespondence['is_sent']) {
    throw new Exception("correspondence_already_sent", EQ_ERROR_INVALID_PARAM);
}

// #memo - document is expected to have been generated beforehand
if(!$assemblyInvitationCorrespondence['document_id']) {
    throw new Exception("missing_invite_document", EQ_ERROR_INVALID_PARAM);
}

// retrieve template (subject & body)
$subject = '';
$body = '';

$template_code = 'general_meetings_invitation_correspondence';

if($assemblyInvitationCorrespondence['assembly_id']['is_second_session']) {
    $template_code = 'general_meetings_invitation_second_session_correspondence';
}

$template = Template::search([
        ['code', '=', $template_code],
        ['type', '=', 'email']
    ])
    ->read( ['id','parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'assembly'  => $assemblyInvitationCorrespondence['assembly_id']['name'],
            'condo'     => $assemblyInvitationCorrespondence['condo_id']['name'],
            'date'      => $assemblyInvitationCorrespondence['assembly_id']['assembly_date']
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $subject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $subject);
    }
    elseif($part['name'] == 'body') {
        $body = $part['value'];

        $map_values = [
            'firstname' => $assemblyInvitationCorrespondence['owner_id']['firstname'],
            'lastname'  => $assemblyInvitationCorrespondence['owner_id']['lastname'],
            'condo'     => $assemblyInvitationCorrespondence['condo_id']['name'],
            'date'      => $assemblyInvitationCorrespondence['assembly_id']['assembly_date'],
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $body = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $body);

        if(strlen($signature)) {
            $body .= "<br><br>" . $signature;
        }
    }
}


// retrieve recipient
$recipient_email = $assemblyInvitationCorrespondence['owner_id']['email']
    ?? $assemblyInvitationCorrespondence['owner_id']['email_alt']
    ?? null;

/** @var EmailAttachment[] */
$attachments = [];

$main_attachment_name = 'Invitation Assemblée - ' . $assemblyInvitationCorrespondence['condo_id']['name'] . ' - ' . $assemblyInvitationCorrespondence['ownership_id']['name'];

// push main attachment
$attachments[] = new EmailAttachment($main_attachment_name.'.pdf', (string) $assemblyInvitationCorrespondence['document_id']['data'], 'application/pdf');

// create message
$message = new Email();
$message->setTo($recipient_email)
        ->setSubject($subject)
        ->setContentType("text/html")
        ->setBody($body);

// append attachments to message
foreach($attachments as $attachment) {
    $message->addAttachment($attachment);
}

// queue message
Mail::queue($message, 'realestate\governance\AssemblyInvitationCorrespondence', $assemblyInvitationCorrespondence['id']);


// mark invitation as sent
AssemblyInvitationCorrespondence::id($assemblyInvitationCorrespondence['id'])
    ->update([
        'sent_date'    => time()
    ])
    ->update([
        'is_sent'      => true,
    ]);

$context->httpResponse()
        ->status(201)
        ->send();
