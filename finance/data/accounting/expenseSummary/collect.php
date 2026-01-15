<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use finance\accounting\Account;
use realestate\finance\accounting\AccountingEntryLine;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use realestate\purchase\accounting\invoice\PurchaseInvoice;
use finance\bank\BankStatement;
use realestate\property\Apportionment;

[$params, $providers] = eQual::announce([
    'description' => 'Advanced search for current expenses summary.',
    // #memo - this controller is named `collect` but is provides data from its own logic, not directly from the model
    // 'extends'       => 'core_model_collect',
    'params' => [

        /* Rendering fields */

        'apportionment' => [
            'type'              => 'string',
            'readonly'          => true
        ],

        'parent_account' => [
            'type'              => 'string',
            'readonly'          => true
        ],

        'account' => [
            'type'              => 'string',
            'readonly'          => true
        ],

        'description' => [
            'type'              => 'string',
            'readonly'          => true
        ],

        'entry_journal' => [
            'type'              => 'string',
            'readonly'          => true
        ],

        'entry_date' => [
            'type'              => 'date',
            'readonly'          => true
        ],

        'entry_reference' => [
            'type'              => 'string',
            'readonly'          => true
        ],

        'supplier_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'purchase\supplier\Supplier',
            'description'       => 'The supplier the invoice relates to.',
            'readonly'          => true
        ],

        'supplier_reference' => [
            'type'              => 'string',
            'readonly'          => true
        ],

        'owner_share'           => [
            'type'              => 'integer',
            'description'       => "Value, in percent, of the amount to be imputed to the owner when using the account.",
            'readonly'          => true
        ],

        'tenant_share'          => [
            'type'              => 'integer',
            'description'       => "Value, in percent, of the amount to be imputed to the tenant when using the account.",
            'readonly'          => true
        ],

        'vat_rate' => [
            'type'              => 'float',
            'usage'             => 'amount/rate',
            'description'       => 'VAT rate to be applied.',
            'readonly'          => true
        ],

        'amount'          => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => "Value, in percent, of the amount to be imputed to the tenant when using the account.",
        ],

        'domain' => [
            'type'              => 'array',
            'description'       => "Conditional domain.",
            'default'           => []
        ],

        /* Filters */

        'date_from' => [
            'type'    => 'date',
            'default' => null
        ],

        'date_to' => [
            'type'    => 'date',
            'default' => null
        ],

        'condo_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\Condominium',
            'default'        => function ($domain = []) {
                $condo_id = null;
                $origDomain = new Domain($domain);
                foreach($origDomain->getClauses() as $clause) {
                    foreach($clause->getConditions() as $condition) {
                        if($condition->getOperand() === 'condo_id') {
                            $condo_id = $condition->getValue();
                            break 2;
                        }
                    }
                }
                return $condo_id;
            }
        ],

        'fiscal_year_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'finance\accounting\FiscalYear',
            'domain'         => ['condo_id', '=', 'object.condo_id'],
            'default'        => function ($condo_id = null) {
                if(!$condo_id) {
                    return null;
                }
                $fiscal_year_ids = FiscalYear::search([
                        ['status', '=', 'open'],
                        ['condo_id', '=', $condo_id],
                    ],  ['sort' => ['date_from' => 'desc']])
                    ->ids();
                if(count($fiscal_year_ids) <= 0) {
                    $fiscal_year_ids = FiscalYear::search([
                            ['status', '=', 'preopen'],
                            ['condo_id', '=', $condo_id],
                        ],  ['sort' => ['date_from' => 'asc']])
                        ->ids();
                }
                return count($fiscal_year_ids) ? current($fiscal_year_ids) : null;
            }
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;



$condo_id = $params['condo_id'] ?? null;

if(isset($params['domain'])) {
    $origDomain = new Domain($params['domain']);

    foreach($origDomain->getClauses() as $clause) {
        foreach($clause->getConditions() as $condition) {
            if($condition->getOperand() === 'condo_id') {
                $condo_id = $condition->getValue();
                break 2;
            }
        }
    }
}

if(!isset($condo_id)) {
    throw new Exception('missing_condo_id', EQ_ERROR_MISSING_PARAM);
}

