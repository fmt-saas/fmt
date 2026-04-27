<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use core\Mail;
use equal\email\Email;
use equal\email\EmailAttachment;
use identity\Organisation;
use realestate\funding\PaymentReminderCorrespondence;

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
    $signature = $organisation['signature'];
}

$paymentReminderCorrespondence = PaymentReminderCorrespondence::id($params['id'])
    ->read([
        'condo_id' => ['name'],
        'name',
        'communication_method',
        'owner_id' => ['firstname', 'lastname', 'email', 'email_alt', 'lang_id'],
        'ownership_id' => ['name'],
        'payment_reminder_id' => ['name', 'emission_date', 'due_date'],
        'document_id' => ['data']
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
        ['code', '=', 'fund_request_execution_correspondence'],
        ['type', '=', 'email']
    ])
    ->read(['id', 'parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part) {
    if($part['name'] === 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'fund_request' => $paymentReminderCorrespondence['payment_reminder_id']['name'],
            'condo'        => $paymentReminderCorrespondence['condo_id']['name'],
            'date'         => $paymentReminderCorrespondence['payment_reminder_id']['due_date']
        ];

        $subject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $subject);
    }
    elseif($part['name'] === 'body') {
        $body = $part['value'];

        $map_values = [
            'firstname' => $paymentReminderCorrespondence['owner_id']['firstname'],
            'lastname'  => $paymentReminderCorrespondence['owner_id']['lastname'],
            'condo'     => $paymentReminderCorrespondence['condo_id']['name'],
            'date'      => $paymentReminderCorrespondence['payment_reminder_id']['due_date']
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

$recipient_email = $paymentReminderCorrespondence['owner_id']['email']
    ?? $paymentReminderCorrespondence['owner_id']['email_alt']
    ?? null;

$attachments = [];
$main_attachment_name = 'Rappel de paiement - ' . $paymentReminderCorrespondence['condo_id']['name'] . ' - ' . $paymentReminderCorrespondence['ownership_id']['name'];
$attachments[] = new EmailAttachment($main_attachment_name . '.pdf', (string) $paymentReminderCorrespondence['document_id']['data'], 'application/pdf');

$message = new Email();
$message->setTo($recipient_email)
        ->setSubject($subject)
        ->setContentType('text/html')
        ->setBody($body);

foreach($attachments as $attachment) {
    $message->addAttachment($attachment);
}

Mail::queue($message, 'realestate\\funding\\PaymentReminderCorrespondence', $paymentReminderCorrespondence['id']);

PaymentReminderCorrespondence::id($paymentReminderCorrespondence['id'])
    ->update(['sent_date' => time()])
    ->update(['is_sent' => true]);

$context->httpResponse()
        ->status(201)
        ->send();
