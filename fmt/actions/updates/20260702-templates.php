<?php

use communication\template\Template;
use communication\template\TemplatePart;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();

$template = Template::search([
        ['code', '=', 'payment_reminder_correspondence'],
        ['category_id', '=', 5],
        ['type_id', '=', 5]
    ])
    ->first();

TemplatePart::search(['template_id', '=', $template['id']])->delete(true);

TemplatePart::create([
    'name'          => 'subject_reminder',
    'value'         => 'Rappel de paiement {level}',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "emission_date", "level"]'
]);

TemplatePart::create([
    'name'          => 'subject_final_notice',
    'value'         => 'Final notice - Situation de compte au {emission_date}',
    'template_id'   => $template['id'],
    'variables'     => '["condo", "emission_date", "level"]'
]);

TemplatePart::create([
    'name'          => 'introduction_reminder_1',
    'value'         => "<p>Bonjour,</p><p><br></p><p>Veuillez trouver en pièce jointe un premier rappel de paiement relatif à votre situation de compte au sein de la copropriété <strong>{condo}</strong>.</p><p><br></p><p>Le solde ouvert repris au rappel s'élève à <strong>{due_amount}</strong> à la date du <strong>{emission_date}</strong>.</p><p><br></p><p>Nous vous invitons à régulariser cette situation pour le <strong>{due_date}</strong>, si le paiement n'a pas déjà été effectué entre-temps.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "emission_date", "due_amount", "due_date"]'
]);

TemplatePart::create([
    'name'          => 'introduction_reminder_2',
    'value'         => "<p>Bonjour,</p><p><br></p><p>Sauf erreur ou paiement récent de votre part, nous constatons que le solde ouvert relatif à votre situation de compte au sein de la copropriété <strong>{condo}</strong> reste impayé.</p><p><br></p><p>Le montant actuellement dû s'élève à <strong>{due_amount}</strong> à la date du <strong>{emission_date}</strong>.</p><p><br></p><p>Nous vous invitons à régulariser cette situation pour le <strong>{due_date}</strong>.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "emission_date", "due_amount", "due_date"]'
]);

TemplatePart::create([
    'name'          => 'introduction_reminder_3',
    'value'         => "<p>Bonjour,</p><p><br></p><p>Malgré nos précédents rappels, nous constatons que votre situation de compte au sein de la copropriété <strong>{condo}</strong> présente toujours un solde ouvert.</p><p><br></p><p>Le montant actuellement dû s'élève à <strong>{due_amount}</strong> à la date du <strong>{emission_date}</strong>.</p><p><br></p><p>Nous vous demandons de bien vouloir régulariser cette situation pour le <strong>{due_date}</strong>.</p><p><br></p><p>À défaut de paiement dans ce délai, des mesures complémentaires pourront être envisagées conformément aux règles applicables au recouvrement des charges de copropriété.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "emission_date", "due_amount", "due_date"]'
]);

TemplatePart::create([
    'name'          => 'introduction_reminder_4',
    'value'         => "<p>Bonjour,</p><p><br></p><p>Malgré plusieurs rappels restés sans régularisation, votre situation de compte au sein de la copropriété <strong>{condo}</strong> présente toujours un solde ouvert.</p><p><br></p><p>Le montant actuellement dû s'élève à <strong>{due_amount}</strong> à la date du <strong>{emission_date}</strong>.</p><p><br></p><p>Nous vous demandons de procéder au paiement complet pour le <strong>{due_date}</strong>.</p><p><br></p><p>À défaut de régularisation dans ce délai, le dossier pourra être transmis pour suite utile, avec les frais et intérêts éventuels qui pourraient en découler.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "emission_date", "due_amount", "due_date"]'
]);

TemplatePart::create([
    'name'          => 'introduction_final_notice',
    'value'         => "<p>Bonjour,</p><p><br></p><p>Malgré nos précédents rappels, votre situation de compte au sein de la copropriété <strong>{condo}</strong> n'a pas été régularisée.</p><p><br></p><p>Le montant actuellement dû s'élève à <strong>{due_amount}</strong> à la date du <strong>{emission_date}</strong>.</p><p><br></p><p>La présente constitue un dernier rappel avant transmission éventuelle du dossier pour recouvrement.</p><p><br></p><p>Nous vous demandons de procéder au paiement complet pour le <strong>{due_date}</strong>.</p><p><br></p><p>À défaut de paiement dans ce délai, le dossier pourra être transmis pour suite utile, sans autre rappel, avec les frais, intérêts et indemnités éventuels qui pourraient en découler.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "emission_date", "due_amount", "due_date"]'
]);

TemplatePart::create([
    'name'          => 'communication_payment_amount',
    'value'         => '<p>Le montant de <b>{due_amount}</b> doit être réglé avant le <b>{due_date}</b></p>',
    'template_id'   => $template['id'],
    'variables'     => '["due_amount", "due_date", "emission_date"]'
]);


$orm->enableEvents($events);
