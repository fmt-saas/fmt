<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


/* General Assembly - Minutes */

// email
$template = Template::create([
        'code'          => 'general_meetings_minutes_correspondence',
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
        'code'          => 'general_meetings_minutes_correspondence',
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
    'variables'     => '["condo", "firstname", "lastname", "date", "condo_city", "time_start", "count_owners", "count_represented_owners", "count_shares", "count_represented_shares"]',
    'value'         => "
<p>Madame, Monsieur,</p>
<p>
Nous avons le plaisir de vous transmettre, en annexe à la présente, le
<strong>procès-verbal de l'Assemblée Générale des copropriétaires</strong> de la copropriété <strong>{{ condo }}</strong>, qui s'est tenue le <strong>{{ date }}</strong>, à <strong>{{ condo_city }}</strong>.
</p>
<p>
Ce procès-verbal reprend l'ensemble des résolutions soumises au vote,
ainsi que les décisions adoptées conformément aux règles légales et statutaires.
</p>
<p>
Nous vous invitons à en prendre attentivement connaissance.
Conformément à la législation en vigueur, ce document fait foi des décisions prises par l'Assemblée Générale.
</p>
<p>
Nous restons bien entendu à votre disposition pour toute information complémentaire.
</p>
<p>
Veuillez agréer, Madame, Monsieur, l'expression de nos salutations distinguées.
</p>
    ",
    'template_id'   => $template['id']
]);




$orm->enableEvents($events);