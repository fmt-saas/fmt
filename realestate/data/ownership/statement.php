<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\accounting\Account;
use finance\accounting\FiscalPeriod;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\PropertyLot;
use realestate\purchase\accounting\AccountingEntry;
use realestate\purchase\accounting\invoice\InvoiceLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Run the given pipeline.',
    'params'        => [
        'fiscal_period_id' => [
            'type'          => 'many2one',
            'object_class'  => 'finance\accounting\FiscalPeriod',
            'description'   => ''
        ]
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'UTF-8',
        'accept-origin'     => '*'
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;


$fiscalPeriod = FiscalPeriod::id($params['fiscal_period_id'])->read(['condo_id', 'date_from', 'date_to'])->first();

if(!$fiscalPeriod) {
    throw new Exception('unknown_period', EQ_ERROR_INVALID_PARAM);
}

// compute number of calendar days within the period
$nb_days = round(($fiscalPeriod['date_to'] - $fiscalPeriod['date_from']) / 86400, 0) + 1;


// fetch relevant accounting entries that apply to the chosen period
$accountingEntries = AccountingEntry::search([
        ['fiscal_period_id', '=', $fiscalPeriod['id']],
        ['status', '=', 'validated'],
        ['invoice_id', '<>', null]
    ])
    ->read([
        'entry_lines_ids' => ['invoice_line_id', 'account_id', 'account_code', 'debit', 'credit']
    ]);


/*
    Prefetch required objects (condominium configuration)
 */

$ownerships = Ownership::search(['condo_id', '=', $fiscalPeriod['condo_id']])
    ->read(['name', 'property_lots_ids'])
    ->get();


// map all condo apportionment by property lot
$map_apportionments = [];
$apportionments = Apportionment::search(['condo_id', '=', $fiscalPeriod['condo_id']])->read(['name', 'total_shares', 'apportionment_shares_ids' => ['property_lot_id', 'property_lot_shares']])->get();
foreach($apportionments as $apportionment_id => $apportionment) {
    $map_apportionments[$apportionment_id] = [];
    foreach($apportionment['apportionment_shares_ids'] as $apportionment_share_id => $apportionmentShare) {
        $map_apportionments[$apportionment_id][$apportionmentShare['property_lot_id']] = $apportionmentShare['property_lot_shares'];
    }
}

$map_accounts_ids = [];
$map_property_lots_ids = [];
$map_invoice_lines_ids = [];

$map_result = [];


/*
    We build a resulting map with the following hierarchy:
    ownership > property_lot > {expense type} > account > apportionment > {share}

    - {expense type} : is based on on the code of the account associated to each accounting entry line, and can be amongst these: 'private_expense', 'common_expense', 'reserve_fund'
    - {share} : there are always two keys: 'owner' and 'tenant'. For reserve_fund, owner is always 100.
    - apportionment : for private expense, we usa a fake apportionment ('0'), so that the structure remains the same in all situations.

 */
foreach($accountingEntries as $accountingEntry) {
    foreach($accountingEntry['entry_lines_ids'] as $accountingEntryLine) {
        // consider only debit entry lines
        if($accountingEntryLine['debit'] <= 0.0) {
            continue;
        }

        // 1) private expense
        if(substr($accountingEntryLine['account_code'], 0, 3) === '643') {
            $invoiceLine = InvoiceLine::id($accountingEntryLine['invoice_line_id'])->read(['owner_share', 'tenant_share', 'ownership_id', 'property_lot_id'])->first();
            if(!$invoiceLine) {
                throw new \Exception('missing_mandatory_invoice_line', EQ_ERROR_INVALID_CONFIG);
            }

            $ownership_id = $invoiceLine['ownership_id'];
            $property_lot_id = $invoiceLine['property_lot_id'];
            $amount = $accountingEntryLine['debit'];
            if(!isset($map_result[$ownership_id][$property_lot_id]['private_expense'][$accountingEntryLine['account_id']][0])) {
                $map_result[$ownership_id][$property_lot_id]['private_expense'][$accountingEntryLine['account_id']][0] = [
                        'owner'     => 0.0,
                        'tenant'    => 0.0
                    ];
            }
            $amount_owner = round($amount * ($invoiceLine['owner_share'] / 100), 2);
            $amount_tenant = round($amount - $amount_owner, 2);

            $map_result[$ownership_id][$property_lot_id]['private_expense'][$accountingEntryLine['account_id']][0]['owner'] += $amount_owner;
            $map_result[$ownership_id][$property_lot_id]['private_expense'][$accountingEntryLine['account_id']][0]['tenant'] += $amount_tenant;

            $map_accounts_ids[$accountingEntryLine['account_id']] = true;
            $map_property_lots_ids[$property_lot_id] = true;
            // for private expense, we'll need the line description
            $map_invoice_lines_ids[$accountingEntryLine['invoice_line_id']] = true;
        }
        // 2 a) deferred common expense
        if(substr($accountingEntryLine['account_code'], 0, 3) === '490') {
            $invoiceLine = InvoiceLine::id($accountingEntryLine['invoice_line_id'])->read(['expense_account_id', 'apportionment_id', 'owner_share', 'tenant_share'])->first();
            if(!$invoiceLine) {
                throw new \Exception('missing_mandatory_invoice_line', EQ_ERROR_INVALID_CONFIG);
            }

            $apportionment = $map_apportionments[$invoiceLine['apportionment_id']];

            foreach($ownerships as $ownership_id => $ownership) {
                foreach($ownership['property_lots_ids'] as $property_lot_id) {
                    if(!isset($apportionment[$property_lot_id])) {
                        continue;
                    }
                    $shares = $apportionment[$property_lot_id];
                    $total_shares = $apportionments[$invoiceLine['apportionment_id']]['total_shares'];
                    $amount = $accountingEntryLine['debit'] * $shares / $total_shares;
                    if(!isset($map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['expense_account_id']][$invoiceLine['apportionment_id']])) {
                        $map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['expense_account_id']][$invoiceLine['apportionment_id']] = [
                                'shares'        => $shares,
                                'total_shares'  => $total_shares,
                                'owner'         => 0.0,
                                'tenant'        => 0.0
                            ];
                    }
                    $amount_owner = round($amount * ($invoiceLine['owner_share'] / 100), 2);
                    $amount_tenant = round($amount - $amount_owner, 2);

                    $map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['expense_account_id']][$invoiceLine['apportionment_id']]['owner'] -= $amount_owner;
                    $map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['expense_account_id']][$invoiceLine['apportionment_id']]['tenant'] -= $amount_tenant;
                    $map_property_lots_ids[$property_lot_id] = true;
                }
            }
            $map_accounts_ids[$invoiceLine['expense_account_id']] = true;
        }
        // 2 b) common expense
        elseif(substr($accountingEntryLine['account_code'], 0, 2) === '61') {
            $invoiceLine = InvoiceLine::id($accountingEntryLine['invoice_line_id'])->read(['apportionment_id', 'owner_share', 'tenant_share'])->first();
            if(!$invoiceLine) {
                throw new \Exception('missing_mandatory_invoice_line', EQ_ERROR_INVALID_CONFIG);
            }

            $apportionment = $map_apportionments[$invoiceLine['apportionment_id']];

            foreach($ownerships as $ownership_id => $ownership) {
                foreach($ownership['property_lots_ids'] as $property_lot_id) {
                    if(!isset($apportionment[$property_lot_id])) {
                        continue;
                    }
                    $shares = $apportionment[$property_lot_id];
                    $total_shares = $apportionments[$invoiceLine['apportionment_id']]['total_shares'];
                    $amount = $accountingEntryLine['debit'] * $shares / $total_shares;
                    if(!isset($map_result[$ownership_id][$property_lot_id]['common_expense'][$accountingEntryLine['account_id']][$invoiceLine['apportionment_id']])) {
                        $map_result[$ownership_id][$property_lot_id]['common_expense'][$accountingEntryLine['account_id']][$invoiceLine['apportionment_id']] = [
                                'shares'        => $shares,
                                'total_shares'  => $total_shares,
                                'owner'         => 0.0,
                                'tenant'        => 0.0
                            ];
                    }
                    $amount_owner = round($amount * ($invoiceLine['owner_share'] / 100), 2);
                    $amount_tenant = round($amount - $amount_owner, 2);

                    $map_result[$ownership_id][$property_lot_id]['common_expense'][$accountingEntryLine['account_id']][$invoiceLine['apportionment_id']]['owner'] += $amount_owner;
                    $map_result[$ownership_id][$property_lot_id]['common_expense'][$accountingEntryLine['account_id']][$invoiceLine['apportionment_id']]['tenant'] += $amount_tenant;
                    $map_property_lots_ids[$property_lot_id] = true;
                }
            }
            $map_accounts_ids[$accountingEntryLine['account_id']] = true;
        }
        // 3) reserve fund
        elseif(substr($accountingEntryLine['account_code'], 0, 4) === '6816') {
            $account = Account::id($accountingEntryLine['account_id'])->read(['apportionment_id'])->first();
            $apportionment = $map_apportionments[$account['apportionment_id']];
            foreach($ownerships as $ownership_id => $ownership) {
                foreach($ownership['property_lots_ids'] as $property_lot_id) {
                    if(!isset($apportionment[$property_lot_id])) {
                        continue;
                    }
                    $shares = $apportionment[$property_lot_id];
                    $total_shares = $apportionments[$account['apportionment_id']]['total_shares'];
                    $amount = $accountingEntryLine['debit'] * $shares / $total_shares;
                    if(!isset($map_result[$ownership_id][$property_lot_id]['reserve_fund'][$accountingEntryLine['account_id']][$account['apportionment_id']])) {
                        $map_result[$ownership_id][$property_lot_id]['reserve_fund'][$accountingEntryLine['account_id']] = [
                                'shares'        => $shares,
                                'total_shares'  => $total_shares,
                                'owner'         => 0.0,
                                'tenant'        => 0.0
                            ];
                    }
                    $map_result[$ownership_id][$property_lot_id]['reserve_fund'][$accountingEntryLine['account_id']][$account['apportionment_id']]['owner'] -= $amount * ($invoiceLine['owner_share'] / 100);
                    $map_property_lots_ids[$property_lot_id] = true;
                }
            }
            $map_accounts_ids[$accountingEntryLine['account_id']] = true;
        }
    }
}

