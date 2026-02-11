<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();

// invitation (minutes)
$template = Template::search(['code', '=', 'general_meetings_minutes'])
    ->read(['id'])
    ->first();

TemplatePart::create([
    'name'          => 'late_arrival_notice',
    'value'         => '<p>Le(s) propriétaire(s) suivant(s) est(sont) arrivé(es) après l’ouverture de la séance : {late_arrival}</p>',
    'template_id'   => $template['id'],
    'variables'     => '["late_arrival"]'
]);

TemplatePart::create([
    'name'          => 'early_departure_notice',
    'value'         => '<p>Le(s) propriétaire(s) suivant(s) a(ont) quitté(es) l’assemblée avant la fin : {early_departure}</p>',
    'template_id'   => $template['id'],
    'variables'     => '["early_departure"]'
]);

$orm->enableEvents($events);
