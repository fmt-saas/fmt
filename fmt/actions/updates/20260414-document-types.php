<?php

use documents\DocumentType;

DocumentType::create([
        'id'            => 19,
        'name'          => 'Dépenses courantes',
        'code'          => 'balance_sheet',
        'folder_code'   => 'operation_statements',
        'description'   => "Dépenses courantes."
    ]);


DocumentType::create([
        'id'            => 20,
        'name'          => 'Bilan',
        'code'          => 'expense_summary',
        'folder_code'   => 'operation_statements',
        'description'   => "Bilan comptable."
    ]);