<?php

use communication\template\Template;
use communication\template\TemplatePart;

$template = Template::search([
        ['code', '=', 'fund_request_execution_correspondence'],
        ['category_id', '=', 5],
        ['type_id', '=', 5]
    ])
    ->first();

TemplatePart::create([
    'name'          => 'introduction_with_date_range',
    'value'         => "<p>avec période Bonjour,</p><p><br></p><p>Veuillez trouver en pièce jointe l'appel de fonds concernant la copropriété <strong>{condo}</strong> pour la période <strong>{period}</strong>.</p><p>Le montant est payable pour le <strong>{due_date}</strong>, selon les modalités précisées dans le document annexé.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "label", "type", "date_from", "date_to"]'
]);
TemplatePart::create([
    'name'          => 'introduction_without_date_range',
    'value'         => "<p>sans période Bonjour,</p><p><br></p><p>Veuillez trouver en pièce jointe l'appel de fonds concernant la copropriété <strong>{condo}</strong> pour la période <strong>{period}</strong>.</p><p>Le montant est payable pour le <strong>{due_date}</strong>, selon les modalités précisées dans le document annexé.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "label", "type", "date_from", "date_to"]'
]);
TemplatePart::create([
    'name'          => 'introduction_with_due_balance',
    'value'         => "<p>avec situation de compte Bonjour,</p><p><br></p><p>Veuillez trouver en pièce jointe l'appel de fonds concernant la copropriété <strong>{condo}</strong> pour la période <strong>{period}</strong>.</p><p>Le montant est payable pour le <strong>{due_date}</strong>, selon les modalités précisées dans le document annexé.</p><p><br></p><p>Nous vous remercions de votre attention et restons à votre disposition pour toute information complémentaire.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["condo", "label", "type", "date_from", "date_to"]'
]);