$fiscal_year_ids = FiscalYear::search([
        ['status', '=', 'open'],
        ['condo_id', '=', $condo_id],
    ],  ['sort' => ['date_from' => 'desc']])
    ->ids();

if(count($fiscal_year_ids) <= 0) {
    $fiscal_year_ids = FiscalYear::search([
            ['status', '=', 'preopen'],
            ['condo_id', '=', $condo_id],
        ],  ['sort' => ['date_from' => 'asc']])
        ->ids();
}

if(count($fiscal_year_ids)) {
    $domain['fiscal_year_id'] = current($fiscal_year_ids);
}

// build domain
$domain = new Domain($params['domain']);

$domain->addCondition(new DomainCondition('condo_id', '=', $params['condo_id']));

// Resolve date interval
$date_from = null;
$date_to   = null;

if(!empty($params['fiscal_year_id'])) {
    $fiscalYear = FiscalYear::id($params['fiscal_year_id'])
        ->read(['date_from', 'date_to'])
        ->first();

    if($fiscalYear) {
        $date_from = $fiscalYear['date_from'];
        $date_to   = $fiscalYear['date_to'];
    }
}

if(!empty($params['date_from']) && (!$date_from || $params['date_from'] > $date_from)) {
    $date_from = $params['date_from'];
}

if(!empty($params['date_to']) && (!$date_to || $params['date_to'] < $date_to)) {
    $date_to = $params['date_to'];
}

if($date_from && $date_to) {
    $domain->addCondition(new DomainCondition('entry_date', '>=', $date_from));
    $domain->addCondition(new DomainCondition('entry_date', '<=', $date_to));
}

// Only validated entries
$domain->addCondition(new DomainCondition('status', '=', 'validated'));
$domain->addCondition(new DomainCondition('account_class', 'in', [6, 7]));


// Retrieve accounting entry lines
$lines = AccountingEntryLine::search($domain->toArray())
    ->read([
        'account_id',
        'account_class',
        'accounting_entry_id'       => ['name', 'journal_id'],
        'fund_usage_line_id'        => ['apportionment_id', 'invoice_id'],
        'sale_invoice_line_id'      => ['invoice_id'],
        'purchase_invoice_line_id'  => ['is_private_expense', 'apportionment_id', 'owner_share', 'tenant_share', 'vat_rate', 'invoice_id'],
        'bank_statement_line_id'    => ['apportionment_id', 'owner_share', 'tenant_share', 'vat_rate', 'bank_statement_id'],
        'description',
        'entry_date',
        'debit',
        'credit'
    ]);

// load Journals of the condominium
$map_journals = Journal::search([
        ['condo_id', '=', $params['condo_id']]
    ])
    ->read(['mnemo', 'code', 'name', 'description'])
    ->get();


// load Chart of Accounts of the condominium
$map_accounts = Account::search([
        ['condo_id', '=', $params['condo_id']]
    ])
    ->read(['code', 'name', 'parent_account_id', 'description', 'operation_assignment', 'account_nature', 'account_class', 'is_control_account'])
    ->get();

/*
foreach($accounts as $account_id => $account) {
    $map_accounts[$account_id] = [
        'id'                => $account_id,
        'code'              => (string) $account['code'],
        'name'              => (string) $account['name'],
        'parent_account_id' => $account['parent_account_id'] ?? null,
        'description'       => $account['description'],
        'account_nature'    => $account['account_nature'],
        'account_class'     => $account['account_class'],
        'is_control_account'=> $account['is_control_account']
    ];
}
*/

// retrieve storage accounts (collectors) and map with each account
$map_parent_storage = [];

foreach($map_accounts as $account_id => $account) {

    $parent_account_id = $account['parent_account_id'];

    while($parent_account_id) {
        if(!isset($map_accounts[$parent_account_id])) {
            break;
        }
        $parentAccount = $map_accounts[$parent_account_id];
        if($parentAccount['is_control_account']) {
            $map_parent_storage[$account_id] = $parent_account_id;
            break;
        }
        $parent_account_id = $parentAccount['parent_account_id'];
    }
}


$invoices_ids = [];
$statements_ids = [];
$apportionments_ids = [];


