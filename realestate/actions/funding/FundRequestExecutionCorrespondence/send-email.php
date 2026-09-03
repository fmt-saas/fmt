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
use realestate\funding\FundRequest;
use realestate\funding\FundRequestExecutionCorrespondence;
use realestate\management\ManagementProcess;
use realestate\ownership\OwnershipCommunicationPreference;

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
    $signature = $organisation['signature'] ?? '';
}

$fundRequestExecutionCorrespondence = FundRequestExecutionCorrespondence::id($params['id'])
    ->read([
        'condo_id' => ['id', 'name'],
        'name',
        'communication_method',
        'is_sent',
        'owner_id' => ['firstname', 'lastname', 'lang_id'],
        'ownership_id' => ['id', 'name'],
        'fund_request_execution_id' => ['name', 'due_date', 'fund_request_id'],
        'document_id'
    ])
    ->first();

if(!$fundRequestExecutionCorrespondence) {
    throw new Exception('unknown_fund_request_execution_correspondence', EQ_ERROR_INVALID_PARAM);
}

if($fundRequestExecutionCorrespondence['communication_method'] !== 'email') {
    throw new Exception('invalid_communication_method', EQ_ERROR_INVALID_PARAM);
}

if($fundRequestExecutionCorrespondence['is_sent']) {
    throw new Exception('correspondence_already_sent', EQ_ERROR_INVALID_PARAM);
}

if(!$fundRequestExecutionCorrespondence['document_id']) {
    throw new Exception('missing_fund_request_execution_document', EQ_ERROR_INVALID_PARAM);
}

$fundRequest = FundRequest::id($fundRequestExecutionCorrespondence['fund_request_execution_id']['fund_request_id'])->read(['request_type'])->first();

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

$map_types_translations = [
    'fr' => [
        'working_fund'        => 'fonds de roulement',
        'reserve_fund'        => 'fonds de réserve',
        'special_reserve_fund'=> 'fonds de réserve spécial',
        'expense_provisions'  => 'provisions pour charge',
        'work_provisions'     => 'provision pour charge exceptionnelle'
    ],
];

foreach($template['parts_ids'] as $part) {
    if($part['name'] === 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'fund_request_execution' => $fundRequestExecutionCorrespondence['fund_request_execution_id']['name'],
            'condo'                  => $fundRequestExecutionCorrespondence['condo_id']['name'],
            'due_date'               => $due_date,
            'type'                   => $map_types_translations['fr'][$fundRequest['request_type']]
        ];

        $subject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $subject);
    }
    elseif($part['name'] === 'body') {
        $body = $part['value'];

        $map_values = [
            'firstname'              => $fundRequestExecutionCorrespondence['owner_id']['firstname'],
            'lastname'               => $fundRequestExecutionCorrespondence['owner_id']['lastname'],
            'condo'                  => $fundRequestExecutionCorrespondence['condo_id']['name'],
            'due_date'               => $due_date,
            'type'                   => $map_types_translations['fr'][$fundRequest['request_type']],
            'fund_request_execution' => $fundRequestExecutionCorrespondence['fund_request_execution_id']['name']
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
        ['condo_id', '=', $fundRequestExecutionCorrespondence['condo_id']['id']],
        ['ownership_id', '=', $fundRequestExecutionCorrespondence['ownership_id']['id']],
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
    'realestate\funding\FundRequestExecutionCorrespondence',
    $fundRequestExecutionCorrespondence['id']
);

if(!$email_id) {
    throw new Exception('email_not_queued', EQ_ERROR_INVALID_CONFIG);
}

Email::id($email_id)->update([
    'mailbox_id'                => $managementProcess['mailbox_id'],
    'attachment_documents_ids'  => [ $fundRequestExecutionCorrespondence['document_id'] ]
]);

FundRequestExecutionCorrespondence::id($fundRequestExecutionCorrespondence['id'])
    ->update([
        'sent_date' => time(),
        'is_sent'   => true
    ]);

$context->httpResponse()
        ->status(201)
        ->send();
