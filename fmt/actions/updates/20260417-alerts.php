<?php

use core\alert\MessageModel;


$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.insufficient_funds',
        'type'          => 'accounting',
        'label'         => 'Insufficient funds',
        'description'   => "Selected reserve fund balance is lower than assigned amount."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Fonds insuffisants',
        'description'   => "La balance du fonds de réserve est inférieure au montant donné.",
    ], 'fr');