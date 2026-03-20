<?php

use communication\template\Template;
use communication\template\TemplatePart;

/*
$template = Template::search([
    ['code', '=', 'expense_statement_correspondence'],
    ['type', '=', 'document']
])
    ->read(['id'])
    ->first(true);

TemplatePart::create([
    'name'          => 'communication_payment_amount',
    'value'         => '<p>Le montant de <b>{remaining_amount}</b> doit être réglé avant le <b>{due_date}</b></p>',
    'template_id'   => $template['id'],
    'variables'     => '["remaining_amount", "due_date"]'
]);

TemplatePart::create([
    'name'          => 'communication_payment_reference',
    'value'         => '<b>Communication</b><b> : {payment_reference}</b>',
    'template_id'   => $template['id'],
    'variables'     => '["payment_reference"]'
]);

TemplatePart::create([
    'name'          => 'communication_reimbursement',
    'value'         => '<p>Votre compte présente un solde créditeur de <b>{remaining_amount_abs}</b> en votre faveur. Ce montant sera automatiquement déduit de votre prochain décompte de charges, ou pourra vous être remboursé sur simple demande écrite adressée au syndic.</p>',
    'template_id'   => $template['id'],
    'variables'     => '["remaining_amount_abs"]'
]);

TemplatePart::create([
    'name'          => 'communication_no_action_required',
    'value'         => '<p>La situation de votre compte fait apparaître un solde nul. Les provisions versées couvrent intégralement le montant du décompte. <b>Aucune action de votre part n\'est requise.</b></p>',
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);


$template = Template::search([
    ['code', '=', 'general_meetings_register'],
    ['type', '=', 'document']
])
    ->read(['id'])
    ->first(true);

TemplatePart::create([
    'name'          => 'certification_full',
    'value'         => "<p>Certifiée sincère et véritable, la feuille de présence est arrêtée à ........ copropriétaires présents ou représentés sur {count_owners}, totalisant ensemble .......... quotités sur {count_shares}.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["count_owners", "count_shares"]'
]);

TemplatePart::create([
    'name'          => 'certification_signed',
    'value'         => "<p>Certifiée sincère et véritable, la feuille de présence est arrêtée à {count_representations} copropriétaires présents ou représentés sur {count_owners}, totalisant ensemble {count_represented_shares} quotités sur {count_shares}.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["count_representations", "count_owners", "count_represented_shares", "count_shares"]'
]);
*/
