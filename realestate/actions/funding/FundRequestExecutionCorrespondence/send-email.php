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
use realestate\funding\FundRequestExecutionCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Send a single email for a given Fund Request Execution correspondence.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\funding\FundRequestExecutionCorrespondence',
            'description'      => 'Identifier of the fund request execution correspondence.',
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

if(!isset($params['id'])) {
    throw new Exception('missing_id', EQ_ERROR_INVALID_PARAM);
}

$organisation = Organisation::id(1)->read(['signature'])->first();
$signature = '';
if($organisation) {
    $signature = $organisation['signature'];
}

$fundRequestExecutionCorrespondence = FundRequestExecutionCorrespondence::id($params['id'])
    ->read([
        'condo_id' => ['name'],
        'name',
        'communication_method',
        'owner_id' => ['firstname', 'lastname', 'email', 'email_alt', 'lang_id'],
        'ownership_id' => ['name'],
        'fund_request_execution_id' => ['name', 'due_date'],
        'document_id' => ['data']
    ])
    ->first();

if(!$fundRequestExecutionCorrespondence) {
    throw new Exception('unknown_fund_request_execution_correspondence', EQ_ERROR_INVALID_PARAM);
}

if($fundRequestExecutionCorrespondence['communication_method'] !== 'email') {
    throw new Exception('invalid_communication_method', EQ_ERROR_INVALID_PARAM);
}

if(!$fundRequestExecutionCorrespondence['document_id']) {
    throw new Exception('missing_fund_request_execution_document', EQ_ERROR_INVALID_PARAM);
}

$subject = '';
$body = '';

$template = Template::search([
        ['code', '=', 'fund_request_execution_correspondence'],
        ['type', '=', 'email']
    ])
    ->read(['id', 'parts_ids' => ['name', 'value']])
    ->first(true);

$due_date = '';
if($fundRequestExecutionCorrespondence['fund_request_execution_id']['due_date']) {
    $due_date = date('d/m/Y', $fundRequestExecutionCorrespondence['fund_request_execution_id']['due_date']);
}

foreach($template['parts_ids'] as $part) {
    if($part['name'] === 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'fund_request_execution' => $fundRequestExecutionCorrespondence['fund_request_execution_id']['name'],
            'condo'                  => $fundRequestExecutionCorrespondence['condo_id']['name'],
            'date'                   => $due_date
        ];

        $subject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $subject);
    }
    elseif($part['name'] === 'body') {
        $body = $part['value'];

        $map_values = [
            'firstname'             => $fundRequestExecutionCorrespondence['owner_id']['firstname'],
            'lastname'              => $fundRequestExecutionCorrespondence['owner_id']['lastname'],
            'condo'                 => $fundRequestExecutionCorrespondence['condo_id']['name'],
            'date'                  => $due_date,
            'fund_request_execution'=> $fundRequestExecutionCorrespondence['fund_request_execution_id']['name']
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

$recipient_email = $fundRequestExecutionCorrespondence['owner_id']['email']
    ?? $fundRequestExecutionCorrespondence['owner_id']['email_alt']
    ?? null;

$attachments = [];
$main_attachment_name = 'Appel de fonds - ' . $fundRequestExecutionCorrespondence['condo_id']['name'] . ' - ' . $fundRequestExecutionCorrespondence['ownership_id']['name'];
$attachments[] = new EmailAttachment($main_attachment_name . '.pdf', (string) $fundRequestExecutionCorrespondence['document_id']['data'], 'application/pdf');

$message = new Email();
$message->setTo($recipient_email)
        ->setSubject($subject)
        ->setContentType('text/html')
        ->setBody($body);

foreach($attachments as $attachment) {
    $message->addAttachment($attachment);
}

Mail::queue($message, 'realestate\funding\FundRequestExecutionCorrespondence', $fundRequestExecutionCorrespondence['id']);

FundRequestExecutionCorrespondence::id($fundRequestExecutionCorrespondence['id'])
    ->update(['sent_date' => time()])
    ->update(['is_sent' => true]);

$context->httpResponse()
        ->status(201)
        ->send();
