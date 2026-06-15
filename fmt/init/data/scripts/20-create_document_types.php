<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

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
    'id'                    => 2,
    'name'                  => 'Document de base',
    'code'                  => 'reference_document',
    'folder_code'           => 'reference_documents',
    'document_visibility'   => 'condo',
    'has_subtype'           => true
], [
    [
        'name'          => 'Statuts',
        'code'          => 'basic_deed',
        'folder_code'   => 'reference_documents'
    ],
    [
        'name'          => 'Règlement d\'ordre intérieur',
        'code'          => 'internal_rules',
        'folder_code'   => 'reference_documents'
    ],
    [
        'name'          => 'Plans',
        'code'          => 'plan',
        'folder_code'   => 'reference_documents'
    ],
    [
        'name'          => 'Matrice cadastrale',
        'code'          => 'cadastral_matrix',
        'folder_code'   => 'reference_documents'
    ],
    [
        'name'          => 'DIU',
        'code'          => 'post_intervention_file',
        'folder_code'   => 'reference_documents'
    ],
    [
        'name'          => 'Fiche signalétique immeuble',
        'code'          => 'building_sheet',
        'folder_code'   => 'reference_documents'
    ]
]);

$createDocumentType([
    'id'                    => 18,
    'name'                  => 'Document d\'assemblée générale',
    'code'                  => 'general_assembly_document',
    'folder_code'           => 'general_meetings',
    'description'           => 'Procès verbal d\'une assemblée ou d\'un conseil.',
    'document_visibility'   => 'condo',
    'has_subtype'           => true
], [
    [
        'name'                  => 'Convocation',
        'code'                  => 'invite',
        'folder_code'           => 'general_meetings',
        'document_visibility'   => 'ownership'
    ],
    [
        'name'                  => 'Annexe de convocation',
        'code'                  => 'appendix',
        'folder_code'           => 'general_meetings',
        'document_visibility'   => 'condo'
    ],
    [
        'name'                  => 'Liste des présences',
        'code'                  => 'attendance_register',
        'document_visibility'   => 'condo'
    ],
    [
        'name'                  => 'PV d\'assemblée générale',
        'code'                  => 'minutes',
        'folder_code'           => 'general_meetings',
        'document_visibility'   => 'condo'
    ]
]);

$createDocumentType([
    'id'                    => 22,
    'name'                  => 'Document bancaire',
    'code'                  => 'bank_document',
    'folder_code'           => 'bank_documents',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Ouverture de compte',
        'code'          => 'bank_account_opening',
        'folder_code'   => 'bank_documents'
    ],
    [
        'name'          => 'Emprunt bancaire',
        'code'          => 'bank_loan',
        'folder_code'   => 'bank_documents'
    ],
    [
        'name'          => 'Mandat de domiciliation copropriétaire',
        'code'          => 'bank_owner_direct_debit_mandate',
        'folder_code'   => 'bank_documents'
    ]
]);

$createDocumentType([
    'id'                    => 12,
    'name'                  => 'Appel de fonds',
    'code'                  => 'fund_request',
    'folder_code'           => 'operation_statements',
    'description'           => 'Document sollicitant un paiement d\'avance ou une participation.',
    'document_visibility'   => 'ownership'
]);

$createDocumentType([
    'id'                    => 13,
    'name'                  => 'Décompte de charges',
    'code'                  => 'expense_statement',
    'folder_code'           => 'operation_statements',
    'description'           => 'Détail ou synthèse des charges engagées.',
    'document_visibility'   => 'ownership'
]);

$createDocumentType([
    'id'                    => 21,
    'name'                  => 'Rappel de paiement',
    'code'                  => 'payment_reminder',
    'folder_code'           => 'operation_statements',
    'document_visibility'   => 'ownership'
]);

$createDocumentType([
    'id'                    => 14,
    'name'                  => 'Extrait bancaire (CODA)',
    'code'                  => 'bank_statement',
    'object_class'          => 'finance\bank\BankStatement',
    'folder_code'           => 'bank_statements',
    'json_schema'           => 'urn:fmt:json-schema:finance:bank-statement',
    'description'           => 'Mouvement sur compte bancaire de l\'ACP.',
    'document_visibility'   => 'agency'
]);

