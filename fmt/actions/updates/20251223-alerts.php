<?php

use core\alert\MessageModel;

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.duplicate_invoice',
        'type'          => 'accounting',
        'label'         => 'Duplicate invoice',
        'description'   => "An invoice with same details has already been imported."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Facture en double',
        'description'   => "Une facture identique a déjà été importée.",
    ], 'fr');


$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.possible_duplicate_invoice',
        'type'          => 'accounting',
        'label'         => 'Possibly duplicate invoice',
        'description'   => "A similar invoice has already been imported."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Possible facture en double',
        'description'   => "Une facture similaire a déjà été importée.",
    ], 'fr');