// read all implied accounts at once
$accounts = Account::ids(array_keys($map_accounts_ids))
    ->read(['name', 'code'])
    ->get();


$propertyLots = PropertyLot::ids(array_keys($map_property_lots_ids))
    ->read(['name', 'property_lot_code', 'property_lot_ref', 'nature_id' => ['name']])
    ->get();

$invoiceLines = InvoiceLine::ids(array_keys($map_invoice_lines_ids))
    ->read(['description'])
    ->get();


// generate output response

$response = [];

foreach($map_result as $ownership_id => $list_property_lots) {
    $owner = [
            'id'                => $ownership_id,
            'name'              => $ownerships[$ownership_id]['name'],
            'property_lots'     => []
        ];

    foreach($list_property_lots as $property_lot_id => $list_expenses) {

        $property_lot = [
                'id'                => $ownership_id,
                'name'              => $propertyLots[$property_lot_id]['name'],
                'code'              => $propertyLots[$property_lot_id]['property_lot_code'],
                'ref'               => $propertyLots[$property_lot_id]['property_lot_ref'],
                'nature'            => $propertyLots[$property_lot_id]['nature_id']['name'],
                'expenses'          => []
            ];

        foreach($list_expenses as $expense_type => $list_accounts) {

            $expense = [
                    'name'          => $expense_type,
                    'accounts'      => []
                ];

            foreach($list_accounts as $account_id => $list_apportionments) {

                $account = [
                        'id'                => $account_id,
                        'name'              => $accounts[$account_id]['name'],
                        'code'              => $accounts[$account_id]['code'],
                        'apportionments'    => []
                    ];

                foreach($list_apportionments as $apportionment_id => $apportionment) {

                    $apportionment['name'] = $apportionments[$apportionment_id]['name'] ?? 'private';
                    $account['apportionments'][] = $apportionment;
                }

                $expense['accounts'][] = $account;
            }

            $property_lot['expenses'][] = $expense;
        }

        $owner['property_lots'][] = $property_lot;
    }

    $response[] = $owner;
}


