<?php

use core\alert\MessageModel;

$message_model = MessageModel::search([
        ['name', '=', 'realestate.funding.expense_statement.sending_disabled']
    ])
    ->read(['name'])
    ->first();

if(is_null($message_model)) {
    $model = MessageModel::create([
            'name'          => 'realestate.funding.expense_statement.sending_disabled',
            'type'          => 'accounting',
            'label'         => 'Expense statement correspondence sending disabled',
            'description'   => "Sending and export of the expense statement correspondences were not scheduled at the user's request."
        ], 'en')
        ->first();

    MessageModel::id($model['id'])->update([
            'label'         => 'Envoi des correspondances du décompte désactivé',
            'description'   => "L'envoi et l'export des correspondances du décompte de charges n'ont pas été planifiés à la demande de l'utilisateur.",
        ], 'fr');
}
