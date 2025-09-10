<?php

use core\alert\MessageModel;

/**
 * GOVERNANCE
 */

// Incomplete sending of the invitations to an Assembly
$model = MessageModel::create([
        'name'          => 'realestate.governance.assembly.incomplete_sending',
        'type'          => 'governance',
        'label'         => 'Incomplete sending',
        'description'   => "At least one owner hasn't been contacted."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Envoi incomplet',
        'description'   => "Au moins un propriétaire n'a pas encore été contacté.",
    ], 'fr');


// The quorum of presence or represented shares is not reached
$model = MessageModel::create([
        'name'          => 'realestate.governance.assembly.invalid',
        'type'          => 'governance',
        'label'         => 'Quorum not reached',
        'description'   => "The quorum of presence or represented shares is not reached."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Quorum non atteint',
        'description'   => "Le quorum de présence ou de parts représentées n'est pas atteint.",
    ], 'fr');



/**
 * ACCOUNTING
 */

// The quorum of presence or represented shares is not reached
$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.invalid',
        'type'          => 'accounting',
        'label'         => 'Incomplete invoice',
        'description'   => "One or more mandatory piece of information are missing."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Facture incomplète',
        'description'   => "Une ou plusieurs informations obligatoires sont manquantes.",
    ], 'fr');
