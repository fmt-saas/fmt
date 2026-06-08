<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\navigation\Node;

/**
 * Real estate co-ownership folders
 */

Node::create([
    'code'          => 'reference_documents',
    'node_type'     => 'folder',
    'name'          => 'Statuts & Documents de base',
    'description'   => 'Documents de référence de la copropriété : acte de base, règlement de copropriété, ROI, plans, etc.'
]);

Node::create([
    'code'          => 'general_meetings',
    'node_type'     => 'folder',
    'name'          => 'Assemblées Générales',
    'description'   => 'Convocations, procès-verbaux, listes de présence et documents relatifs aux assemblées générales.'
]);

Node::create([
    'code'          => 'bank_documents',
    'node_type'     => 'folder',
    'name'          => 'Documents bancaires',
    'description'   => 'Documents généraux liés aux comptes bancaires de la copropriété.'
]);

Node::create([
    'code'          => 'operation_statements',
    'node_type'     => 'folder',
    'name'          => 'Relevés des charges et produits',
    'description'   => 'Décomptes de charges, répartitions, appels de fonds et relevés d\'opérations.'
]);

Node::create([
    'code'          => 'bank_statements',
    'node_type'     => 'folder',
    'name'          => 'Relevés bancaires',
    'description'   => 'Relevés bancaires liés aux comptes de la copropriété.'
]);

Node::create([
    'code'          => 'supplier_invoices',
    'node_type'     => 'folder',
    'name'          => 'Factures et notes de crédit fournisseurs',
    'description'   => 'Factures, notes de crédit et documents comptables reçus des fournisseurs.'
]);

Node::create([
    'code'          => 'supplier_contracts',
    'node_type'     => 'folder',
    'name'          => 'Contrats fournisseurs',
    'description'   => 'Contrats de prestation, abonnements, contrats de maintenance et conventions fournisseurs.'
]);

Node::create([
    'code'          => 'supplier_documents',
    'node_type'     => 'folder',
    'name'          => 'Documents fournisseurs',
    'description'   => 'Documents administratifs, attestations, coordonnées bancaires et justificatifs liés aux fournisseurs.'
]);

Node::create([
    'code'          => 'technical_reports',
    'node_type'     => 'folder',
    'name'          => 'Rapports techniques',
    'description'   => 'Rapports techniques, diagnostics, expertises, études et documents de suivi technique.'
]);

Node::create([
    'code'          => 'insurance_documents',
    'node_type'     => 'folder',
    'name'          => 'Documents d\'assurance',
    'description'   => 'Contrats d\'assurance, attestations, déclarations, échanges et documents associés.'
]);

Node::create([
    'code'          => 'staff_documents',
    'node_type'     => 'folder',
    'name'          => 'Documents du personnel',
    'description'   => 'Documents relatifs au personnel, aux prestataires internes ou aux intervenants réguliers.'
]);

Node::create([
    'code'          => 'concierge_lease_documents',
    'node_type'     => 'folder',
    'name'          => 'Conciergerie',
    'description'   => 'Bail, état des lieux, garantie locative et documents relatifs à la conciergerie.'
]);

Node::create([
    'code'          => 'ownership_identification_sheets',
    'node_type'     => 'folder',
    'name'          => 'Fiches d\'identification copropriétaires',
    'description'   => 'Fiches d\'identification, coordonnées et informations administratives des copropriétaires.'
]);

Node::create([
    'code'          => 'condominium_council',
    'node_type'     => 'folder',
    'name'          => 'Conseil de copropriété',
    'description'   => 'Comptes rendus, échanges et documents relatifs au conseil de copropriété.'
]);

Node::create([
    'code'          => 'auditor_documents',
    'node_type'     => 'folder',
    'name'          => 'Documents du commissaire aux comptes',
    'description'   => 'Rapports, contrôles, échanges et documents liés au commissaire aux comptes ou au vérificateur.'
]);

Node::create([
    'code'          => 'litigation_files',
    'node_type'     => 'folder',
    'name'          => 'Dossiers de contentieux',
    'description'   => 'Dossiers de litiges, procédures judiciaires, relances, mises en demeure et échanges associés.'
]);

