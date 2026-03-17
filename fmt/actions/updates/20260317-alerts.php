<?php

use core\alert\MessageModel;

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_private_expense_data',
        'type'          => 'accounting',
        'label'         => 'Missing private expense data',
        'description'   => "Some data of private expense are missing (ownership/property lot)."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Infos frais privatifs manquantes',
        'description'   => "Certaines infos des frais privatifs sont manquantes (propriétaire/lot).",
    ], 'fr');