$result = [];


// pass-1 - store references of invoices and statements
foreach($lines as $line) {

    if(empty($line['account_id'])) {
        continue;
    }

    $account_id = $line['account_id'];

    if(!isset($map_accounts[$account_id])) {
        // shouldn't occur
        continue;
    }

    $account = $map_accounts[$account_id];

    // ignore lines with account_id not matching class 6 or 7
    if(!in_array($account['account_class'], [6, 7], true)) {
        continue;
    }

    // Source documents
    if($line['purchase_invoice_line_id']) {
        if($line['purchase_invoice_line_id']['invoice_id']) {
            $invoices_ids[] = $line['purchase_invoice_line_id']['invoice_id'];
        }
        if($line['purchase_invoice_line_id']['apportionment_id']) {
            $apportionments_ids[] = $line['purchase_invoice_line_id']['apportionment_id'];
        }
    }
    elseif($line['bank_statement_line_id']) {
        if($line['bank_statement_line_id']['bank_statement_id']) {
            $statements_ids[] = $line['bank_statement_line_id']['bank_statement_id'];
        }
        if($line['bank_statement_line_id']['apportionment_id']) {
            $apportionments_ids[] = $line['bank_statement_line_id']['apportionment_id'];
        }
    }
    elseif($line['fund_usage_line_id']) {
        if($line['fund_usage_line_id']['invoice_id']) {
            $invoices_ids[] = $line['fund_usage_line_id']['invoice_id'];
        }
        if($line['fund_usage_line_id']['apportionment_id']) {
            $apportionments_ids[] = $line['fund_usage_line_id']['apportionment_id'];
        }
    }
}

// Read all involved invoices at once
$map_invoices = [];
$map_statements = [];
$map_apportionments = [];


if(!empty($invoices_ids)) {
    $invoices = PurchaseInvoice::ids($invoices_ids)
        ->read([
            'supplier_id' => ['name'],
            'supplier_invoice_number',
            'emission_date'
        ])
        ->get();

    foreach($invoices as $invoice_id => $invoice) {
        $map_invoices[$invoice_id] = [
            'supplier_id' => $invoice['supplier_id'],
            'supplier_reference' => $invoice['supplier_invoice_number']
        ];
    }
}

if(!empty($statements_ids)) {
    $statements = BankStatement::ids($statements_ids)
        ->read([
            'bank_id' => ['name'],
            'date',
            'statement_number'
        ])
        ->get();

    foreach($statements as $statement_id => $statement) {
        $map_statements[$statement_id] = [
            'supplier_id' => $statement['bank_id'],
            'supplier_reference' => $statement['statement_number']
        ];
    }
}

if(!empty($apportionments_ids)) {
    $map_apportionments = Apportionment::ids($apportionments_ids)
        ->read([
            'name',
            'code',
            'description'
        ])
        ->get();
}

// create a pseudo-apportionment for private expenses
$map_apportionments['private_expense'] = [
    'name'        => 'Frais privatifs',
    'code'        => 'private_expense',
    'description' => 'Dépense non imputable à la copropriété.'
];

$map_apportionments['provisions_restitution'] = [
    'name'        => 'Restitution des provisions appelées',
    'code'        => 'provisions_restitution',
    'description' => 'Restitution des provisions appelées.'
];

$result = [];

