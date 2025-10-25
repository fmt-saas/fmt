<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

/*
 This script is intended to complete the information from `init/demo`
 in order to produce a coherent environment on which automated and/or manual tests can be conducted.
*/

use documents\recording\RecordingRule;
use documents\recording\RecordingRuleLine;
use finance\accounting\Account;
use finance\accounting\AccountChart;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\accounting\MiscOperation;
use finance\accounting\MiscOperationLine;
use finance\bank\BankStatement;
use finance\bank\BankStatementImport;
use finance\bank\CondominiumBankAccount;
use realestate\finance\accounting\MoneyTransfer;
use hr\employee\Employee;
use hr\role\RoleAssignment;
use identity\Identity;
use identity\User;
use purchase\supplier\Suppliership;
use purchase\supplier\SuppliershipReference;
use realestate\finance\accounting\CondoFund;
use realestate\funding\FundRequest;
use realestate\funding\FundRequestExecution;
use realestate\funding\FundRequestLine;
use realestate\management\ManagingAgent;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\Condominium;
use realestate\property\PropertyLot;

use realestate\purchase\accounting\invoice\PurchaseInvoice;


core\User::id(2)->update(['language' => 'fr']);

$condominiums = Condominium::search()->read(['id', 'account_chart_id', 'bank_accounts_ids']);

$condominiums_ids = $condominiums->ids();

// attach condos to default managing agent
$condominiums->update(['managing_agent_id' => 1]);

$user = User::search(['login', '=', 'admin@fmt.yb.run'])
    ->read(['identity_id'])
    ->first();

$employee = Employee::search(['identity_id', '=', $user['identity_id']])
    ->first();

// assign accountant and condo_manager to default Employee to all Condominiums (mandatory for validation)
foreach($condominiums_ids as $condo_id) {
    RoleAssignment::create([
        'condo_id'      => $condo_id,
        'employee_id'   => $employee['id'],
        'role_id'       => 3
    ]);

    RoleAssignment::create([
        'condo_id'      => $condo_id,
        'employee_id'   => $employee['id'],
        'role_id'       => 4
    ]);
}

$condominiums
    // init condominiums (generate sequences, empty chart of accounts, journals, folders, ...)
    ->transition('validate');

// activate "common expenses" apportionments
Apportionment::search(['condo_id', '<>', null])
    ->transition('validate');

// import & activate chart of accounts
AccountChart::search(['condo_id', '<>', null])
    ->do('import_accounts', ['chart_template_id' => 1])
    ->transition('activation');

// assign accounting accounts to condominium bank accounts
foreach($condominiums as $condominium) {
    CondominiumBankAccount::ids($condominium['bank_accounts_ids'])->transition('validate');
}

// assign default apportionment to accounts
$apportionments = Apportionment::search([['is_statutory', '=', false], ['status', '=', 'validated']])
    ->read(['condo_id']);


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
    ->transition('validate');


// create supplierships for managing agent

// Managing Agent
Suppliership::create(["condo_id" => 1, "supplier_id" => 1]);
Suppliership::create(["condo_id" => 2, "supplier_id" => 1]);

// VIVAQUA
$vivaquaSuppliership = Suppliership::create(["condo_id" => 1, "supplier_id" => 1131])->first();
SuppliershipReference::create(['condo_id' => 1, 'suppliership_id' => $vivaquaSuppliership['id'], 'reference_type' => 'installation_number', 'reference_value' => '4000232058']);

Suppliership::search(['condo_id', '<>', 0])
    ->read(['code'])
    ->transition('validate');


/*
// récupérer toutes les RecordingRules `is_template` et les dupliquer dans le Condominium (condo_id)
$recordingRules = RecordingRule::search(['is_template', '=', true])->read([
        'name',
        'document_type_id',
        'document_subtype_id',
        'recording_rule_lines_ids' => [
            'name', 'account_code', 'apportionment_code', 'owner_share', 'tenant_share', 'share'
        ]
    ]);

foreach($recordingRules as $recordingRule) {
    $newRecordingRule = RecordingRule::create(['condo_id' => 1, 'name' => 'facture d\'acompte EAU', 'document_type_id' => 1, 'document_subtype_id' => 1])->first();
    foreach($recordingRule['recording_rule_lines_ids'] as $recordingRuleLine) {
        RecordingRuleLine::create(['name' => 'eau', 'condo_id' => 1, 'recording_rule_id' => $recordingRule['id'], 'account_id' => 635, 'apportionment_id' => 1, 'owner_share' => 100, 'tenant_share' => 0, 'share' => 1.0]);
    }
}
*/

