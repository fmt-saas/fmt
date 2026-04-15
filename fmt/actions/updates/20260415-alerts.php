<?php

use core\alert\MessageModel;

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.invalid_date_from_fiscal_year',
        'type'          => 'accounting',
        'label'         => 'Invalid date from',
        'description'   => "Date from must be on an open fiscal year."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Début d\'intervalle invalide',
        'description'   => "L'intervalle doit être sur une année comptable ouverte.",
    ], 'fr');
