<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\DocumentType;
use documents\DocumentSubtype;

/**
 * Invoices
 */

$documentType = DocumentType::create([
        'id'            => 1,
        'name'          => 'Facture fournisseur',
        'code'          => 'invoice',
        'object_class'  => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
        'folder_code'   => 'supplier_invoices',
        'json_schema'   => 'urn:fmt:json-schema:finance:purchase-invoice',
        'description'   => "Document comptable à comptabiliser et réconcilier"
    ])
    ->first();

DocumentSubtype::create([
    'name'              => 'Facture',
    'code'              => 'invoice',
    'document_type_id'  => $documentType['id'],
    'description'       => 'Facture standard.'
]);

DocumentSubtype::create([
    'name'              => 'Note de crédit',
    'code'              => 'credit_note',
    'document_type_id'  => $documentType['id']
]);

DocumentSubtype::create([
    'name'              => 'Facture d\'acompte',
    'code'              => 'advance_invoice',
    'document_type_id'  => $documentType['id']
]);

DocumentSubtype::create([
    'name'              => 'Facture de régularisation',
    'code'              => 'adjustment_invoice',
    'document_type_id'  => $documentType['id']
]);

DocumentSubtype::create([
    'name'              => 'Facture de prestation hors-contrat',
    'code'              => 'off_contract',
    'document_type_id'  => $documentType['id']
]);


$documentType = DocumentType::create([
        'id'            => 14,
        'name'          => 'Relevés bancaires',
        'code'          => 'bank_statement',
        'object_class'  => 'finance\bank\BankStatement',
        'folder_code'   => 'bank_statements',
        'json_schema'   => 'urn:fmt:json-schema:finance:bank-statement',
        'description'   => "Mouvement sur compte bancaire de l'ACP."
    ])
    ->first();

DocumentSubtype::create([
    'name'              => 'Relevé bancaire',
    'code'              => 'bank_statement',
    'document_type_id'  => $documentType['id'],
    'description'       => 'Relevé standard.'
]);


/**
 *  #deprecated - use 'invoice' instead
 */
/*
DocumentType::create([
    'id'            => 2,
    'name'          => 'Note de crédit fournisseur',
    'code'          => 'credit_note',
    'folder_code'   => 'supplier_invoices',
    'description'   => "Note de crédit liée à une facture précédente",
    'json_schema'   => 'urn:fmt:json-schema:finance:purchase-invoice'
]);
*/

DocumentType::create([
    'id'            => 3,
    'name'          => 'Devis',
    'code'          => 'quote',
    'folder_code'   => 'tender_documents',
    'description'   => "Proposition chiffrée, rattachable à un dossier travaux ou sinistre."
]);

DocumentType::create([
    'id'            => 4,
    'name'          => 'Bon de commande',
    'code'          => 'purchase_order',
    'folder_code'   => 'works_and_repairs',
    'description'   => "Validation d'engagement de dépenses."
]);

DocumentType::create([
    'id'            => 5,
    'name'          => 'Bon de livraison',
    'code'          => 'delivery_note',
    'folder_code'   => 'works_and_repairs',
    'description'   => "Justifie qu'un service ou une marchandise a été livré."
]);

DocumentType::create([
    'id'            => 6,
    'name'          => 'Rapports de sinistre',
    'code'          => 'incident_report',
    'folder_code'   => 'works_and_repairs',
    'description'   => "Document décrivant un problème ou dégât."
]);

DocumentType::create([
    'id'            => 7,
    'name'          => 'Rapport d\'entretien',
    'code'          => 'maintenance_report',
    'folder_code'   => 'maintenance_logs',
    'description'   => "Suivi régulier, ex. extincteurs, ascenseurs."
]);

DocumentType::create([
    'id'            => 8,
    'name'          => 'Contrat fournisseur',
    'code'          => 'contract',
    'folder_code'   => 'supplier_contracts',
    'description'   => "Engagement contractuel formel (nettoyage, assurance, etc.)."
]);