$createDocumentType([
    'id'                    => 1,
    'name'                  => 'Facture fournisseur',
    'code'                  => 'supplier_invoice',
    'object_class'          => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
    'folder_code'           => 'supplier_invoices',
    'json_schema'           => 'urn:fmt:json-schema:finance:purchase-invoice',
    'description'           => 'Document comptable à comptabiliser et réconcilier',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Facture d\'acompte',
        'code'          => 'advance_invoice',
        'folder_code'   => 'supplier_invoices'
    ],
    [
        'name'          => 'Facture de régularisation',
        'code'          => 'adjustment_invoice',
        'folder_code'   => 'supplier_invoices'
    ],
    [
        'name'          => 'Facture de prestations hors contrat',
        'code'          => 'off_contract',
        'folder_code'   => 'supplier_invoices'
    ]
]);

$createDocumentType([
    'id'                    => 8,
    'name'                  => 'Contrat fournisseur',
    'code'                  => 'supplier_contract',
    'folder_code'           => 'supplier_contracts',
    'description'           => 'Engagement contractuel formel (nettoyage, assurance, etc.).',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Contrat nuisibles',
        'code'          => 'pest_control_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat ordures ménagères',
        'code'          => 'waste_collection_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat télécoms',
        'code'          => 'telecom_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat nettoyage',
        'code'          => 'cleaning_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat jardins',
        'code'          => 'garden_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat toiture',
        'code'          => 'roof_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat porte de garage / barrière',
        'code'          => 'garage_door_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat contrôle d\'accès',
        'code'          => 'access_control_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat canalisations / égouts',
        'code'          => 'sewer_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat protection incendie',
        'code'          => 'fire_safety_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat chauffage',
        'code'          => 'heating_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat gaz',
        'code'          => 'gas_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat mazout',
        'code'          => 'fuel_oil_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat eau',
        'code'          => 'water_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat adoucisseur',
        'code'          => 'water_softener_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat ascenseur',
        'code'          => 'elevator_contract',
        'folder_code'   => 'supplier_contracts'
    ],
    [
        'name'          => 'Contrat électricité',
        'code'          => 'electricity_contract',
        'folder_code'   => 'supplier_contracts'
    ]
]);

$createDocumentType([
    'id'                    => 23,
    'name'                  => 'Document fournisseur',
    'code'                  => 'supplier_document',
    'folder_code'           => 'supplier_documents',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Mandat de domiciliation fournisseur',
        'code'          => 'supplier_direct_debit_mandate',
        'folder_code'   => 'supplier_documents'
    ],
    [
        'name'          => 'Infos bancaires fournisseur',
        'code'          => 'supplier_bank_details',
        'folder_code'   => 'supplier_documents'
    ]
]);

$createDocumentType([
    'id'                    => 24,
    'name'                  => 'Attestation technique & Rapport de contrôle',
    'code'                  => 'technical_report_document',
    'folder_code'           => 'technical_reports',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Attestation de conformité de citerne à mazout',
        'code'          => 'oil_tank_compliance_certificate',
        'folder_code'   => 'technical_reports'
    ],
    [
        'name'          => 'Attestation de neutralisation de citerne à mazout',
        'code'          => 'oil_tank_neutralization_certificate',
        'folder_code'   => 'technical_reports'
    ],
    [
        'name'          => 'Attestation de conformité électrique',
        'code'          => 'electrical_certificate',
        'folder_code'   => 'technical_reports'
    ],
    [
        'name'          => 'Analyse de risque ascenseur',
        'code'          => 'elevator_risk_analysis',
        'folder_code'   => 'technical_reports'
    ],
    [
        'name'          => 'Rapport SECT',
        'code'          => 'inspection_report',
        'folder_code'   => 'technical_reports'
    ],
    [
        'name'          => 'Inventaire amiante',
        'code'          => 'asbestos_report',
        'folder_code'   => 'technical_reports'
    ],
    [
        'name'          => 'Attestation périodique',
        'code'          => 'periodic_certificate',
        'folder_code'   => 'inspection_reports'
    ]
]);

$createDocumentType([
    'id'                    => 25,
    'name'                  => 'Document d\'assurance',
    'code'                  => 'insurance_document',
    'folder_code'           => 'insurance_documents',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Assurance bâtiment multi-périls',
        'code'          => 'multi_risk_building',
        'folder_code'   => 'insurance_documents'
    ],
    [
        'name'          => 'Assurance responsabilité civile',
        'code'          => 'civil_liability',
        'folder_code'   => 'insurance_documents'
    ],
    [
        'name'          => 'Assurance protection juridique',
        'code'          => 'legal_protection',
        'folder_code'   => 'insurance_documents'
    ],
    [
        'name'          => 'Assurance accident du travail',
        'code'          => 'work_accident',
        'folder_code'   => 'insurance_documents'
    ]
]);

