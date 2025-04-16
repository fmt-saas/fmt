<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

/*
 This script is intended to complete the information from `init/demo`
 in order to produce a coherent environment on which automated and/or manual tests can be conducted.
*/

use finance\accounting\AccountChart;
use finance\accounting\FiscalYear;
use hr\employee\Employee;
use hr\role\RoleAssignment;
use identity\Identity;
use identity\User;
use purchase\supplier\Suppliership;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\Condominium;
use realestate\property\PropertyLot;

$condominiums = Condominium::search()->read(['id', 'account_chart_id']);

$condominiums
    // init condominiums (generate sequences, chart of account & journals)
    ->do('init');


// import & activate account chart
AccountChart::search(['condo_id', '<>', null])
    ->do('import_accounts', ['chart_template_id' => 1])
    ->transition('activation');

Apportionment::search(['condo_id', '<>', null])
    ->transition('publish');

// assign codes to entities depending on Condominium
PropertyLot::search()
    ->read(['property_lot_code']);

Ownership::search()
    ->read(['ownership_code'])
    ->do('generate_accounts');

Suppliership::search()
    ->read(['suppliership_code'])
    ->do('generate_accounts');

$user = User::id(3)
    ->read(['id'])
    ->first();

// will create related Identity
$employee = Employee::create()
    ->update([
        'firstname'     => 'Léon',
        'lastname'      => 'Jacques',
        'lang_id'       => 2
    ])
    ->read(['identity_id'])
    ->first();

// link employee to user
Identity::id($employee['identity_id'])
    ->update(['user_id' => $user['id']]);

// assign employee as manager for all condos
RoleAssignment::create([
        'condo_id'      => null,
        'user_id'       => $user['id'],
        'employee_id'   => $employee['id'],
        'role_id'       => 1
    ]);

// create first fiscal year draft
$condominiums
    ->do('create_draft_fiscal_year');

FiscalYear::search(['status', '=', 'draft'])
    ->do('generate_periods')
    ->transition('preopen');

// create following fiscal year draft
$condominiums
    ->do('create_draft_fiscal_year');

FiscalYear::search(['status', '=', 'draft'])
    ->do('generate_periods');

$condominiums
    ->do('open_fiscal_year');