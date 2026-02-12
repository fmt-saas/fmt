<?php

use communication\template\Template;
use communication\template\TemplatePart;


// General Meetings register
$template = Template::create([
        'code'          => 'general_meetings_register',
        'description'   => 'Registre des présences.',
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => "Registre des présences",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "type", "date"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "Liste des présences de l'{type} tenue à l'adresse {location} à la date du {date}.",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date", "location", "type", "time_start"]'
]);