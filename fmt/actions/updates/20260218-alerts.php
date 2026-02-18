<?php

use core\alert\MessageModel;



$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.invalid_suppliership_iban',
        'type'          => 'accounting',
        'label'         => 'Invalid Supplier Bank Account IBAN',
        'description'   => "The Supplier bank account IBAN is invalid."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'IBAN Compte bancaire fournisseur invalide',
        'description'   => "L'IBAN du compte bancaire du fournisseur est invalide."
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.invalid_suppliership_bic',
        'type'          => 'accounting',
        'label'         => 'Invalid Supplier Bank Account BIC',
        'description'   => "The Supplier bank account BIC is invalid."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'BIC Compte bancaire fournisseur invalide',
        'description'   => "Le BIC du compte bancaire du fournisseur est invalide."
    ], 'fr');
