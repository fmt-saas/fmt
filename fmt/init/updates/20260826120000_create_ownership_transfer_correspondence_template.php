<?php

use communication\template\Template;
use communication\template\TemplatePart;

$template = Template::create([
        'code'          => 'ownership_transfer_correspondence',
        'description'   => "Dossier de mutation - email d'accompagnement",
        'category_id'   => 5,
        'type_id'       => 1
    ])
    ->read(['id'])
    ->first();

TemplatePart::create([
    'name'          => 'subject',
    'value'         => "Demande d’informations / Convention de cession du droit de propriété",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);

TemplatePart::create([
    'name'          => 'body',
    'value'         => "<p>Bonjour,</p><p>Dans le cadre de la perspective de vente d’un ou plusieurs lots situés au sein de la copropriété {condo}, vous trouverez en pièce jointe les informations disponibles à ce jour concernant la situation de la copropriété et des lots concernés.</p><p>Nous restons bien entendu à disposition pour toute précision complémentaire que vous jugeriez utile dans le cadre de la suite de la procédure.</p><p>Bien cordialement,<br /><strong>L’équipe de gestion</strong><br /><em>{managing_agent}</em><br /></p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "managing_agent"]'
]);
