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
use realestate\funding\ExpenseStatementCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Send a single email for a given Expense Statement correspondence.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\funding\ExpenseStatementCorrespondence',
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

$expenseStatementCorrespondence = ExpenseStatementCorrespondence::id($params['id'])
    ->read([
        'condo_id' => ['name'],
        'name',
        'communication_method',
        'owner_id' => ['firstname', 'lastname', 'email', 'email_alt', 'lang_id'],
        'ownership_id' => ['name'],
        'expense_statement_id' => ['name', 'emission_date'],
        'document_id' => ['data']
    ])
    ->first();

if(!$expenseStatementCorrespondence) {
    throw new Exception("unknown_expense_statement_correspondence", EQ_ERROR_INVALID_PARAM);
}

if($expenseStatementCorrespondence['communication_method'] !== 'email') {
    throw new Exception("invalid_communication_method", EQ_ERROR_INVALID_PARAM);
}

// #memo - document is expected to have been generated beforehand
if(!$expenseStatementCorrespondence['document_id']) {
    throw new Exception("missing_invite_document", EQ_ERROR_INVALID_PARAM);
}

// retrieve template (subject & body)
$subject = '';
$body = '';

$template = Template::search([
        ['code', '=', 'general_meetings_call'],
        ['type', '=', 'email']
    ])
    ->read( ['id','parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = $part['value'];

        $map_values = [
            'expense_statement' => $expenseStatementCorrespondence['expense_statement_id']['name'],
            'condo'             => $expenseStatementCorrespondence['condo_id']['name'],
            'date'              => $expenseStatementCorrespondence['expense_statement_id']['emission_date']
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
            'firstname' => $expenseStatementCorrespondence['owner_id']['firstname'],
            'lastname'  => $expenseStatementCorrespondence['owner_id']['lastname'],
            'condo'     => $expenseStatementCorrespondence['condo_id']['name'],
            'date'      => $expenseStatementCorrespondence['expense_statement_id']['emission_date'],
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
$recipient_email = $expenseStatementCorrespondence['owner_id']['email']
    ?? $expenseStatementCorrespondence['owner_id']['email_alt']
    ?? null;

/** @var EmailAttachment[] */
$attachments = [];

$main_attachment_name = 'Invitation Assemblée - ' . $expenseStatementCorrespondence['condo_id']['name'] . ' - ' . $expenseStatementCorrespondence['ownership_id']['name'];

// push main attachment
$attachments[] = new EmailAttachment($main_attachment_name.'.pdf', (string) $expenseStatementCorrespondence['document_id']['data'], 'application/pdf');

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
Mail::queue($message, 'realestate\governance\ExpenseStatementCorrespondence', $expenseStatementCorrespondence['id']);


// mark invitation as sent
ExpenseStatementCorrespondence::id($expenseStatementCorrespondence['id'])
    ->update([
        'is_sent'      => true,
        'sent_date'    => date('Y-m-d H:i:s')
    ]);

$context->httpResponse()
        ->status(201)
        ->send();
