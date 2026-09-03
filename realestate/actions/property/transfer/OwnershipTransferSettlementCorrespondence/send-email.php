<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Email;
use communication\template\Template;
use equal\email\Email as EmailMessage;
use fmt\core\Mail;
use identity\Organisation;
use realestate\management\ManagementProcess;
use realestate\ownership\OwnershipCommunicationPreference;
use realestate\property\transfer\OwnershipTransferSettlementCorrespondence;

[$params, $providers] = eQual::announce([
    'description' => 'Queue one ownership-transfer settlement correspondence email.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementCorrespondence',
            'description'    => 'Email settlement correspondence to send.',
            'required'       => true
        ]
    ],
    'response'  => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context']
]);

['context' => $context] = $providers;

$correspondence = OwnershipTransferSettlementCorrespondence::id($params['id'])
    ->read([
        'recipient_role',
        'communication_method',
        'is_sent',
        'document_id',
        'condo_id',
        'ownership_id',
        'owner_id' => ['firstname', 'lastname'],
        'settlement_id' => [
            'name',
            'transfer_date',
            'condo_id' => ['name']
        ]
    ])
    ->first();

if(!$correspondence) {
    throw new Exception('unknown_settlement_correspondence', EQ_ERROR_UNKNOWN_OBJECT);
}
if($correspondence['communication_method'] !== 'email') {
    throw new Exception('invalid_communication_method', EQ_ERROR_INVALID_PARAM);
}
if($correspondence['is_sent']) {
    throw new Exception('correspondence_already_sent', EQ_ERROR_INVALID_PARAM);
}
if(!$correspondence['document_id']) {
    throw new Exception('missing_correspondence_document', EQ_ERROR_INVALID_CONFIG);
}

$communicationPreference = OwnershipCommunicationPreference::search([
        ['condo_id', '=', $correspondence['condo_id']],
        ['ownership_id', '=', $correspondence['ownership_id']],
        ['communication_reason', '=', 'technical_communication'],
        ['has_channel_email', '=', true]
    ])
    ->read(['email', 'email_alt'])
    ->first();

$recipient_email = ($communicationPreference['email'] ?? null)
    ?: ($communicationPreference['email_alt'] ?? null);
if(!$recipient_email) {
    throw new Exception('missing_mandatory_email', EQ_ERROR_INVALID_CONFIG);
}

$role = $correspondence['recipient_role'];
$template_code = "ownership_transfer_settlement_{$role}_correspondence";
$subject = $role === 'seller'
    ? 'Régularisation comptable de votre mutation'
    : 'Régularisation comptable de votre acquisition';
$body = $role === 'seller'
    ? '<p>Veuillez trouver en annexe la régularisation comptable établie à la suite de votre mutation.</p>'
    : '<p>Veuillez trouver en annexe la régularisation comptable établie à la suite de votre acquisition.</p>';

$template = Template::search([
        ['code', '=', $template_code],
        ['type', '=', 'email']
    ])
    ->read(['parts_ids' => ['name', 'value']])
    ->first(true);

if($template) {
    foreach($template['parts_ids'] as $part) {
        if($part['name'] === 'subject') {
            $subject = strip_tags($part['value']);
        }
        elseif($part['name'] === 'body') {
            $body = $part['value'];
        }
    }
}

$map_values = [
    'firstname' => $correspondence['owner_id']['firstname'],
    'lastname'  => $correspondence['owner_id']['lastname'],
    'condo'     => $correspondence['settlement_id']['condo_id']['name'],
    'settlement'=> $correspondence['settlement_id']['name'],
    'date'      => date('d/m/Y', $correspondence['settlement_id']['transfer_date'])
];

$interpolate = static function($matches) use ($map_values) {
    return $map_values[$matches[1]] ?? '';
};
$subject = preg_replace_callback('/\{(\w+)\}/', $interpolate, $subject);
$body = preg_replace_callback('/\{(\w+)\}/', $interpolate, $body);

$organisation = Organisation::id(1)->read(['signature'])->first();
if($organisation && $organisation['signature']) {
    $body .= '<br><br>' . $organisation['signature'];
}

$message = new EmailMessage();
$message->setTo($recipient_email)
    ->setSubject($subject)
    ->setContentType('text/html')
    ->setBody($body);

$managementProcess = ManagementProcess::search(['code', '=', 'legal'])
    ->read(['mailbox_id'])
    ->first();
if(!$managementProcess || !$managementProcess['mailbox_id']) {
    throw new Exception('missing_mandatory_mailbox', EQ_ERROR_INVALID_CONFIG);
}

$email_id = Mail::queue(
    $message,
    'realestate\property\transfer\OwnershipTransferSettlementCorrespondence',
    $correspondence['id']
);
if(!$email_id) {
    throw new Exception('email_not_queued', EQ_ERROR_INVALID_CONFIG);
}

Email::id($email_id)->update([
    'mailbox_id'               => $managementProcess['mailbox_id'],
    'attachment_documents_ids' => [$correspondence['document_id']]
]);

OwnershipTransferSettlementCorrespondence::id($correspondence['id'])
    ->update(['sent_date' => time()])
    ->update(['is_sent' => true]);

$context->httpResponse()
    ->status(201)
    ->send();

