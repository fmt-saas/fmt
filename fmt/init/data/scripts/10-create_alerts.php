<?php

use core\alert\MessageModel;

/**
 * GOVERNANCE
 */

// Incomplete sending of the invitations to an Assembly
$model = MessageModel::create([
        'name'          => 'realestate.workflow.assembly.incomplete_sending',
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
        'name'          => 'realestate.workflow.assembly.invalid',
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


/**
 * OWNERSHIPS
 */

$model = MessageModel::create([
        'name'          => 'realestate.workflow.ownership.invalid_communication_prefs',
        'type'          => 'ownership',
        'label'         => 'Invalid preference',
        'description'   => "One or more communication preferences are invalid."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Préférence invalide',
        'description'   => "Au moins un canal de communication est invalide.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'realestate.workflow.communication_prefs.email_missing',
        'type'          => 'ownership',
        'label'         => 'Incomplete invoice',
        'description'   => "One or more mandatory piece of information are missing."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Canal invalide',
        'description'   => "Email non défini pour le choix du canal `email` (aucun email assigné).",
    ], 'fr');


/**
 * DOCUMENTS
 */

$model = MessageModel::create([
        'name'          => 'documents.export.export_failing',
        'type'          => 'export',
        'label'         => 'Export failing',
        'description'   => "One or more exports could not be completed."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Echec d\'un export',
        'description'   => "Au moins un export n\'a pas pu être complété.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'documents.export.export_ready',
        'type'          => 'export',
        'label'         => 'Export complete',
        'description'   => "A requested export is ready for download."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Export terminé',
        'description'   => "L\'export demandé est prêt pour le téléchargement.",
    ], 'fr');