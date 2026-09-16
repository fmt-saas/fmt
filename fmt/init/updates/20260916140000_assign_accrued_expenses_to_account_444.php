<?php

use finance\accounting\Account;
use finance\accounting\AccountTemplate;

Account::search([
        ['code', '=', '444']
    ])
    ->update(['operation_assignment' => 'accrued_expenses']);

AccountTemplate::search([
        ['code', '=', '444']
    ])
    ->update(['operation_assignment' => 'accrued_expenses']);
