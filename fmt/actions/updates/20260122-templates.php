<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();



// agenda
$template = Template::create([
        'code'          => 'general_meetings_agenda',
        'description'   => 'Ordre du jour d\'une assemblée de la copropriété.',
        'category_id'   => 5,
        'type_id'       => 5
    ])
    ->first();
TemplatePart::create([
    'name'          => 'subject',
    'value'         => "Ordre du jour de l'{type} du {date}",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "type", "date"]'
]);
TemplatePart::create([
    'name'          => 'introduction',
    'value'         => "",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date", "location", "type", "time_start"]'
]);
TemplatePart::create([
    'name'          => 'conclusion',
    'value'         => "<p>Pour que l'assemblée générale se déroule efficacement et ne dure pas plus longtemps que nécessaire, les copropriétaires sont invités à transmettre leurs questions au syndic par écrit dès qu'ils en ont connaissance, sans attendre la réunion.</p>
    <p>Cela concerne notamment les points inscrits à l'ordre du jour, y compris les décomptes et la comptabilité.</p>
    <p>Cette anticipation permettra d'apporter des réponses avant l'assemblée et d'éviter les prolongations inutiles.</p>",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);
TemplatePart::create([
    'name'          => 'legal_notes',
    'value'         => "IMPORTANT :<br />
    <p>
    1) Modalité de consultation des documents relatifs aux points inscrits à l'ordre du jour :<br />
    Les documents sont disponibles sur simple demande au bureau du Syndic.
    </p>
    <p>
    2) Au cas où votre emploi du temps ne vous permettrait pas d'assister à l'assemblée générale, nous vous demandons de donner procuration à un autre copropriétaire ou une personne de votre choix pour vous représenter ; ceci afin de permettre à l'assemblée générale de se tenir valablement.<br />
    Dans le cas où le quorum n'est pas atteint, il est alors nécessaire de convoquer une deuxième assemblée générale ce qui représente des frais pour la copropriété et dérange une nouvelle fois les propriétaires.
    </p>
    <p>
    3) En cas de division du droit de propriété portant sur un lot privatif ou lorsque la propriété d'un lot privatif est grevée d'un droit d'emphytéose, de superficie, d'usufruit, d'usage ou d'habitation, le droit de participation aux délibérations de l'Assemblée Générale est suspendu jusqu'à ce que les intéressés désignent la personne qui sera leur mandataire.
    </p>
    <p>
    4) Toute demande ou remarque individuelle à propos de la comptabilité doit être faite 7 jours à l'avance.
    </p>",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);

$orm->enableEvents($events);