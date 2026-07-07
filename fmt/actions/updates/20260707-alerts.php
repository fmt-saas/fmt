<?php

use core\alert\MessageModel;

/**
 * FUNDINGS
 */

$model = MessageModel::create([
        'name'          => 'finance.accounting.payment.insufficient_funds',
        'type'          => 'accounting',
        'label'         => 'Insufficient Funds',
        'description'   => "The bank account balance is too low for this transfer."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Fonds insuffisants',
        'description'   => "Le solde du compte bancaire n'est pas suffisant pour ce transfert.",
    ], 'fr');


