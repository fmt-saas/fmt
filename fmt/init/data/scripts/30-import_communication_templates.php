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
    'value'         => "Convocation à l'{type} du {date}",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "type", "date"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "
<p style=\"text-align: justify;\">Madame, Monsieur,</p><p><br></p><p style=\"text-align: justify;\">Vous êtes cordialement convié à l'{type} de l'association des copropriétaires dénommée&nbsp;{condo} qui se tiendra&nbsp;: &nbsp;</p><p><br></p><ul><li style=\"text-align: justify;\">Date : {date}</li><li style=\"text-align: justify;\">Lieu : {location}</li><li style=\"text-align: justify;\">Début de séance : {time_start}</li></ul><p><br></p><p style=\"text-align: justify;\">Afin de ne pas retarder l'ouverture de la séance, nous vous invitons à vous présenter au bureau de l'assemblée avec 15 minutes d'avance, muni de la présente convocation, pour y signer la liste de présences.</p><p><br></p><p style=\"text-align: justify;\">Suivant les dispositions de la loi du 4 février 2020 sur la copropriété, l'assemblée générale ne délibère valablement que si plus de la moitié des copropriétaires sont présents ou représentés, et pour autant qu'ils possèdent au moins la moitié des quotes-parts dans les parties communes. Si ce quorum n'est pas atteint, la loi nous contraint à convoquer une deuxième assemblée générale, ce qui entraîne à la fois des frais importants et une perte de temps regrettable.</p><p><br></p><p style=\"text-align: justify;\">Au cas où vous ne pourriez assister personnellement à la réunion, nous vous remercions de compléter le formulaire de procuration transmis en annexe de la présente et nous le renvoyer par email, par la poste ou le transmettre à votre mandataire.</p><p><br></p><p style=\"text-align: justify;\">Nous vous prions d'agréer, Madame, Monsieur, l'expression de nos sentiments distingués.</p><p style=\"text-align: justify;\"><br></p><p style=\"text-align: justify;\">Le syndic.</p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date", "location", "type", "time_start"]'
]);


// mandate (call)
$template = Template::create([
        'code'          => 'general_meetings_mandate',
        'description'   => 'Mandat de procuration pour une assemblée de la copropriété.',
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => "Procuration",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "type", "date"]'
]);
// #todo - create parts according to existing template AssemblyMandate.print.html
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "
    ",
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

// correspondence
$template = Template::create([
        'code'          => 'general_meetings_minutes',
        'description'   => 'Procès verbal d\'Assemblée Générale',
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => 'PV de l\'{type} du {date}',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "
<p>La liste de présences dûment signée dénombre {count_represented_owners} Copropriétaires présents ou réprésentés sur {count_owners}, représentant {count_represented_shares} quotités sur {count_shares}.
<p>Le <strong>{date} à {time_start}</strong>, les copropriétaires de l'immeuble <strong>{condo}</strong> à {condo_city} se sont réunis en assemblée générale sur convocation régulière adressée par le syndic à tous les copropriétaires.</p>
<p>Il a été dressé une feuille de présence qui a été signée par tous les copropriétaires présents et par les mandataires de ceux qui se sont fait représenter. Le quorum étant atteint, les membres peuvent débattre de l'ordre du jour.</p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "condo_city", "date", "time_start", "count_owners", "count_represented_owners", "count_shares", "count_represented_shares"]'
]);
TemplatePart::create([
    'name'          => 'conclusion',
    'value'         => "
<p>L'Ordre du Jour étant épuisé, la séance est levée à <strong>{time_end}</strong>.</p>
<p>Il est rappelé que, conformément à l'article 3.87 §10 du Code civil, le syndic rédige le procès-verbal des décisions prises par l'assemblée générale avec indication des majorités obtenues et du nom des copropriétaires qui ont voté contre ou qui se sont abstenus.</p>
<p>À la fin de la séance et après lecture, ce procès-verbal est signé par le président de l'assemblée générale, par le secrétaire désigné lors de l'ouverture de la séance et par tous les copropriétaires encore présents à ce moment ou leurs mandataires.</p>
<p>Les membres de l'association des copropriétaires peuvent prendre à l'unanimité et par écrit toutes les décisions relevant des compétences de l'assemblée générale, à l'exception de celles qui doivent être passées par acte authentique. Le syndic en dresse le procès-verbal conformément à l'article 3.87 §11.</p>
<p>Le syndic consigne les décisions visées aux paragraphes 10 et 11 dans le registre prévu à l'article 3.93, §4, dans les trente jours suivant l'assemblée générale, et transmet celles-ci, dans le même délai, à tout titulaire d'un droit réel sur un lot disposant, le cas échéant en vertu de l'article 3.87, §1er, alinéa 2, du droit de vote à l'assemblée générale, et aux autres syndics. Si l'un d'eux n'a pas reçu le procès-verbal dans le délai fixé, il en informe le syndic par écrit.</p>
<p>Tout copropriétaire peut demander au juge d'annuler ou de réformer une décision irrégulière, frauduleuse ou abusive de l'assemblée générale si elle lui cause un préjudice personnel.</p>
<p>Cette action doit être intentée dans un délai de quatre mois, à compter de la date à laquelle l'assemblée générale a eu lieu, conformément à l'article 3.92 §3 du Code civil.</p>
<p>{condo_city}, le <strong>{date}</strong></p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "date", "time_end", "condo_city"]'
]);

TemplatePart::create([
    'name'          => 'introduction.adjourned',
    'value'         => "",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);

TemplatePart::create([
    'name'          => 'introduction.second_session',
    'value'         => "",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);

TemplatePart::create([
    'name'          => 'conclusion.adjourned',
    'value'         => "",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);

TemplatePart::create([
    'name'          => 'conclusion.second_session',
    'value'         => "",
    'template_id'   => $template['id'],
    'variables'     => '[]'
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


// correspondence
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