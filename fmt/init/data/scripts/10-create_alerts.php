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

$model = MessageModel::create([
        'name'          => 'realestate.workflow.assembly.valid',
        'type'          => 'governance',
        'label'         => 'Quorum reached',
        'description'   => "The quorum of presence or represented shares is reached."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Quorum atteint',
        'description'   => "Le quorum de présence ou de parts représentées est atteint.",
    ], 'fr');



/**
 * ACCOUNTING
 */

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_invoice_type',
        'type'          => 'accounting',
        'label'         => 'Missing invoice type',
        'description'   => "The invoice type is mandatory."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Facture sans type',
        'description'   => "Le type de facture est obligatoire.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_condo_id',
        'type'          => 'accounting',
        'label'         => 'Missing Condominium',
        'description'   => "A purchase invoice must relate to a Condominium."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Copropriété manquante',
        'description'   => "Une facture d'achat doit référencer une copropriété.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_fiscal_year_id',
        'type'          => 'accounting',
        'label'         => 'Missing Fiscal Year',
        'description'   => "A purchase invoice must relate to a Fiscal Year."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Exercice comptable manquant',
        'description'   => "Une facture d'achat doit référencer un exercice comptable.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_condo_bank_account_id',
        'type'          => 'accounting',
        'label'         => 'Missing Bank Account',
        'description'   => "The bank account for payment must be specified."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Compte bancaire manquant',
        'description'   => "Le compte bancaire pour le paiement doit être renseigné.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_suppliership_bank_account_id',
        'type'          => 'accounting',
        'label'         => 'Missing Supplier Bank Account',
        'description'   => "The Supplier bank account must be specified for payment."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Compte bancaire fournisseur manquant',
        'description'   => "Le compte bancaire du fournisseur doit être renseigné pour le paiement.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_supplier_invoice_number',
        'type'          => 'accounting',
        'label'         => 'Missing Invoice Number',
        'description'   => "The invoice number (supplier) is mandatory."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Numéro de facture manquant',
        'description'   => "Le numéro de facture est obligatoire.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_payable_amount',
        'type'          => 'accounting',
        'label'         => 'Missing payable amount',
        'description'   => "The total payable amount must be provided."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Total dû manquant',
        'description'   => "Le montant total dû doit être renseigné.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_emission_date',
        'type'          => 'accounting',
        'label'         => 'Missing emission date',
        'description'   => "The invoice emission date must be provided."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Date d\'emision manquante',
        'description'   => "La date d'émission de la facture doit être renseignée.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.invalid_owner_tenant_ratio',
        'type'          => 'accounting',
        'label'         => 'Invalid (non-balanced) owner/tenant ratio',
        'description'   => "The owner/tenant ratio must be balanced and total 100%."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Ratio owner/tenant invalide (non-balancé)',
        'description'   => "Le ratio propriétaire/locataire doit totaliser 100 %.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_mandatory_line_apportionment',
        'type'          => 'accounting',
        'label'         => 'Missing apportionment for income/expense account line.',
        'description'   => "Lines referring to expense or income must have an apportionment set."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Clé de répartition manquante pour les lignes de compte de charge/revenu.',
        'description'   => "Les lignes se rapportant à des comptes de charge ou de revenu doivent avoir une clé de répartition définie.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.non_matching_price',
        'type'          => 'accounting',
        'label'         => 'Inconsistent price & VAT rate',
        'description'   => "Price does not match VAT-excl amount and applicable VAT rate."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Prix incohérent avec taux TVA',
        'description'   => "Le prix incohérent avec montant HTVA et taux de TVA applicable.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.non_matching_lines_total',
        'type'          => 'accounting',
        'label'         => 'Non matching lines total',
        'description'   => "Invoice total and lines total do not match."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Total des lignes incorrect',
        'description'   => "Le total de la facture ne correspond pas au total des lignes.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.duplicate_expense_account',
        'type'          => 'accounting',
        'label'         => 'Duplicate expense account',
        'description'   => "A same expense account cannot be used twice."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Compte de charge en double',
        'description'   => "Un même compte de charge ne peut pas être utilisé deux fois.",
    ], 'fr');


$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_apportionment',
        'type'          => 'accounting',
        'label'         => 'Missing Apportionment (mandatory)',
        'description'   => "Apportionment must be specified."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Clé de répartition manquante',
        'description'   => "La clé de répartition doit être renseignée.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.missing_expense_account',
        'type'          => 'accounting',
        'label'         => 'Missing expense account (mandatory)',
        'description'   => "The expense account must be specified."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Compte de charge manquant',
        'description'   => "Le compte de charge doit être renseigné.",
    ], 'fr');

$model = MessageModel::create([
        'name'          => 'purchase.accounting.invoice.exceeding_fund_allocation',
        'type'          => 'accounting',
        'label'         => 'Fund usage exceeds total',
        'description'   => "Fund usage cannot exceed invoice total."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Utilisation du fonds supérieur au total',
        'description'   => "L'utilisation du fonds ne peut pas être supérieure au montant de la facture.",
    ], 'fr');


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
 * DOCUMENTS IMPORTS
 */
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


/**
 * DOCUMENTS EXPORTS
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
        'description'   => "Au moins un export n'a pas pu être complété.",
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
        'description'   => "L'export demandé est prêt pour le téléchargement.",
    ], 'fr');
