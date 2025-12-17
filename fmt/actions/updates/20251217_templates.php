<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


// General Assembly Minutes
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




// Expense Statement
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


// Fund Request
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
    'variables'     => '["condo", "period", "due_date"]'
]);

TemplatePart::create([
    'name'          => 'body',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Veuillez trouver en pièce jointe l'appel de fonds concernant la copropriété <strong>{condo}</strong> pour la période <strong>{period}</strong>.</p><p><br></p><p>Le montant est payable pour le <strong>{due_date}</strong>, selon les modalités précisées dans le document annexé.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "period", "due_date"]'
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
    'variables'     => '["condo", "due_date"]'
]);

TemplatePart::create([
    'name'          => 'body',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Sauf erreur ou omission de notre part, le paiement relatif à l'appel de fonds de la copropriété <strong>{condo}</strong>, échu au <strong>{due_date}</strong>, n'a pas encore été enregistré.</p><p><br></p><p>Nous vous invitons à régulariser la situation dans les meilleurs délais ou à nous contacter si votre paiement a déjà été effectué.</p><p><br></p><p>Nous restons à votre disposition pour toute question.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "firstname", "lastname", "due_date"]'
]);


$orm->enableEvents($events);