<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use core\Mail;
use equal\email\Email;
use identity\Organisation;
use realestate\funding\PaymentReminder;
use fmt\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => "Send a single email for a given funding reminder.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\funding\PaymentReminder',
            'description'      => "Identifier of the funding reminder."
        ],
        'ids' => [
            'type'              => 'one2many',
            'foreign_object'    => 'realestate\funding\PaymentReminder',
            'description'       => 'List of reminders IDs for which we want to send emails.',
            'default'           => []
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
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

if(isset($params['id']) && $params['id'] === 0) {
    unset($params['id']);
}

$ids = array_merge((isset($params['id']) ? [$params['id']] : []), $params['ids'] ?? []);

if(empty($ids)) {
    throw new Exception("missing_id", EQ_ERROR_INVALID_PARAM);
}

$reminders = PaymentReminder::ids($ids)
    ->read([
        'status',
        'due_date',
        'due_amount',
        'condo_id' => [
            'name'
        ],
        'ownership_id' => [
            'representative_owner_id' => [
                'firstname',
                'lastname',
                'email',
                'email_alt',
                'address_recipient'
            ],
            'owners_ids' => [
                'firstname',
                'lastname',
                'email',
                'email_alt',
                'address_recipient'
            ]
        ]
    ])
    ->get();

if(count($ids) !== count($reminders)) {
    throw new Exception("payment_reminder_not_found", EQ_ERROR_UNKNOWN_OBJECT);
}

foreach($reminders as $reminder) {
    if($reminder['status'] !== 'not_sent') {
        throw new Exception("payment_reminder_{$reminder['status']}", EQ_ERROR_UNKNOWN_OBJECT);
    }
}

foreach($reminders as $reminder) {
    $owner = null;
    if($reminder['ownership_id']['representative_owner_id']) {
        $representative_owner = $reminder['ownership_id']['representative_owner_id'];
        if(!empty($representative_owner['email']) || !empty($representative_owner['email_alt'])) {
            $owner = $representative_owner;
        }
    }
    if(!$owner) {
        foreach($reminder['ownership_id']['owners_ids'] as $owner) {
            if(!empty($representative_owner['email']) || !empty($representative_owner['email_alt'])) {
                $owner = $representative_owner;
                break;
            }
        }
    }
    $recipient_email = !empty($owner['email']) ? $owner['email'] : $owner['email_alt'];

    if(!$owner) {
        throw new Exception("owner_not_found", EQ_ERROR_INVALID_PARAM);
    }

    // generate signature
    $organisation = Organisation::id(1)->read(['signature'])->first();

    $signature = '';

    if($organisation) {
        $signature = $organisation['signature'];
    }

    // retrieve template (subject & body)
    $subject = '';
    $body = '';

    $template_code = 'payment_reminder';

    $template = Template::search([
            ['code', '=', $template_code],
            ['type', '=', 'email']
        ])
        ->read( ['id','parts_ids' => ['name', 'value']])
        ->first(true);

    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

    foreach($template['parts_ids'] as $part_id => $part) {
        if($part['name'] == 'subject') {
            $subject = strip_tags($part['value']);

            $map_values = [
                'due_date'      => date($date_format, $reminder['due_date']),
                'due_amount'    => Setting::format_number_currency($reminder['due_amount']),
                'condo'         => $reminder['condo_id']['name']
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
                'due_date'              => date($date_format, $reminder['due_date']),
                'due_amount'            => Setting::format_number_currency($reminder['due_amount']),
                'condo'                 => $reminder['condo_id']['name'],
                'firstname'             => $owner['firstname'],
                'lastname'              => $owner['lastname'],
                'address_recipient'     => $owner['address_recipient']
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

    $message = new Email();
    $message->setTo($recipient_email)
        ->setSubject($subject)
        ->setContentType("text/html")
        ->setBody($body);

    $mail_id = Mail::queue($message, 'realestate\funding\PaymentReminder', $reminder['id']);

    PaymentReminder::id($reminder['id'])->update(['status' => 'sent']);
}

$context
    ->httpResponse()
    ->status(201)
    ->send();
