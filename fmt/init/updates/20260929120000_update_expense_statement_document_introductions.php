<?php

use communication\template\Template;
use communication\template\TemplatePart;

$template = Template::search([
        ['code', '=', 'expense_statement_correspondence'],
        ['category', '=', 'general'],
        ['type', '=', 'document']
    ])
    ->read(['id'])
    ->first();

if(!$template) {
    return;
}

$introduction = TemplatePart::search([
        ['template_id', '=', $template['id']],
        ['name', '=', 'introduction']
    ])
    ->read(['id', 'value', 'variables'])
    ->first();

if(!$introduction) {
    return;
}

$variables = json_decode($introduction['variables'], true);
if(!is_array($variables)) {
    $variables = [];
}

if(!in_array('document_date', $variables, true)) {
    $variables[] = 'document_date';
}

$variables = json_encode($variables);

TemplatePart::id($introduction['id'])->update([
    'name'      => 'introduction_period_end',
    'variables' => $variables
]);

TemplatePart::create([
    'name'        => 'introduction_document_date',
    'value'       => "<p><small><big>Madame, Monsieur,</big></small></p><p><small><big>Nous vous prions de trouver ci-joint le détail de votre participation dans le décompte de charges de la copropriété dénommée {condo} pour la période du {period_from} au {period_to}, et en particulier les documents suivants :</big></small></p><ol><li><small><big>Votre décompte de charges ;</big></small></li><li><small><big>Le détail de votre situation de compte copropriétaire arrêtée au {document_date} ;</big></small></li><li><small><big>Le bilan comptable de la copropriété à la date de clôture ;</big></small></li><li><small><big>La liste des dépenses faisant partie du décompte ;</big></small></li></ol><p><small><big>Vous trouverez les modalités de paiement dans l'encadré ci-dessous. Nous attirons votre attention sur le fait que le montant repris dans cet encadré correspond au montant de votre situation de compte au {document_date}. Tout mouvement ultérieur n'est donc pas pris en compte.</big></small></p><p><small><big>Nous rappelons aux copropriétaires bailleurs que la quote-part occupant est communiquée à titre purement indicatif, selon l'usage, ne connaissant pas les éventuelles dispositions particulières reprises dans votre contrat de bail. Il n'appartient pas au syndic d'intervenir en cas de différend entre le bailleur et son locataire.</big></small></p><p><small><big>Nous restons à votre disposition pour toute question.</big></small></p><p><small><big>Cordialement,</big></small></p><p><small><big>Le syndic</big></small></p>",
    'template_id' => $template['id'],
    'variables'   => $variables
]);
