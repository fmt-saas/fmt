<?php

use core\alert\MessageModel;

$model = MessageModel::create([
        'name'          => 'documents.import.missing_suppliership',
        'type'          => 'import',
        'label'         => 'Missing suppliership',
        'description'   => "Provided supplier is not linked to condominium."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Fournisseur manquant',
        'description'   => "Le fournisseur choisi n'est pas lié à la coproprité.",
    ], 'fr');


$model = MessageModel::create([
        'name'          => 'documents.import.existing_target',
        'type'          => 'import',
        'label'         => 'Existing Target',
        'description'   => "Target document has already been created."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Cible existante',
        'description'   => "La cible a déjà été créée.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'documents.import.missing_condo_id',
        'type'          => 'import',
        'label'         => 'Missing Condominium',
        'description'   => "Condominium is not provided."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Copro manquante',
        'description'   => "La copropriété n'est pas renseignée.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'documents.import.missing_supplier_id',
        'type'          => 'import',
        'label'         => 'Missing supplier',
        'description'   => "Supplier is not provided."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Fournisseur manquant',
        'description'   => "Le fournisseur n'est pas renseigné.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'documents.import.missing_document_type_id',
        'type'          => 'import',
        'label'         => 'Missing document type',
        'description'   => "Document type is unknown."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Type de document manquant',
        'description'   => "Le type de document n'est pas renseigné.",
    ], 'fr');
