<?php

use core\alert\MessageModel;

$message_model = MessageModel::search([
        ['name', '=', 'finance.accounting.fiscal_year.export_task_created']
    ])
    ->read(['name'])
    ->first();

if(is_null($message_model)) {
    $model = MessageModel::create([
            'name'          => 'finance.accounting.fiscal_year.export_task_created',
            'type'          => 'accounting',
            'label'         => 'Fiscal year export scheduled',
            'description'   => 'An export task was created for this fiscal year.'
        ], 'en')
        ->first();

    MessageModel::id($model['id'])->update([
            'label'         => "Export de l'exercice comptable planifié",
            'description'   => "Une tâche d'export a été créée pour cet exercice comptable.",
        ], 'fr');
}
