<?php

use communication\template\TemplatePart;

TemplatePart::create([
    'name'          => 'communication_payment_amount',
    'value'         => '<p>Le montant de <b>{remaining_amount}</b> doit être réglé avant le <b>{due_date}</b></p>',
    'template_id'   => 14,
    'variables'     => '["remaining_amount", "due_date"]'
]);
TemplatePart::create([
    'name'          => 'communication_reimbursement',
    'value'         => '<p>Votre compte présente un solde créditeur de <b>{remaining_amount}</b> en votre faveur. Ce montant sera automatiquement déduit de votre prochain décompte de charges, ou pourra vous être remboursé sur simple demande écrite adressée au syndic.</p>',
    'template_id'   => 14,
    'variables'     => '["remaining_amount"]'
]);
TemplatePart::create([
    'name'          => 'communication_no_action_required',
    'value'         => '<p>La situation de votre compte fait apparaître un solde nul. Les provisions versées couvrent intégralement le montant du décompte. <b>Aucune action de votre part n\'est requise.</b></p>',
    'template_id'   => 14,
    'variables'     => '[]'
]);