<?php

use core\alert\MessageModel;

$message_model = MessageModel::search([
        ['name', '=', 'realestate.funding.payment_reminder.outdated_bank_statements']
    ])
    ->read(['id'])
    ->first();

if(!$message_model) {
    $message_model = MessageModel::create([
            'name'          => 'realestate.funding.payment_reminder.outdated_bank_statements',
            'type'          => 'accounting',
            'label'         => 'Bank statements are outdated',
            'description'   => 'At least one active condominium bank account has no bank statement dated within the last seven days.'
        ], 'en')
        ->first();

    MessageModel::id($message_model['id'])->update([
            'label'         => 'Extraits bancaires obsolètes',
            'description'   => "Au moins un compte bancaire actif de la copropriété ne dispose d'aucun extrait daté des sept derniers jours."
        ], 'fr');
}

