<?php

use fmt\setting\Setting;

// Init script for symbiose-related settings (default language: en)

Setting::assert_value('sale', 'order', 'sequence_format', '%05d{sequence}');
Setting::assert_value('sale', 'order', 'option_validity', 10);
Setting::assert_value('sale', 'accounting', 'invoice.sequence_format', '%2d{year}/%05d{sequence}');
Setting::assert_value('sale', 'accounting', 'account_sales', '700');
Setting::assert_value('sale', 'accounting', 'account_sales-taxes', '451');
Setting::assert_value('sale', 'accounting', 'account_trade-debtors', '400');
Setting::assert_value('sale', 'accounting', 'downpayment_sku', 'DOWNPAYMENT');
Setting::assert_value('sale', 'accounting', 'account_downpayment', '460', ['organisation_id' => 1]);
Setting::assert_value('finance', 'accounting', 'fiscal_year', 2025);
Setting::assert_value('finance', 'accounting', 'accounting_entry.sequence_format', '%s{journal}/%02d{year}/%05d{sequence}', ['organisation_id' => 1]);

Setting::assert_sequence('sale', 'accounting', 'invoice.sequence', 1, ['organisation_id' => 1]);
Setting::assert_sequence('finance', 'accounting', 'accounting_entry.sequence', 1, ['organisation_id' => 1]);

Setting::assert_value('identity', 'organization', 'identity_type_default', 3);
Setting::assert_value('identity', 'organization', 'identity_lang_default', 2);

// Affichage du détail par lot (annexe)
Setting::assert_value('realestate', 'features', 'expense_statement.show_lots_details', false);
// Présentation regroupée par lot principal
Setting::assert_value('realestate', 'features', 'expense_statement.enable_lots_grouping', true);
// Affichage du détail TVA
Setting::assert_value('realestate', 'features', 'expense_statement.show_vat_detail', false);
// Distinction propriétaire / locataire
Setting::assert_value('realestate', 'features', 'expense_statement.show_owner_tenant_split', true);
// Addition de la part occupant dans la part proprio
/*
    colonne LOC toujours à titre indicatif (montant qui peut être demandé au LOC)
    - enabled: colonne PROP = LOT
    - disabled: colonne PROPLOT - LOC
*/
Setting::assert_value('realestate', 'features', 'expense_statement.enable_tenant_rollup', false);
// Regroupement par compte collecteur (contrôle)
Setting::assert_value('realestate', 'features', 'expense_statement.enable_accounts_grouping', false);
// Forcer nombre de pages pair
Setting::assert_value('realestate', 'features', 'expense_statement.enable_force_even_pages', true);


// nombre de jours après dépassement de la date d'échéance
Setting::assert_value('realestate', 'features', 'payment_reminder.first_delay_days', 2);
// délai d'attente avant envoi de deux rappels successifs
Setting::assert_value('realestate', 'features', 'payment_reminder.repeat_delay_days', 15);
// montant minimum pour un rappel
Setting::assert_value('realestate', 'features', 'payment_reminder.min_amount', 50);
Setting::assert_value('realestate', 'features', 'payment_reminder.max_reminder_level', 2);
Setting::assert_value('realestate', 'features', 'payment_reminder.is_first_reminder_billable', true);
Setting::assert_value('realestate', 'features', 'payment_reminder.auto_generate_correspondence', false);
// #todo to confirm
Setting::assert_value('realestate', 'features', 'payment_reminder.auto_generate_accounting_entries', false);


// to be configured by condominium
Setting::assert_value('realestate', 'features', 'reminder_penalty.fixed_fee', 0.0);
Setting::assert_value('realestate', 'features', 'reminder_penalty.percentage_fee', 0.0);
Setting::assert_value('realestate', 'features', 'reminder_penalty.interest_rate_monthly', 0.0);
// Setting::assert_value('realestate', 'features', 'reminder_penalty.accounting_account_id', true);