$context->httpResponse()
    ->body($response)
    ->send();


/*

Output result sample :


```
[
    {
        "id": 2,
        "name": "00000 - Charles MAX",
        "property_lots": [
            {
                "id": 2,
                "name": "00003 - 1C (APPARTEMENT) - 00000 - Charles MAX",
                "code": "00003",
                "ref": "1C",
                "nature": "APPARTEMENT",
                "expenses": [
                    {
                        "name": "common_expense",
                        "accounts": [
                            {
                                "id": 576,
                                "name": "6110002 - Travaux de transformation",
                                "code": "6110002",
                                "apportionments": [
                                    {
                                        "shares": 275,
                                        "total_shares": 1000,
                                        "owner": 874.5,
                                        "tenant": 0,
                                        "name": "0001 - Charges communes (Q. 1000)"
                                    }
                                ]
                            }
                        ]
                    }
                ]
            }
        ]
    },
    {
        "id": 3,
        "name": "00000 - Lucienne PRÉVAUT",
        "property_lots": [
            {
                "id": 3,
                "name": "00001 - 1A (APPARTEMENT) - 00000 - Lucienne PRÉVAUT",
                "code": "00001",
                "ref": "1A",
                "nature": "APPARTEMENT",
                "expenses": [
                    {
                        "name": "common_expense",
                        "accounts": [
                            {
                                "id": 576,
                                "name": "6110002 - Travaux de transformation",
                                "code": "6110002",
                                "apportionments": [
                                    {
                                        "shares": 225,
                                        "total_shares": 1000,
                                        "owner": 715.5,
                                        "tenant": 0,
                                        "name": "0001 - Charges communes (Q. 1000)"
                                    }
                                ]
                            }
                        ]
                    }
                ]
            }
        ]
    },
    {
        "id": 4,
        "name": "00000 - Etienne DUCHEMIN",
        "property_lots": [
            {
                "id": 4,
                "name": "00002 - 1B (APPARTEMENT) - 00000 - Etienne DUCHEMIN",
                "code": "00002",
                "ref": "1B",
                "nature": "APPARTEMENT",
                "expenses": [
                    {
                        "name": "common_expense",
                        "accounts": [
                            {
                                "id": 576,
                                "name": "6110002 - Travaux de transformation",
                                "code": "6110002",
                                "apportionments": [
                                    {
                                        "shares": 250,
                                        "total_shares": 1000,
                                        "owner": 795,
                                        "tenant": 0,
                                        "name": "0001 - Charges communes (Q. 1000)"
                                    }
                                ]
                            }
                        ]
                    }
                ]
            }
        ]
    }
]
```

*/