foreach($lines as $line_id => $line) {
    if(empty($line['account_id'])) {
        trigger_error("APP::line without account_id in result set", EQ_REPORT_WARNING);
        continue;
    }

    $account_id = $line['account_id'];

    if(!isset($map_accounts[$account_id])) {
        trigger_error("APP::unknown account in result set", EQ_REPORT_WARNING);
        continue;
    }
    $account = $map_accounts[$account_id];

    // ignore non 6/7
    if(!in_array($line['account_class'], [6, 7], true)) {
        trigger_error("APP::line with invalid class in result set", EQ_REPORT_WARNING);
        continue;
    }

    // Prefer grouping by storage/collector if you want "summary" by collector
    $account = $map_accounts[$account_id];

    $is_provision = in_array($account['operation_assignment'], ['expense_provisions', 'work_provisions']);

    $parentAccount = null;
    if(isset($map_parent_storage[$account_id])) {
        $parentAccount = $map_accounts[$map_parent_storage[$account_id]] ?? null;
    }

    $apportionment_id = null;
    $supplier_id = null;
    $supplier_reference = null;
    $owner_share  = 0;
    $tenant_share = 0;

    // Determine origin and fetch doc metadata
    if(!empty($line['purchase_invoice_line_id']['invoice_id'])) {
        $invoice_id = $line['purchase_invoice_line_id']['invoice_id'];
        $apportionment_id = $line['purchase_invoice_line_id']['apportionment_id'] ?? null;
        if($line['purchase_invoice_line_id']['is_private_expense']) {
            $apportionment_id = 'private_expense';
        }
        $owner_share = $line['purchase_invoice_line_id']['owner_share'] ?? 0;
        $tenant_share = $line['purchase_invoice_line_id']['tenant_share'] ?? 0;
        $vat_rate = $line['purchase_invoice_line_id']['vat_rate'] ?? 0.0;

        if(isset($map_invoices[$invoice_id])) {
            $supplier_id = $map_invoices[$invoice_id]['supplier_id'];
            $supplier_reference = $map_invoices[$invoice_id]['supplier_reference'];
        }
    }
    elseif(!empty($line['bank_statement_line_id']['bank_statement_id'])) {
        $statement_id = $line['bank_statement_line_id']['bank_statement_id'];
        $apportionment_id = $line['bank_statement_line_id']['apportionment_id'] ?? null;
        $owner_share = $line['bank_statement_line_id']['owner_share'] ?? 0;
        $tenant_share = $line['bank_statement_line_id']['tenant_share'] ?? 0;
        $vat_rate = $line['bank_statement_line_id']['vat_rate'] ?? 0.0;

        if(isset($map_statements[$statement_id])) {
            $supplier_id = $map_statements[$statement_id]['supplier_id'];
            $supplier_reference = $map_statements[$statement_id]['supplier_reference'];
        }
    }
    elseif(!empty($line['fund_usage_line_id']['invoice_id'])) {
        $invoice_id = $line['fund_usage_line_id']['invoice_id'];
        $apportionment_id = $line['fund_usage_line_id']['apportionment_id'] ?? null;
        $owner_share = 100;
        $vat_rate = 0.0;
    }
    elseif($is_provision) {
        $apportionment_id = 'provisions_restitution';
    }

    $result[] = [
        'id'                 => $line_id,
        'apportionment'      => $map_apportionments[$apportionment_id]['name'] ?? 'Autre',
        'account'            => (string) ($account['name'] ?? ''),
        'parent_account'     => (string) ($parentAccount['name'] ?? ''),
        'description'        => (string) $line['description'],
        'entry_journal'      => $map_journals[$line['accounting_entry_id']['journal_id']]['mnemo'],
        'entry_date'         => $line['entry_date'] ? (date('c', $line['entry_date'])) : null,
        // for sorting
        'timestamp'          => $line['entry_date'] ?? null,
        // keep only last 3 parts
        'entry_reference'    => ($ref = $line['accounting_entry_id']['name'] ?? null) ? implode('/', array_slice(explode('/', $ref), -3)) : null,
        'supplier_id'        => $supplier_id,
        'supplier_reference' => $supplier_reference,
        'owner_share'        => $owner_share,
        'tenant_share'       => $tenant_share,
        'vat_rate'           => $vat_rate,
        'amount'             => round($line['debit'] - $line['credit'], 2)
    ];
}

usort($result, function ($a, $b) {

    // 1. Apportionment
    $cmp = strnatcasecmp($a['apportionment'], $b['apportionment']);
    if($cmp !== 0) {
        return $cmp;
    }

    // 2. Parent account
    $cmp = strnatcasecmp($a['parent_account'], $b['parent_account']);
    if($cmp !== 0) {
        return $cmp;
    }

    // 3. Account
    $cmp = strnatcasecmp($a['account'], $b['account']);
    if($cmp !== 0) {
        return $cmp;
    }

    return $a['timestamp'] <=> $b['timestamp'];
});


$context->httpResponse()
    ->body($result)
    ->send();