$createDocumentType([
    'id'                    => 26,
    'name'                  => 'Salarié',
    'code'                  => 'concierge_staff_document',
    'folder_code'           => 'staff_documents',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Contrat concierge',
        'code'          => 'concierge_contract',
        'folder_code'   => 'staff_documents'
    ],
    [
        'name'          => 'Document chèques repas',
        'code'          => 'meal_voucher_document',
        'folder_code'   => 'staff_documents'
    ],
    [
        'name'          => 'Prévention et protection du travail',
        'code'          => 'workplace_prevention_protection',
        'folder_code'   => 'staff_documents'
    ],
    [
        'name'          => 'Contrat téléphone / internet / TV concierge',
        'code'          => 'concierge_telecom_contract',
        'folder_code'   => 'staff_documents'
    ]
]);

$createDocumentType([
    'id'                    => 27,
    'name'                  => 'Conciergerie',
    'code'                  => 'concierge_lease_document',
    'folder_code'           => 'concierge_lease_documents',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Bail conciergerie',
        'code'          => 'contract_concierge_lease',
        'folder_code'   => 'concierge_lease_documents'
    ],
    [
        'name'          => 'Etat des lieux',
        'code'          => 'inventory_concierge_lease',
        'folder_code'   => 'concierge_lease_documents'
    ],
    [
        'name'          => 'Garantie locative',
        'code'          => 'rental_guarantee_deposit',
        'folder_code'   => 'concierge_lease_documents'
    ]
]);

$createDocumentType([
    'id'                    => 28,
    'name'                  => 'Fiches signalétiques copropriétaires',
    'code'                  => 'ownership_identification_document',
    'folder_code'           => 'ownership_identification_sheets',
    'document_visibility'   => 'ownership'
]);

$createDocumentType([
    'id'                    => 29,
    'name'                  => 'Document du conseil de copropriété',
    'code'                  => 'condominium_council_document',
    'folder_code'           => 'condominium_council',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'PV du conseil de copropriété',
        'code'          => 'condominium_council_minutes',
        'folder_code'   => 'condominium_council'
    ],
    [
        'name'          => 'Document du conseil de copropriété',
        'code'          => 'document',
        'folder_code'   => 'condominium_council'
    ]
]);

$createDocumentType([
    'id'                    => 30,
    'name'                  => 'Document du commissaire aux comptes',
    'code'                  => 'auditor_document',
    'folder_code'           => 'auditor_documents',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Rapport du commissaire aux comptes',
        'code'          => 'auditor_report',
        'folder_code'   => 'auditor_documents'
    ],
    [
        'name'          => 'Documents pour le commissaire aux comptes',
        'code'          => 'auditor_documents',
        'folder_code'   => 'auditor_documents'
    ]
]);

$createDocumentType([
    'id'                    => 33,
    'name'                  => 'Document de contentieux',
    'code'                  => 'litigation_document',
    'folder_code'           => 'litigation_files',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Dossier de contentieux',
        'code'          => 'case_file',
        'folder_code'   => 'litigation_files'
    ]
]);

$createDocumentType([
    'id'                    => 34,
    'name'                  => 'Suivi entretien',
    'code'                  => 'maintenance_log',
    'folder_code'           => 'maintenance_logs',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Suivi entretien Ascenseur',
        'code'          => 'elevator_maintenance',
        'folder_code'   => 'maintenance_logs'
    ],
    [
        'name'          => 'Suivi entretien Chauffage',
        'code'          => 'heating_maintenance',
        'folder_code'   => 'maintenance_logs'
    ],
    [
        'name'          => 'Suivi entretien Nettoyage',
        'code'          => 'cleaning_maintenance',
        'folder_code'   => 'maintenance_logs'
    ],
    [
        'name'          => 'Suivi entretien Egouts',
        'code'          => 'sewer_maintenance',
        'folder_code'   => 'maintenance_logs'
    ],
    [
        'name'          => 'Suivi entretien Nuisibles',
        'code'          => 'pest_control_maintenance',
        'folder_code'   => 'maintenance_logs'
    ],
    [
        'name'          => 'Suivi entretien Jardin',
        'code'          => 'garden_maintenance',
        'folder_code'   => 'maintenance_logs'
    ],
    [
        'name'          => 'Suivi entretien Porte de garage / barrière',
        'code'          => 'garage_door_maintenance',
        'folder_code'   => 'maintenance_logs'
    ],
    [
        'name'          => 'Suivi entretien Toiture',
        'code'          => 'roof_maintenance',
        'folder_code'   => 'maintenance_logs'
    ],
    [
        'name'          => 'Suivi entretien protection incendie',
        'code'          => 'fire_safety_maintenance',
        'folder_code'   => 'maintenance_logs'
    ]
]);