DocumentType::create([
    'id'            => 9,
    'name'          => 'Certificat d\'assurance',
    'code'          => 'certificate',
    'folder_code'   => 'insurance_contracts',
    'description'   => "Preuve de conformité, attestation, certificat de contrôle ou d'assurance."
]);

DocumentType::create([
    'id'            => 10,
    'name'          => 'Conditions générales',
    'code'          => 'terms_and_conditions',
    'folder_code'   => 'contracts',
    'description'   => "Pièce annexe souvent non pertinente."
]);

DocumentType::create([
    'id'            => 11,
    'name'          => 'Relevé de consommations',
    'code'          => 'reconciliation_report',
    'folder_code'   => 'operation_statements',
    'description'   => "Répartition ou données de consommation (eau, gaz…)."
]);

DocumentType::create([
    'id'            => 12,
    'name'          => 'Appel de fonds',
    'code'          => 'fund_request',
    'folder_code'   => 'operation_statements',
    'description'   => "Document sollicitant un paiement d'avance ou une participation."
]);

DocumentType::create([
    'id'            => 13,
    'name'          => 'État des dépenses',
    'code'          => 'expense_statement',
    'folder_code'   => 'operation_statements',
    'description'   => "Détail ou synthèse des charges engagées."
]);



DocumentType::create([
    'id'            => 15,
    'name'          => 'Document juridique',
    'code'          => 'legal_document',
    'folder_code'   => 'legal_followup',
    'description'   => "Assignation, ordonnance, etc."
]);

DocumentType::create([
    'id'            => 16,
    'name'          => 'Courriers de mutations',
    'code'          => 'ownership_transfer_correspondence',
    'folder_code'   => 'ownership_transfers',
    'description'   => "Courriers relatifs aux transferts de propriété."
]);

$documentType = DocumentType::create([
        'id'            => 17,
        'name'          => 'Pièce justificative',
        'code'          => 'supporting_document',
        'folder_code'   => 'justifications',
        'description'   => "RIB, Kbis, attestation URSSAF, etc."
    ])
    ->first();

DocumentSubtype::create([
    'name'              => 'Attestation de conformité de citerne à mazout',
    'code'              => 'oil_tank_compliance_certificate',
    'document_type_id'  => $documentType['id'],
    'description'       => 'Attestation de conformité de citerne à mazout.'
]);

DocumentSubtype::create([
    'name'              => 'Attestation de neutralisation de citerne à mazout',
    'code'              => 'oil_tank_neutralization_certificate',
    'document_type_id'  => $documentType['id'],
    'description'       => 'Attestation de neutralisation de citerne à mazout.'
]);




$documentType = DocumentType::create([
        'id'            => 18,
        'name'          => 'Procès Verbaux',
        'code'          => 'internal_memo',
        'folder_code'   => 'internal_notes',
        'description'   => "Procès verbal d'une assemblée ou d'un conseil."
    ])
    ->first();


DocumentSubtype::create([
    'name'              => 'PV d\'assemblées générales',
    'code'              => 'general_assembly',
    'document_type_id'  => $documentType['id'],
    'description'       => 'Procès Verbaux d\'assemblées générales ordinaires.'
]);

DocumentSubtype::create([
    'name'              => 'PV d\'assemblées générales extraordinaire',
    'code'              => 'extra_general_assembly',
    'document_type_id'  => $documentType['id'],
    'description'       => 'Procès Verbaux d\'assemblées générales extraordinaires.'
]);

DocumentSubtype::create([
    'name'              => 'PV de Conseils de Copropriété',
    'code'              => 'condominium_council',
    'document_type_id'  => $documentType['id'],
    'description'       => 'Procès verbaux de Conseils de Copropriété (CC).'
]);




DocumentType::create([
    'id'            => 31,
    'name'          => 'Import Fournisseurs',
    'code'          => 'suppliers_import',
    'folder_code'   => 'imports',
    'description'   => "Fichiers d'imports Fournisseurs (temporaire)."
]);


DocumentType::create([
    'id'            => 32,
    'name'          => 'Import Copropriété',
    'code'          => 'condominium_import',
    'folder_code'   => 'imports',
    'description'   => "Fichiers d'imports Copropriété (temporaire)."
]);