$recordingRule = RecordingRule::create([
        'condo_id'              => 1,
        'name'                  => 'facture d\'acompte EAU',
        'document_type_id'      => 1,
        'document_subtype_id'   => 1,
        'supplier_type_id'      => 3
    ])->first();

RecordingRuleLine::create([
        'name' => 'eau',
        'condo_id' => 1,
        'recording_rule_id' => $recordingRule['id'],
        'account_id' => 635,
        'apportionment_id' => 1,
        'owner_share' => 100,
        'tenant_share' => 0,
        'share' => 1.0
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

CondoFund::create([
        'description'           => 'Fonds de roulement',
        'condo_id'              => 1,
        'apportionment_id'      => 2,
        'fund_type'             => 'working_fund'
    ])
    ->transition('validate');

CondoFund::create([
        'description'           => 'Fonds de réserve',
        'condo_id'              => 1,
        'apportionment_id'      => 2,
        'fund_type'             => 'reserve_fund'
    ])
    ->transition('validate');

// create a fund request for first condo
$fundRequest = FundRequest::create([
        'name'                      => 'provisions',
        'condo_id'                  => $condominiums_ids[0],
        'fiscal_year_id'            => 1,
        'fiscal_period_id'          => 1,
        'request_type'              => 'expense_provisions',
        'request_account_id'        => 712,
        'request_bank_account_id'   => 2,
        'payment_terms_id'          => 1,
        'has_date_range'            => true,
        'date_range_frequency'      => 3,
        'date_from'                 => strtotime('2024-01-01'),
        'date_to'                   => strtotime('2024-12-31')
    ])
    ->first();

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
// #memo - FundRequestExecution uses same table as Sale Invoice, so we need to specify the invoice_type
FundRequestExecution::search([
        ['invoice_type', '=', 'fund_request'],
        ['fund_request_id', '=', $fundRequest['id']]
    ],
    [
        'sort' => [ 'posting_date' => 'asc' ],
        'limit' => 1
    ])
    ->transition('call');


$suppliership = Suppliership::search(['condo_id', '=',  1])->read(['supplier_id' => ['identity_id' => ['bank_accounts_ids']]])->first(true);

$purchaseInvoice = PurchaseInvoice::create([
        'condo_id'                      => $condominiums_ids[0],
        'status'                        => 'proforma',
        'invoice_type'                  => 'invoice',
        'emission_date'                 => strtotime('2024-01-01T00:00:00Z'),
        'posting_date'                  => strtotime('2024-01-01T00:00:00Z'),
        'due_date'                      => strtotime('2024-03-01T00:00:00Z'),
        'description'                   => 'services',
        'supplier_invoice_number'       => '1234567',
        'suppliership_id'               => $suppliership['id'],
        'suppliership_bank_account_id'  => current($suppliership['supplier_id']['identity_id']['bank_accounts_ids'] ?? [])
    ])
    ->first();


/*
// create a miscellaneous operation: transfer amount to the savings account
$miscOperation = MiscOperation::create([
        'condo_id'          => 1,
        'name'              => 'Reprise compte épargne',
        'description'       => 'Reprise compte épargne',
        'posting_date'      => strtotime('2024-01-01T00:00:00Z'),
        'journal_id'        => 11
    ])
    ->first();


MiscOperationLine::create([
        'condo_id'          => 1,
        'misc_operation_id' => $miscOperation['id'],
        'account_id'        => 676,
        'credit'            => 5000.00,
        'debit'             => 0.0
    ]);

MiscOperationLine::create([
        'condo_id'          => 1,
        'misc_operation_id' => $miscOperation['id'],
        'account_id'        => 468,
        'debit'             => 5000.00,
        'credit'            => 0.0
    ]);

MiscOperation::id($miscOperation['id'])
    ->transition('publish')
    ->transition('post');

// create bank operation: move amount from savings account to current account
$moneyTransfer = MoneyTransfer::create([
        'condo_id'                    => 1,
        'posting_date'                => time(),
        'description'                 => 'Money transfer',
        'amount'                      => 5000,
        'bank_account_id'             => 138,
        'counterpart_bank_account_id' => 137
    ])
    ->transition('publish')
    ->transition('send');

// load a bank statement, validate it, and account for the variation on the concerned account
$data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_isabel_demo.xlsx');

BankStatementImport::create()
    ->update(['name' => 'Bank statement import'])
    ->update(['data' => $data]);

BankStatement::search(['condo_id', '=', 1], ['sort' => ['date' => 'desc'], 'limit' => 2])
    ->do('attempt_reconcile')
    ->transition('post');
*/