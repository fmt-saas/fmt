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

use finance\accounting\Account;
use finance\accounting\AccountChart;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use hr\employee\Employee;
use hr\role\RoleAssignment;
use identity\Identity;
use identity\User;
use purchase\supplier\Suppliership;
use realestate\finance\accounting\ReserveFund;
use realestate\funding\FundRequest;
use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestLine;
use realestate\management\ManagingAgent;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\Condominium;
use realestate\property\PropertyLot;

use realestate\purchase\accounting\invoice\Invoice as PurchaseInvoice;

$condominiums = Condominium::search()->read(['id', 'account_chart_id']);

$condominiums_ids = $condominiums->ids();

// attach condos to default managing agent
ManagingAgent::id(1)->update(['condominiums_ids' => $condominiums_ids]);

$condominiums
    // init condominiums (generate sequences, chart of accounts, journals, folders, ...)
    ->do('init');

// activate "common expenses" apportionments
Apportionment::search(['condo_id', '<>', null])
    ->transition('publish');

// import & activate chart of accounts
AccountChart::search(['condo_id', '<>', null])
    ->do('import_accounts', ['chart_template_id' => 1])
    ->transition('activation');

$apportionments = Apportionment::search([['is_statutory', '=', false], ['status', '=', 'published']])
    ->read(['condo_id']);

// assign default apportionment to accounts
foreach($apportionments as $apportionment_id => $apportionment) {
    $account = Account::search([
            [
                ['condo_id', '=', $apportionment['condo_id']],
                ['is_control_account', '=', false],
                ['operation_assignment', '=', 'reserve_fund']
            ],
            [
                ['condo_id', '=', $apportionment['condo_id']],
                ['is_control_account', '=', false],
                ['code', 'like', '61%']
            ]
        ])
        ->update(['apportionment_id' => $apportionment_id]);
}


// assign codes to entities depending on Condominium
PropertyLot::search()
    ->read(['code']);

Ownership::search()
    ->read(['code'])
    ->do('init');


// create supplierships
Suppliership::create(["condo_id" => 1, "supplier_id" => 1]);
Suppliership::create(["condo_id" => 2, "supplier_id" => 1]);

Suppliership::search()
    ->read(['code'])
    ->do('generate_accounts');

$user = User::id(3)
    ->read(['firstname', 'lastname'])
    ->first();

// will create related Identity
$employee = Employee::create()
    ->update([
        'firstname'     => $user['firstname'],
        'lastname'      => $user['lastname'],
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

// open candidate fiscal year
$condominiums
    ->do('open_fiscal_year');

// force computing names
FiscalPeriod::search(['status', '=', 'pending'])
    ->read(['name']);

ReserveFund::create([
        'name'                  => 'fonds de réserve',
        'condo_id'              => 1,
        'fund_account_id'       => 373,
        'expense_account_id'    => 706,
        'apportionment_id'      => 2
    ]);


// create a fund request for first condo
$fundRequest = FundRequest::create([
        'name'                      => 'provisions',
        'condo_id'                  => $condominiums_ids[0],
        'fiscal_year_id'            => 1,
        'fiscal_period_id'          => 1,
        'request_type'              => 'expense_provisions',
        'request_account_id'        => 712,
        'request_date'              => null,
        'request_bank_account_id'   => 2,
        'payment_terms_id'          => 1,
        'has_date_range'            => true,
        'date_range_frequency'      => 3,
        'date_from'                 => strtotime('2024-01-01'),
        'date_to'                   => strtotime('2024-12-31')
    ])
    ->first();


// créer une part
FundRequestLine::create([
        'condo_id'          =>  $condominiums_ids[0],
        'fund_request_id'   => $fundRequest['id'],
        'apportionment_id'  => 2,
        'request_amount'    => 6000.00
    ]);

// activate & generate allocations and executions
FundRequest::id($fundRequest['id'])
    ->transition('activate')
    ->do('generate_allocation')
    ->do('generate_executions');


// call first execution
FundRequestExecution::search([
        ['fund_request_id', '=', $fundRequest['id']]
    ],
    [
        'sort' => [ 'posting_date' => 'desc'],
        'limit' => 1
    ])
    ->transition('call');

$purchaseInvoice = PurchaseInvoice::create([
        'condo_id'                  => $condominiums_ids[0],
        'status'                    => 'proforma',
        'invoice_type'              => 'invoice',
        'emission_date'             => strtotime('2024-01-01T00:00:00Z'),
        'posting_date'              => strtotime('2024-01-01T00:00:00Z'),
        'due_date'                  => strtotime('2024-03-01T00:00:00Z'),
        'description'               => 'services',
        'supplier_invoice_number'   => '1234567',
        'suppliership_id'           => 2
    ])
    ->first();