Node::create([
    'code'          => 'maintenance_logs',
    'node_type'     => 'folder',
    'name'          => "Carnets d\'entretien",
    'description'   => "Carnets d\'entretien, suivis périodiques et historiques de maintenance."
]);

Node::create([
    'code'          => 'inspection_reports',
    'node_type'     => 'folder',
    'name'          => 'Rapports d\'inspection',
    'description'   => 'Rapports de contrôle, inspections réglementaires, vérifications et visites techniques.'
]);

Node::create([
    'code'          => 'claims',
    'node_type'     => 'folder',
    'name'          => 'Sinistres',
    'description'   => 'Déclarations de sinistre, suivis d\'assurance, expertises, échanges et documents associés.'
]);

Node::create([
    'code'          => 'works_and_repairs',
    'node_type'     => 'folder',
    'name'          => 'Travaux et réparations',
    'description'   => 'Interventions, bons de travaux, rapports de réparation, suivis de chantier et documents techniques.'
]);

Node::create([
    'code'          => 'ownership_transfers',
    'node_type'     => 'folder',
    'name'          => 'Mutations',
    'description'   => 'Documents et échanges relatifs aux mutations, ventes et transferts de propriété.'
]);

Node::create([
    'code'          => 'access_devices',
    'node_type'     => 'folder',
    'name'          => 'Dispositifs d\'accès',
    'description'   => 'Badges, clés, télécommandes, accès parking, accès immeuble et documents associés.'
]);

Node::create([
    'code'          => 'imports',
    'node_type'     => 'folder',
    'name'          => 'Imports',
    'description'   => "Documents d\'import de données temporaires ou historiques."
]);

/*
|--------------------------------------------------------------------------
| Deprecated or unused folder codes
|--------------------------------------------------------------------------
| These codes were previously used but are not present in the updated list.
| They are kept here as comments for migration/reference purposes.
|--------------------------------------------------------------------------
*/

// tender_documents -> likely replaced by supplier_contracts, supplier_documents or works_and_repairs
// Node::create([
//     'code'          => 'tender_documents',
//     'node_type'     => 'folder',
//     'name'          => 'Bordereaux / Devis fournisseurs',
//     'description'   => "Appels d'offres, devis, consultation d'entreprises"
// ]);

// council_minutes -> replaced by condominium_council
// Node::create([
//     'code'          => 'council_minutes',
//     'node_type'     => 'folder',
//     'name'          => 'Conseil de copropriété / Comptes rendus',
//     'description'   => 'Comptes rendus des réunions du conseil'
// ]);

// legal_followup -> replaced by litigation_files and/or claims
// Node::create([
//     'code'          => 'legal_followup',
//     'node_type'     => 'folder',
//     'name'          => 'Contentieux / relance',
//     'description'   => 'Courriers de relance, procédures judiciaires, assignations'
// ]);

// insurance_contracts -> replaced by insurance_documents
// Node::create([
//     'code'          => 'insurance_contracts',
//     'node_type'     => 'folder',
//     'name'          => "Contrats d'assurance",
//     'description'   => "Contrats d'assurance, attestations associées"
// ]);

// syndic_contracts -> not present in updated list
// Node::create([
//     'code'          => 'syndic_contracts',
//     'node_type'     => 'folder',
//     'name'          => 'Contrats de syndic',
//     'description'   => 'Contrat désignant le syndic en cours'
// ]);

// sepa_mandates -> not present in updated list
// Node::create([
//     'code'          => 'sepa_mandates',
//     'node_type'     => 'folder',
//     'name'          => 'Mandats de prélèvement',
//     'description'   => 'Mandats SEPA signés'
// ]);

// justifications -> likely replaced by supplier_documents or bank_documents
// Node::create([
//     'code'          => 'justifications',
//     'node_type'     => 'folder',
//     'name'          => 'Pièces justificatives',
//     'description'   => 'RIB, Kbis, attestations URSSAF, etc.'
// ]);

// internal_memos -> likely replaced by general_meetings or condominium_council
// Node::create([
//     'code'          => 'internal_memos',
//     'node_type'     => 'folder',
//     'name'          => 'Procès Verbaux',
//     'description'   => 'PV des assemblées (AGO-AGE), et des conseils de copropriété.'
// ]);
