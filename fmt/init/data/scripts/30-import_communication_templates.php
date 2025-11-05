<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();

$template = Template::create([
        'code'          => 'general_meetings',
        'description'   => 'Invitation à une assemblée de la copropriété.',
        'category_id'   => 5,
        'type_id'       => 1
    ])
    ->first();


TemplatePart::create([
    'name'          => 'subject',
    'value'         => '<p>{condo} - Invitation à {assembly}</p>',
    'template_id'   => $template['id']
]);

TemplatePart::create([
    'name'          => 'body',
    'value'         => "<p>Bonjour {firstname} {lastname},</p><p><br></p><p>Vous êtes cordialement convié à une nouvelle assemblée des copropriétaires de la copropriété {condo}.</p><p><br></p><p>Veuillez trouver l'invitation et les détails en pièce jointe.</p><p><br></p><p>Bien cordialement,</p>",
    'template_id'   => $template['id']
]);


$orm->enableEvents($events);