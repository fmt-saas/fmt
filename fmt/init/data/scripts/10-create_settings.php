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
Setting::assert_value('realestate', 'features', 'expense_statement.show_lots_details', 0);
// Présentation regroupée par lot principal
Setting::assert_value('realestate', 'features', 'expense_statement.enable_lots_grouping', 1);
// Affichage du détail TVA
Setting::assert_value('realestate', 'features', 'expense_statement.show_vat_detail', 0);
// Distinction propriétaire / locataire
Setting::assert_value('realestate', 'features', 'expense_statement.show_owner_tenant_split', 1);
// Addition de la part occupant dans la part proprio
/*
    colonne LOC toujours à titre indicatif (montant qui peut être demandé au LOC)
    - enabled: colonne PROP = LOT
    - disabled: colonne PROPLOT - LOC
*/
Setting::assert_value('realestate', 'features', 'expense_statement.enable_tenant_rollup', 0);
// Regroupement par compte collecteur (contrôle)
Setting::assert_value('realestate', 'features', 'expense_statement.enable_accounts_grouping', 0);
// Forcer nombre de pages pair
Setting::assert_value('realestate', 'features', 'expense_statement.enable_force_even_pages', 1);
