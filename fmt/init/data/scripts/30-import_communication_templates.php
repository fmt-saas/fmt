<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


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
        1: email
        2: sms
        3: notification
        4: form
        5: document
*/


/* General Assembly - Call */

// email
$template = Template::create([
        'code'          => 'general_meetings_call',
        'description'   => 'Convocation à une assemblée de la copropriété.',
        'category_id'   => 5,
        'type_id'       => 1
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => '<p>{condo} - Convocation à {assembly}</p>',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date"]'
]);
TemplatePart::create([
    'name'          => 'body',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Vous êtes cordialement convié à une nouvelle assemblée des copropriétaires de la copropriété {condo}.</p><p><br></p><p>Veuillez trouver l'invitation et les détails en pièce jointe.</p><p><br></p><p>Bien cordialement,</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "date"]'
]);


// invitation (call)
$template = Template::create([
        'code'          => 'general_meetings_call',
        'description'   => 'Convocation à une assemblée de la copropriété.',
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => 'Convocation à {assembly}',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Vous êtes cordialement convoqué à une nouvelle assemblée des copropriétaires de la copropriété {condo}.</p><p><br></p><p>Veuillez trouver l'invitation et les détails en pièce jointe.</p><p><br></p><p>Bien cordialement,</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date", "location", "type", "time_start"]'
]);



/* General Assembly - Minutes */

// email
$template = Template::create([
        'code'          => 'general_meetings_minutes',
        'description'   => 'Procès verbal d\'Assemblée Générale.',
        'category_id'   => 5,
        'type_id'       => 1
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => '<p>{condo} - PV {assembly}</p>',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date"]'
]);
TemplatePart::create([
    'name'          => 'body',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Veuillez trouver en pièce jointe le procès-verbal de l'Assemblée Générale des copropriétaires de la copropriété <strong>{condo}</strong>, tenue le <strong>{date}</strong>.</p><p><br></p><p>Ce document reprend l'ensemble des décisions et résolutions adoptées lors de cette assemblée.</p><p><br></p><p>Nous restons à votre disposition pour toute question ou précision complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "date"]'
]);

// invitation (minutes)
$template = Template::create([
        'code'          => 'general_meetings_minutes',
        'description'   => 'Procès verbal d\'Assemblée Générale',
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => 'PV de {assembly}',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Veuillez trouver en pièce jointe le procès-verbal de l'Assemblée Générale des copropriétaires de la copropriété <strong>{condo}</strong>, tenue le <strong>{date}</strong>.</p><p><br></p><p>Ce document reprend l'ensemble des décisions et résolutions adoptées lors de cette assemblée.</p><p><br></p><p>Nous restons à votre disposition pour toute question ou précision complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "date"]'
]);
TemplatePart::create([
    'name'          => 'conclusion',
    'value'         => "",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "date"]'
]);



/* Expense Statement */

// email
$template = Template::create([
        'code'          => 'expense_statement',
        'description'   => 'Décompte de charges.',
        'category_id'   => 5,
        'type_id'       => 1
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => '<p>{condo} - Décompte de charges {period}</p>',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "period"]'
]);
TemplatePart::create([
    'name'          => 'body',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Veuillez trouver en pièce jointe le décompte de charges relatif à la copropriété <strong>{condo}</strong> pour la période <strong>{period}</strong>.</p><p><br></p><p>Ce document détaille les charges réparties conformément aux décisions de l'Assemblée Générale et au règlement de copropriété.</p><p><br></p><p>Nous restons à votre disposition pour toute question ou précision complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "period"]'
]);


// invitation (minutes)
$template = Template::create([
        'code'          => 'expense_statement',
        'description'   => 'Décompte de charges',
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => 'Décompte de charges {period}',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "period"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Veuillez trouver en pièce jointe le décompte de charge de la copropriété <strong>{condo}</strong>, pour la période <strong>{period}</strong>.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "period"]'
]);



/* Fund Request */

// email
$template = Template::create([
        'code'          => 'fund_request',
        'description'   => 'Appel de fonds.',
        'category_id'   => 5,
        'type_id'       => 1
    ])
    ->first();

TemplatePart::create([
    'name'          => 'subject',
    'value'         => '<p>{condo} - Appel de fonds {period}</p>',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "period"]'
]);

TemplatePart::create([
    'name'          => 'body',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Veuillez trouver en pièce jointe l'appel de fonds concernant la copropriété <strong>{condo}</strong> pour la période <strong>{period}</strong>.</p><p><br></p><p>Le montant est payable pour le <strong>{due_date}</strong>, selon les modalités précisées dans le document annexé.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "period"]'
]);

// correspondence
$template = Template::create([
        'code'          => 'fund_request',
        'description'   => 'Appel de fonds',
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => 'Appel de fonds',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "period"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Veuillez trouver en pièce jointe l'appel de fonds concernant la copropriété <strong>{condo}</strong> pour la période <strong>{period}</strong>.</p><p><br></p><p>Le montant est payable pour le <strong>{due_date}</strong>, selon les modalités précisées dans le document annexé.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "period"]'
]);


// Fund Request Reminder
$template = Template::create([
        'code'          => 'fund_request_reminder',
        'description'   => 'Rappel de paiement - appel de fonds.',
        'category_id'   => 5,
        'type_id'       => 1
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => '<p>{condo} - Rappel de paiement</p>',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "period", "due_date"]'
]);
TemplatePart::create([
    'name'          => 'body',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Sauf erreur ou omission de notre part, le paiement relatif à l'appel de fonds de la copropriété <strong>{condo}</strong>, échu au <strong>{due_date}</strong>, n'a pas encore été enregistré.</p><p><br></p><p>Nous vous invitons à régulariser la situation dans les meilleurs délais ou à nous contacter si votre paiement a déjà été effectué.</p><p><br></p><p>Nous restons à votre disposition pour toute question.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "period", "due_date"]'
]);

// correspondence
$template = Template::create([
        'code'          => 'fund_request_reminder',
        'description'   => 'Appel de fonds - Rappel',
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => 'Rappel de l\'appel de fonds pour la période {period}',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "due_date", "period"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Veuillez trouver en pièce jointe l'appel de fonds concernant la copropriété <strong>{condo}</strong> pour la période <strong>{period}</strong>.</p><p><br></p><p>Le montant est payable pour le <strong>{due_date}</strong>, selon les modalités précisées dans le document annexé.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "period", "due_date"]'
]);


$orm->enableEvents($events);