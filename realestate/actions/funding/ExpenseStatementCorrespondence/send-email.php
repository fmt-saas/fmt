<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Email;
use communication\template\Template;
use equal\data\DataFormatter;
use fmt\core\Mail;
use identity\Organisation;
use equal\email\Email as EmailMessage;
use equal\email\EmailAttachment;
use finance\accounting\FiscalPeriod;
use fmt\setting\Setting;
use realestate\funding\ExpenseStatementCorrespondence;
use realestate\management\ManagementProcess;

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

$dataFormatter = function ($value, $usage) {
    if(is_null($value)) {
        return '';
    }
    return DataFormatter::format($value, $usage);
};

$getFormattedDate = function($timestamp) {
    if(empty($timestamp) || !is_numeric($timestamp)) {
        return '';
    }
    try {
        $tz = new DateTimeZone(constant('L10N_TIMEZONE'));
        $tz_offset = $tz->getOffset(new DateTime('@' . $timestamp));
        $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
        return date($date_format, $timestamp + $tz_offset);
    }
    catch(\Throwable $e) {
        return '';
    }
};

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
    $signature = $organisation['signature'] ?? '';
}

$expenseStatementCorrespondence = ExpenseStatementCorrespondence::id($params['id'])
    ->read([
        'condo_id' => ['name'],
        'name',
        'communication_method',
        'owner_id' => ['firstname', 'lastname', 'email', 'email_alt', 'lang_id'],
        'ownership_id' => ['name'],
        'expense_statement_id' => ['name', 'emission_date', 'fiscal_period_id'],
        'document_id'
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

$fiscal_period_fields = [
    'date_from',
    'date_to'
];

$fiscalPeriod = FiscalPeriod::id($expenseStatementCorrespondence['expense_statement_id']['fiscal_period_id'])
    ->read($fiscal_period_fields)
    ->first();

if(!$fiscalPeriod) {
    throw new Exception('unknown_fiscal_period', EQ_ERROR_UNKNOWN_OBJECT);
}

// retrieve template (subject & body)
$subject = '';
$body = '';

$template = Template::search([
        ['code', '=', 'expense_statement_correspondence'],
        ['type', '=', 'email']
    ])
    ->read( ['id','parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'expense_statement' => $expenseStatementCorrespondence['expense_statement_id']['name'],
            'condo'             => $expenseStatementCorrespondence['condo_id']['name'],
            'date'              => $getFormattedDate($expenseStatementCorrespondence['expense_statement_id']['emission_date']),
            'period'            => $getFormattedDate($fiscalPeriod['date_from']) . ' - ' . $getFormattedDate($fiscalPeriod['date_to']),
            'period_from'       => $getFormattedDate($fiscalPeriod['date_from']),
            'period_to'         => $getFormattedDate($fiscalPeriod['date_to'])
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
            'firstname'         => $expenseStatementCorrespondence['owner_id']['firstname'],
            'lastname'          => $expenseStatementCorrespondence['owner_id']['lastname'],
            'condo'             => $expenseStatementCorrespondence['condo_id']['name'],
            'date'              => $getFormattedDate($expenseStatementCorrespondence['expense_statement_id']['emission_date']),
            'period'            => $getFormattedDate($fiscalPeriod['date_from']) . ' - ' . $getFormattedDate($fiscalPeriod['date_to']),
            'period_from'       => $getFormattedDate($fiscalPeriod['date_from']),
            'period_to'         => $getFormattedDate($fiscalPeriod['date_to'])
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

if(!$recipient_email || $recipient_email === '') {
    throw new Exception('missing_mandatory_email', EQ_ERROR_INVALID_CONFIG);
}

// create message
$message = new EmailMessage();
$message->setTo($recipient_email)
        ->setSubject($subject)
        ->setContentType("text/html")
        ->setBody($body);

$managementProcess = ManagementProcess::search(['code', '=', 'finance'])->read(['mailbox_id'])->first();
if(!$managementProcess || !$managementProcess['mailbox_id']) {
    throw new Exception('missing_mandatory_mailbox', EQ_ERROR_INVALID_CONFIG);
}

// queue message
$email_id = Mail::queue(
    $message,
    'realestate\governance\ExpenseStatementCorrespondence',
    $expenseStatementCorrespondence['id']
);

Email::id($email_id)->update([
    'mailbox_id'                => $managementProcess['mailbox_id'],
    'attachment_documents_ids'  => [ $expenseStatementCorrespondence['document_id'] ]
]);

// mark invitation as sent
ExpenseStatementCorrespondence::id($expenseStatementCorrespondence['id'])
    ->update([
        'sent_date'    => time()
    ])
    ->update([
        'is_sent'      => true,
    ]);

$context->httpResponse()
        ->status(201)
        ->send();
