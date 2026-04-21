<?php

use fmt\setting\Setting;

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
