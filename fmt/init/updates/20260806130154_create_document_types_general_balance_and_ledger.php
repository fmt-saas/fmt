<?php

use documents\DocumentSubtype;
use documents\DocumentType;

$createDocumentType = function(array $type, array $subtypes = []) {
    if(empty($type['description'])) {
        $type['description'] = 'Type de document : ' . $type['name'] . '.';
    }

    $documentType = DocumentType::create($type)->first();

    foreach($subtypes as $subtype) {
        if(empty($subtype['description'])) {
            $subtype['description'] = 'Sous-type de document : ' . $subtype['name'] . '.';
        }
        $subtype['document_type_id'] = $documentType['id'];
        DocumentSubtype::create($subtype);
    }
};

$createDocumentType([
    'id'                    => 40,
    'name'                  => 'Balance Générale Des Comptes',
    'code'                  => 'general_balance',
    'folder_code'           => 'operation_statements',
    'description'           => 'Document présentant, pour chaque compte, le total des mouvements au débit et au crédit ainsi que le solde sur une période comptable donnée.',
    'document_visibility'   => 'agency',
]);

$createDocumentType([
    'id'                    => 41,
    'name'                  => 'Grand Livre',
    'code'                  => 'general_ledger',
    'folder_code'           => 'operation_statements',
    'description'           => 'Document présentant, par compte et par ordre chronologique, l’ensemble des écritures comptables enregistrées sur une période donnée.',
    'document_visibility'   => 'agency',
]);
