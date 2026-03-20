<?php

use communication\template\Template;
use communication\template\TemplatePart;

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

$template = Template::create([
    'code'          => 'mandate_form',
    'description'   => "Formulaire de procuration",
    'category_id'   => 5,
    'type_id'       => 5
])
    ->read(['id'])
    ->first();

TemplatePart::create([
    'name'          => 'subject',
    'value'         => "Procuration",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);

TemplatePart::create([
    'name'          => 'owner_undersign',
    'value'         => implode('', [
        "<p>Je soussigné(e), <strong>{representative_owner}</strong>,</p>",
        "<p>Demeurant à l'adresse : <strong>{representative_owner_address}</strong>,</p>",
        "<p>Propriétaire du/des lot(s) suivant(s) au sein de la copropriété <strong>{condo}</strong></p>"
    ]),
    'template_id'   => $template['id'],
    'variables'     => '["representative_owner", "representative_owner_address", "condo"]'
]);

TemplatePart::create([
    'name'          => 'owner_representation',
    'value'         => "<p>Pour me représenter à l'Assemblée Générale des copropriétaires qui se tiendra le <strong>{{ assembly.assembly_date | date(date_format, timezone) }}</strong>, à <strong>{{ assembly.assembly_location }}</strong>,<br />et pour voter en mon nom sur toutes les résolutions inscrites à l'ordre du jour, ainsi que sur toutes questions pouvant être soumises à l'assemblée.</p>",
    'template_id'   => $template['id'],
    'variables'     => '["assembly_date", "assembly_location"]'
]);

TemplatePart::create([
    'name'          => 'notice',
    'value'         => "<p>IMPORTANT: Rappel de l'article 3-87 §7 du code civil. <br />« Nul ne peut accepter plus de trois procurations. Toutefois, un mandataire peut recevoir plus de trois procurations de vote si le total des voix dont il dispose lui-même et de celles de ses mandants n'excède pas 10% du total des voix affectées à l'ensemble des lots de la copropriété. »</p>",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);
