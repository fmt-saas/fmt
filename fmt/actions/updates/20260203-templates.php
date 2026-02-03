<?php

use communication\template\Template;
use communication\template\TemplatePart;

// email (call_second_session - correspondence)
$template = Template::create([
        'code'          => 'general_meetings_invitation_second_session_correspondence',
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


// invitation (call_second_session - correspondence)
$template = Template::create([
        'code'          => 'general_meetings_invitation_second_session_correspondence',
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
<p style=\"text-align: justify;\">Madame, Monsieur,</p>
<p><br></p>
<p style=\"text-align: justify;\">
Vous êtes cordialement convié à l'{type} <strong>de seconde séance</strong> de l'association des copropriétaires dénommée&nbsp;{condo}, qui se tiendra&nbsp;:
</p>
<p><br></p>
<ul>
  <li style=\"text-align: justify;\">Date : {date}</li>
  <li style=\"text-align: justify;\">Lieu : {location}</li>
  <li style=\"text-align: justify;\">Début de séance : {time_start}</li>
</ul>
<p><br></p>
<p style=\"text-align: justify;\">
La présente assemblée générale est convoquée en <strong>seconde séance</strong>, la première assemblée générale n'ayant pu valablement délibérer faute d'avoir atteint le quorum légal.
</p>
<p><br></p>
<p style=\"text-align: justify;\">
<strong>En conséquence, aucun quorum de présence ou de représentation n'est requis pour cette assemblée générale de seconde séance.</strong>
L'assemblée pourra donc délibérer et prendre des décisions valablement, quel que soit le nombre de copropriétaires présents ou représentés, sous réserve du respect des majorités légales applicables à chaque point de l'ordre du jour.
</p>
<p><br></p>
<p style=\"text-align: justify;\">
Afin de ne pas retarder l'ouverture de la séance, nous vous invitons à vous présenter au bureau de l'assemblée avec 15 minutes d'avance, muni de la présente convocation, pour y signer la liste de présences.
</p>
<p><br></p>
<p style=\"text-align: justify;\">
Au cas où vous ne pourriez assister personnellement à la réunion, nous vous remercions de compléter le formulaire de procuration transmis en annexe de la présente et de nous le renvoyer par email, par la poste ou de le remettre à votre mandataire.
</p>
<p><br></p>
<p style=\"text-align: justify;\">
Nous vous prions d'agréer, Madame, Monsieur, l'expression de nos sentiments distingués.
</p>
<p><br></p>
<p style=\"text-align: justify;\">Le syndic.</p>
    ",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "assembly", "date", "location", "type", "time_start"]'
]);