$createDocumentType([
    'id'                    => 35,
    'name'                  => 'Document de sinistre',
    'code'                  => 'claim_document',
    'folder_code'           => 'claims',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Rapport d\'expertise sinistre',
        'code'          => 'expert_report',
        'folder_code'   => 'claims'
    ],
    [
        'name'          => 'PV d\'indemnisation',
        'code'          => 'claim_report',
        'folder_code'   => 'claims'
    ]
]);

$createDocumentType([
    'id'                    => 36,
    'name'                  => 'Document de travaux',
    'code'                  => 'works_document',
    'folder_code'           => 'works_and_repairs',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Devis',
        'code'          => 'quote',
        'folder_code'   => 'works_and_repairs'
    ],
    [
        'name'          => 'Bon de commande',
        'code'          => 'purchase_order',
        'folder_code'   => 'works_and_repairs'
    ],
    [
        'name'          => 'Rapport d\'expert travaux',
        'code'          => 'works_expert_report',
        'folder_code'   => 'works_and_repairs'
    ]
]);

$createDocumentType([
    'id'                    => 16,
    'name'                  => 'Document de mutation',
    'code'                  => 'ownership_transfer_document',
    'folder_code'           => 'ownership_transfers',
    'description'           => 'Courriers relatifs aux transferts de propriété.',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Demande d\'informations',
        'code'          => 'notary_correspondence',
        'folder_code'   => 'ownership_transfers'
    ],
    [
        'name'          => 'Confirmation d\'acte',
        'code'          => 'transfer_deed',
        'folder_code'   => 'ownership_transfers'
    ]
]);

$createDocumentType([
    'id'                    => 37,
    'name'                  => 'Document relatif aux accès',
    'code'                  => 'access_device_document',
    'folder_code'           => 'access_devices',
    'document_visibility'   => 'agency',
    'has_subtype'           => true
], [
    [
        'name'          => 'Plaquette',
        'code'          => 'plate',
        'folder_code'   => 'access_devices'
    ],
    [
        'name'          => 'Clé',
        'code'          => 'key',
        'folder_code'   => 'access_devices'
    ],
    [
        'name'          => 'Télécommande',
        'code'          => 'remote_control',
        'folder_code'   => 'access_devices'
    ],
    [
        'name'          => 'Badge',
        'code'          => 'badge',
        'folder_code'   => 'access_devices'
    ]
]);

$createDocumentType([
    'id'                    => 19,
    'name'                  => 'Bilan',
    'code'                  => 'balance_sheet',
    'folder_code'           => 'operation_statements',
    'description'           => 'Bilan comptable.',
    'document_visibility'   => 'agency'
]);

$createDocumentType([
    'id'                    => 20,
    'name'                  => 'Récapitulatif des frais',
    'code'                  => 'expense_summary',
    'folder_code'           => 'operation_statements',
    'description'           => 'Dépenses courantes.',
    'document_visibility'   => 'agency'
]);

$createDocumentType([
    'id'                    => 31,
    'name'                  => 'Import Fournisseurs',
    'code'                  => 'suppliers_import',
    'folder_code'           => 'imports',
    'description'           => 'Fichiers d\'imports Fournisseurs (temporaire).',
    'document_visibility'   => 'agency'
]);

$createDocumentType([
    'id'                    => 32,
    'name'                  => 'Import Copropriété',
    'code'                  => 'condominium_import',
    'folder_code'           => 'imports',
    'description'           => 'Fichiers d\'imports Copropriété (temporaire).',
    'document_visibility'   => 'agency'
]);

$createDocumentType([
    'id'                    => 38,
    'name'                  => 'Import Banques',
    'code'                  => 'banks_import',
    'folder_code'           => 'imports',
    'document_visibility'   => 'agency'
]);
