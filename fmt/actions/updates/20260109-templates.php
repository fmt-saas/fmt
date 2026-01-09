<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();



/* General Assembly - Minutes */

// invitation (minutes)
$template = Template::search([
        ['type', '=', 'document'],
        ['code', '=', 'general_meetings_minutes']
    ])
    ->first();

TemplatePart::create([
    'name'          => 'conclusion',
    'value'         => "<p>
        Plus aucun point n'étant à l'ordre du jour et personne ne demandant la parole, le président constate que l'ordre du jour a été entièrement épuisé.
        La séance est levée à {time_end}.<br />
        Le présent procès-verbal est établi conformément aux dispositions du Code civil et sera communiqué aux copropriétaires dans les délais légaux.
        Il est signé par le président, le secrétaire et les scrutateurs.<br /><br />Fait à {location}, le {date}.
    </p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "date", "location", "time_end"]'
]);



$orm->enableEvents($events);