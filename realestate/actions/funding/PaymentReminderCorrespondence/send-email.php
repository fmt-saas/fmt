<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Email;
use communication\template\Template;
use fmt\core\Mail;
use equal\email\Email as EmailMessage;
use equal\email\EmailAttachment;
use identity\Organisation;
use realestate\funding\PaymentReminderCorrespondence;
use realestate\management\ManagementProcess;
use realestate\ownership\OwnershipCommunicationPreference;

[$params, $providers] = eQual::announce([
    'description'   => "Send a single email for a given Payment Reminder correspondence.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\funding\PaymentReminderCorrespondence',
            'description'      => 'Identifier of the Payment Reminder correspondence.',
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

if(!isset($params['id'])) {
    throw new Exception('missing_id', EQ_ERROR_INVALID_PARAM);
}

$organisation = Organisation::id(1)->read(['signature'])->first();
$signature = '';
if($organisation) {
    $signature = $organisation['signature'] ?? '';
}

$paymentReminderCorrespondence = PaymentReminderCorrespondence::id($params['id'])
    ->read([
        'condo_id' => ['id', 'name'],
        'name',
        'communication_method',
        'owner_id' => ['firstname', 'lastname', 'lang_id'],
        'ownership_id' => ['id', 'name'],
        'payment_reminder_id' => ['name', 'emission_date', 'due_amount'],
        'payment_reminder_owner_id' => ['due_amount'],
        'document_id'
    ])
    ->first();

if(!$paymentReminderCorrespondence) {
    throw new Exception('unknown_payment_reminder_correspondence', EQ_ERROR_INVALID_PARAM);
}

if($paymentReminderCorrespondence['communication_method'] !== 'email') {
    throw new Exception('invalid_communication_method', EQ_ERROR_INVALID_PARAM);
}

if(!$paymentReminderCorrespondence['document_id']) {
    throw new Exception('missing_payment_reminder_document', EQ_ERROR_INVALID_PARAM);
}

$subject = '';
$body = '';

$template = Template::search([
        ['code', '=', 'payment_reminder_correspondence'],
        ['type', '=', 'email']
    ])
    ->read(['id', 'parts_ids' => ['name', 'value']])
    ->first(true);

$emission_date = '';
if($paymentReminderCorrespondence['payment_reminder_id']['emission_date']) {
    $emission_date = date('d/m/Y', $paymentReminderCorrespondence['payment_reminder_id']['emission_date']);
}

foreach($template['parts_ids'] as $part) {
    if($part['name'] === 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'payment_reminder' => $paymentReminderCorrespondence['payment_reminder_id']['name'],
            'condo'            => $paymentReminderCorrespondence['condo_id']['name'],
            'emission_date'    => $emission_date,
            'due_amount'       => number_format((float) ($paymentReminderCorrespondence['payment_reminder_owner_id']['due_amount'] ?? 0), 2, ',', '.') . ' €'
        ];

        $subject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $subject);
    }
    elseif($part['name'] === 'body') {
        $body = $part['value'];

        $map_values = [
            'firstname'        => $paymentReminderCorrespondence['owner_id']['firstname'],
            'lastname'         => $paymentReminderCorrespondence['owner_id']['lastname'],
            'condo'            => $paymentReminderCorrespondence['condo_id']['name'],
            'emission_date'    => $emission_date,
            'due_amount'       => number_format((float) ($paymentReminderCorrespondence['payment_reminder_owner_id']['due_amount'] ?? 0), 2, ',', '.') . ' €'
        ];

        $body = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $body);

        if(strlen($signature)) {
            $body .= '<br><br>' . $signature;
        }
    }
}

$communicationPreference = OwnershipCommunicationPreference::search([
        ['condo_id', '=', $paymentReminderCorrespondence['condo_id']['id']],
        ['ownership_id', '=', $paymentReminderCorrespondence['ownership_id']['id']],
        ['communication_reason', '=', 'fund_request'],
        ['has_channel_email', '=', true]
    ])
    ->read(['email', 'email_alt'])
    ->first();

$recipient_email = ($communicationPreference['email'] ?? null)
    ?: ($communicationPreference['email_alt'] ?? null);

if(!$recipient_email || $recipient_email === '') {
    throw new \Exception('missing_mandatory_email', EQ_ERROR_INVALID_CONFIG);
}

$message = new EmailMessage();
$message->setTo($recipient_email)
        ->setSubject($subject)
        ->setContentType('text/html')
        ->setBody($body);

$managementProcess = ManagementProcess::search(['code', '=', 'finance'])->read(['mailbox_id'])->first();
if(!$managementProcess || !$managementProcess['mailbox_id']) {
    throw new Exception('missing_mandatory_mailbox', EQ_ERROR_INVALID_CONFIG);
}

$email_id = Mail::queue(
    $message,
    'realestate\funding\PaymentReminderCorrespondence',
    $paymentReminderCorrespondence['id']
);

Email::id($email_id)->update([
    'mailbox_id'                => $managementProcess['mailbox_id'],
    'attachment_documents_ids'  => [ $paymentReminderCorrespondence['document_id'] ]
]);

PaymentReminderCorrespondence::id($paymentReminderCorrespondence['id'])
    ->update(['sent_date' => time()])
    ->update(['is_sent' => true]);

$context->httpResponse()
        ->status(201)
        ->send();
