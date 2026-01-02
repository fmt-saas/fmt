<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


/* General Assembly - Call */

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
    'value'         => 'Invitation à {assembly}',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Vous êtes cordialement convié à une nouvelle assemblée des copropriétaires de la copropriété {condo}.</p><p><br></p><p>Veuillez trouver l'invitation et les détails en pièce jointe.</p><p><br></p><p>Bien cordialement,</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "date"]'
]);

/* General Assembly - Minutes */

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

/* Expense Statement */

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
    'variables'     => '["condo", "firstname", "lastname", "period", "due_date"]'
]);

// Fund Request Reminder

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
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Veuillez trouver en pièce jointe l'appel de fonds concernant la copropriété <strong>{condo}</strong> pour la période <strong>{period}</strong>.</p><p><br></p><p>Le montant est payable selon les modalités précisées dans le présent document.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "period", "due_date"]'
]);

$orm->enableEvents($events);