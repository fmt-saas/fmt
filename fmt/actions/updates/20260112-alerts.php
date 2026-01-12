<?php

use core\alert\MessageModel;

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_mandatory_line_apportionment',
        'type'          => 'accounting',
        'label'         => 'Missing apportionment for income/expense account line.',
        'description'   => "Lines referring to expense or income must have an apportionment set."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Clé de répartition manquante pour les lignes de compte de charge/revenu.',
        'description'   => "Les lignes se rapportant à des comptes de charge ou de revenu doivent avoir une clé de répartition définie.",
    ], 'fr');
