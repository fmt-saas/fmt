<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use core\Mail;
use equal\email\Email;
use realestate\governance\AssemblyInvitation;

[$params, $providers] = eQual::announce([
    'description'   => "Send the email relating to a given assembly invitation.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\governance\AssemblyInvitation',
            'description'      => 'Identifier of the Assembly item (resolution).',
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
 * @var \equal\php\Context                  $context
 */
['context' => $context] = $providers;

if(!isset($params['id'])) {
    throw new Exception("missing_id", EQ_ERROR_INVALID_PARAM);
}

$assemblyInvitation = AssemblyInvitation::id($params['id'])
    ->read([
        'condo_id' => ['name'],
        'name',
        'communication_method',
        'owner_id' => ['firstname', 'lastname', 'email', 'email_alt', 'lang_id'],
        'ownership_id' => ['name'],
        'assembly_id' => ['name', 'assembly_date', 'assembly_type']
    ])
    ->first();

if(!$assemblyInvitation) {
    throw new Exception("unknown_assembly_invitation", EQ_ERROR_INVALID_PARAM);
}

if($assemblyInvitation['communication_method'] !== 'email') {
    throw new Exception("invalid_communication_method", EQ_ERROR_INVALID_PARAM);
}

/*
    Templates codes and types

    Template code = même catégorisation que pour les Documents FS Nodes
        "general_meetings",
        "tender_documents",
        "maintenance_logs",
        "council_minutes",
        "legal_followup",
        "insurance_contracts",
        "syndic_contracts",
        "works_and_repairs",
        "sepa_mandates",
        "regulations",
        "operation_statements",
        "bank_statements",
        "supplier_contracts",
        "justifications",
        "internal_memos",
        "supplier_invoices",
        "ownership_transfers",

    TemplateTypes (unique)
        email
        sms
        notification
        form
        document



*/

// retrieve template (subject & body)
$subject = '';
$body = '';

$template = Template::search([
        ['code', '=', 'general_meetings'],
        ['type', '=', 'email']
    ])
    ->read( ['id','parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = $part['value'];

        $map_values = [
            'assembly'  => $assemblyInvitation['assembly_id']['name'],
            'condo'     => $assemblyInvitation['condo_id']['name']
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
            'firstname' => $assemblyInvitation['owner_id']['firstname'],
            'lastname'  => $assemblyInvitation['owner_id']['lastname'],
            'condo'     => $assemblyInvitation['condo_id']['name'],
            'date'      => $assemblyInvitation['assembly_id']['assembly_date'],
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $body = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $body);
    }
}


// retrieve recipient
$recipient_email = $assemblyInvitation['owner_id']['email']
    ?? $assemblyInvitation['owner_id']['email_alt']
    ?? null;

// create message
$message = new Email();
$message->setTo($recipient_email)
        ->setSubject($subject)
        ->setContentType("text/html")
        ->setBody($body);

// queue message
Mail::queue($message, 'realestate\governance\AssemblyInvitation', $assemblyInvitation['id']);

$context->httpResponse()
        ->status(201)
        ->send();
