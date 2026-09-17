<?php

use finance\accounting\Account;
use finance\accounting\AccountTemplate;

Account::search([
        ['code', '=', '6500000']
    ])
    ->write(['operation_assignment' => 'bank_fees']);

AccountTemplate::search([
        ['code', '=', '6500000']
    ])
    ->write(['operation_assignment' => 'bank_fees']);
