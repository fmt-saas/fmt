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
    'description'   => 'Generate the structure for splitting expenses amongst owners at the closing of a given fiscal period.',
    'help'          => 'This can be used either for generating accounting entries, or document to provide to owners as justification for the subsequent funding request.',
    'params'        => [
        'fiscal_period_id' => [
            'type'          => 'many2one',
            'object_class'  => 'finance\accounting\FiscalPeriod',
            'description'   => 'Period for which the statement is requested.',

            'required'      => true
        ],
        'ownership_id' => [
            'type'          => 'many2one',
            'object_class'  => 'realestate\ownership\Ownership',
            'description'   => 'Optional Ownership for which the statement is requested.',
            'help'          => 'For generating resulting accounting entries, this field must always be left to null.'
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


$fiscalPeriod = FiscalPeriod::id($params['fiscal_period_id'])
    ->read(['condo_id', 'date_from', 'date_to'])
    ->first();

if(!$fiscalPeriod) {
    throw new Exception('unknown_period', EQ_ERROR_INVALID_PARAM);
}

// compute number of calendar days within the period
$nb_days = round(($fiscalPeriod['date_to'] - $fiscalPeriod['date_from']) / 86400, 0) + 1;


// #todo - il faut calculer le nombre de jours pour lesquels chaque propriétaire était effectivement propriétaire de chaque lot concerné à cette période
// il y a la notion de lots groupés - à faire une map, par propriétaire, par lot : on peut le faire par groupe de lots (si un lot est marqué avec primary_lot_id, il peut être ignoré pour les calculs)

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

$ownerships_domain = [ ['condo_id', '=', $fiscalPeriod['condo_id']] ];

if(isset($params['ownership_id'])) {
    $ownerships_domain[] = ['id', '=', $params['ownership_id']];
}

$ownerships = Ownership::search($ownerships_domain)
    ->read(['name', 'date_from', 'date_to', 'property_lots_ids'])
    ->get();


// inject prorata
foreach($ownerships as $ownership_id => $ownership) {
    $start = max($fiscalPeriod['date_from'], $ownership['date_from'] ?? $fiscalPeriod['date_from']);
    $end   = min($fiscalPeriod['date_to'], $ownership['date_to'] ?? $fiscalPeriod['date_to']);
    $ownerships[$ownership_id]['nb_days'] = ($start <= $end) ? (($end-$start)/86400 + 1) : 0;
}

// map all condo apportionment by property lot
$map_apportionments = [];
$apportionments = Apportionment::search(['condo_id', '=', $fiscalPeriod['condo_id']])
    ->read(['name', 'total_shares', 'apportionment_shares_ids' => ['property_lot_id', 'property_lot_shares']])
    ->get();

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

// We need to keep track of the delta between the total entries and the total distributed
// assigned_delta is to be put on the rounding_adjustment account
$assigned_total = 0.0;
$expenses_total = 0.0;

foreach($accountingEntries as $accountingEntry) {
    foreach($accountingEntry['entry_lines_ids'] as $accountingEntryLine) {
        // consider only debit entry lines
        if($accountingEntryLine['debit'] <= 0.0) {
            continue;
        }

        // 1) private expense
        if(substr($accountingEntryLine['account_code'], 0, 3) === '643') {
            $invoiceLine = InvoiceLine::id($accountingEntryLine['invoice_line_id'])->read([
                    'description', 'vat', 'vat_rate', 'owner_share', 'tenant_share', 'ownership_id', 'property_lot_id',
                    'invoice_id' => ['posting_date']
                ])
                ->first();

            if(!$invoiceLine) {
                throw new \Exception('missing_mandatory_invoice_line', EQ_ERROR_INVALID_CONFIG);
            }

            $ownership_id = $invoiceLine['ownership_id'];
            $property_lot_id = $invoiceLine['property_lot_id'];
            $amount = $accountingEntryLine['debit'];

            if(!isset($map_result[$ownership_id][$property_lot_id]['private_expense'][$accountingEntryLine['account_id']][0])) {
                $map_result[$ownership_id][$property_lot_id]['private_expense'][$accountingEntryLine['account_id']][0] = [];
            }

            $amount_owner = round($amount * ($invoiceLine['owner_share'] / 100), 2);
            $amount_tenant = round($amount - $amount_owner, 2);

            $map_result[$ownership_id][$property_lot_id]['private_expense'][$accountingEntryLine['account_id']][0][] = [
                    'owner'         => $amount_owner,
                    'tenant'        => $amount_tenant,
                    'vat'           => $invoiceLine['vat'],
                    'description'   => $invoiceLine['description'],
                    'date'          => date('c', $invoiceLine['invoice_id']['posting_date'])
                ];

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

            $expenses_total += $accountingEntryLine['debit'];
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
                                'total_amount'  => $accountingEntryLine['debit'],
                                'owner'         => 0.0,
                                'tenant'        => 0.0
                            ];
                    }

                    $assigned_total += $amount;

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
            $invoiceLine = InvoiceLine::id($accountingEntryLine['invoice_line_id'])->read(['vat', 'vat_rate', 'apportionment_id', 'owner_share', 'tenant_share'])->first();
            if(!$invoiceLine) {
                throw new \Exception('missing_mandatory_invoice_line', EQ_ERROR_INVALID_CONFIG);
            }

            $expenses_total += $accountingEntryLine['debit'];
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
                                'total_amount'  => $accountingEntryLine['debit'],
                                'owner'         => 0.0,
                                'tenant'        => 0.0,
                                'vat'           => 0.0
                            ];
                    }

                    $assigned_total += $amount;

                    $amount_vat = $invoiceLine['vat'] * $shares / $total_shares;
                    $amount_owner = round($amount * ($invoiceLine['owner_share'] / 100), 2);
                    $amount_tenant = round($amount - $amount_owner, 2);

                    $map_result[$ownership_id][$property_lot_id]['common_expense'][$accountingEntryLine['account_id']][$invoiceLine['apportionment_id']]['vat'] += $amount_vat;
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

// #todo - retrieve account according to account_id and ReserveFund

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

$response = [
    'expenses_total' => $expenses_total,
    'assigned_total' => $assigned_total,
    'assigned_delta' => $assigned_total - $expenses_total,
    'date_from'      => date('c', $fiscalPeriod['date_from']),
    'date_to'        => date('c', $fiscalPeriod['date_to']),
    'nb_days'        => $nb_days,
    'owners'         => []
];

foreach($map_result as $ownership_id => $list_property_lots) {
    $owner = [
            'id'                => $ownership_id,
            'name'              => $ownerships[$ownership_id]['name'],
            'nb_days'           => $ownerships[$ownership_id]['nb_days'],
            'date_from'         => $ownerships[$ownership_id]['date_from'] ? date('c', $ownerships[$ownership_id]['date_from']) : '',
            'date_to'           => $ownerships[$ownership_id]['date_to'] ? date('c', $ownerships[$ownership_id]['date_to']) : '',
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
                    'name'              => $expense_type,
                    'accounts'          => [],
                    'apportionments'    => []
                ];

            $map_apportionments = [];

            foreach($list_accounts as $account_id => $list_apportionments) {

                $account = [
                        'id'                => $account_id,
                        'name'              => $accounts[$account_id]['name'],
                        'code'              => $accounts[$account_id]['code'],
                        'apportionments'    => []
                    ];

                foreach($list_apportionments as $apportionment_id => $apportionment) {
                    if(!isset($map_apportionments[$apportionment_id])) {
                        $map_apportionments[$apportionment_id] = [
                            'id'                => $apportionment_id,
                            'name'              => $apportionments[$apportionment_id]['name'] ?? 'private',
                            'total_shares'      => $apportionment['total_shares'],
                            'total_amount'      => $apportionment['total_amount'],
                            'shares'            => $apportionment['shares'],
                            'accounts'          => []
                        ];
                    }
                    $map_apportionments[$apportionment_id]['accounts'][] = [
                            'id'                => $account_id,
                            'name'              => $accounts[$account_id]['name'],
                            'code'              => $accounts[$account_id]['code'],
                            'owner'             => $apportionment['owner'],
                            'tenant'            => $apportionment['tenant'],
                            'vat'               => $apportionment['vat'],
                            'total_amount'      => $apportionment['total_amount']
                        ];

                    if(isset($apportionments[$apportionment_id])) {
                        $apportionment['name'] = $apportionments[$apportionment_id]['name'] ?? 'unknown';
                    }

                    $account['apportionments'][] = $apportionment;
                }

                $expense['accounts'][] = $account;
            }
            // we generate both 'accounts' and 'apportionments' variation (same data but grouped by accounts or apportionment)
            // #memo - we can do this since there is a 1-1 dependency between account and apportionment (in hierarchical map)
            $expense['apportionments'] = array_values($map_apportionments);

            $property_lot['expenses'][] = $expense;
        }

        $owner['property_lots'][] = $property_lot;
    }

    $response['owners'][] = $owner;
}


$context->httpResponse()
    ->body($response)
    ->send();


/*

Output result sample :


```
{
    "expenses_total": 1694,
    "assigned_total": 1694,
    "assigned_delta": 0,
    "owners": [
        {
            "id": 2,
            "name": "00001 - Charles MAX",
            "property_lots": [
                {
                    "id": 2,
                    "name": "00003 - 1C (APPARTEMENT) - 00001 - Charles MAX",
                    "code": "00003",
                    "ref": "1C",
                    "nature": "APPARTEMENT",
                    "expenses": [
                        {
                            "name": "private_expense",
                            "accounts": [
                                {
                                    "id": 689,
                                    "name": "6430000 - Frais privatifs",
                                    "code": "6430000",
                                    "apportionments": [
                                        [
                                            {
                                                "owner": 2420,
                                                "tenant": 0,
                                                "vat": 420,
                                                "description": "appareils",
                                                "date": "1991-04-16T00:00:00+00:00"
                                            },
                                            {
                                                "owner": 484,
                                                "tenant": 0,
                                                "vat": 84,
                                                "description": "rfais en plus",
                                                "date": "1991-04-16T00:00:00+00:00"
                                            }
                                        ]
                                    ]
                                }
                            ],
                            "apportionments": [
                                {
                                    "id": 0,
                                    "name": "private",
                                    "total_shares": null,
                                    "shares": null,
                                    "accounts": [
                                        {
                                            "id": 689,
                                            "name": "6430000 - Frais privatifs",
                                            "code": "6430000",
                                            "owner": null,
                                            "tenant": null,
                                            "vat": null
                                        }
                                    ]
                                }
                            ]
                        },
                        {
                            "name": "common_expense",
                            "accounts": [
                                {
                                    "id": 481,
                                    "name": "6100003 - Réparation protection incendie",
                                    "code": "6100003",
                                    "apportionments": [
                                        {
                                            "shares": 275,
                                            "total_shares": 1000,
                                            "owner": 332.75,
                                            "tenant": 0,
                                            "vat": 57.75,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                },
                                {
                                    "id": 578,
                                    "name": "6110009 - Autres travaux",
                                    "code": "6110009",
                                    "apportionments": [
                                        {
                                            "shares": 275,
                                            "total_shares": 1000,
                                            "owner": 133.1,
                                            "tenant": 0,
                                            "vat": 23.1,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                }
                            ],
                            "apportionments": [
                                {
                                    "id": 2,
                                    "name": "0001 - Charges communes (Q. 1000)",
                                    "total_shares": 1000,
                                    "shares": 275,
                                    "accounts": [
                                        {
                                            "id": 481,
                                            "name": "6100003 - Réparation protection incendie",
                                            "code": "6100003",
                                            "owner": 332.75,
                                            "tenant": 0,
                                            "vat": 57.75
                                        },
                                        {
                                            "id": 578,
                                            "name": "6110009 - Autres travaux",
                                            "code": "6110009",
                                            "owner": 133.1,
                                            "tenant": 0,
                                            "vat": 23.1
                                        }
                                    ]
                                }
                            ]
                        }
                    ]
                },
                {
                    "id": 2,
                    "name": "00004 - GREZ (GARAGE) - 00001 - Charles MAX",
                    "code": "00004",
                    "ref": "GREZ",
                    "nature": "GARAGE",
                    "expenses": [
                        {
                            "name": "common_expense",
                            "accounts": [
                                {
                                    "id": 481,
                                    "name": "6100003 - Réparation protection incendie",
                                    "code": "6100003",
                                    "apportionments": [
                                        {
                                            "shares": 75,
                                            "total_shares": 1000,
                                            "owner": 90.75,
                                            "tenant": 0,
                                            "vat": 15.75,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                },
                                {
                                    "id": 578,
                                    "name": "6110009 - Autres travaux",
                                    "code": "6110009",
                                    "apportionments": [
                                        {
                                            "shares": 75,
                                            "total_shares": 1000,
                                            "owner": 36.3,
                                            "tenant": 0,
                                            "vat": 6.3,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                }
                            ],
                            "apportionments": [
                                {
                                    "id": 2,
                                    "name": "0001 - Charges communes (Q. 1000)",
                                    "total_shares": 1000,
                                    "shares": 75,
                                    "accounts": [
                                        {
                                            "id": 481,
                                            "name": "6100003 - Réparation protection incendie",
                                            "code": "6100003",
                                            "owner": 90.75,
                                            "tenant": 0,
                                            "vat": 15.75
                                        },
                                        {
                                            "id": 578,
                                            "name": "6110009 - Autres travaux",
                                            "code": "6110009",
                                            "owner": 36.3,
                                            "tenant": 0,
                                            "vat": 6.3
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
            "name": "00002 - Lucienne PRÉVAUT",
            "property_lots": [
                {
                    "id": 3,
                    "name": "00001 - 1A (APPARTEMENT) - 00002 - Lucienne PRÉVAUT",
                    "code": "00001",
                    "ref": "1A",
                    "nature": "APPARTEMENT",
                    "expenses": [
                        {
                            "name": "common_expense",
                            "accounts": [
                                {
                                    "id": 481,
                                    "name": "6100003 - Réparation protection incendie",
                                    "code": "6100003",
                                    "apportionments": [
                                        {
                                            "shares": 225,
                                            "total_shares": 1000,
                                            "owner": 272.25,
                                            "tenant": 0,
                                            "vat": 47.25,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                },
                                {
                                    "id": 578,
                                    "name": "6110009 - Autres travaux",
                                    "code": "6110009",
                                    "apportionments": [
                                        {
                                            "shares": 225,
                                            "total_shares": 1000,
                                            "owner": 108.9,
                                            "tenant": 0,
                                            "vat": 18.9,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                }
                            ],
                            "apportionments": [
                                {
                                    "id": 2,
                                    "name": "0001 - Charges communes (Q. 1000)",
                                    "total_shares": 1000,
                                    "shares": 225,
                                    "accounts": [
                                        {
                                            "id": 481,
                                            "name": "6100003 - Réparation protection incendie",
                                            "code": "6100003",
                                            "owner": 272.25,
                                            "tenant": 0,
                                            "vat": 47.25
                                        },
                                        {
                                            "id": 578,
                                            "name": "6110009 - Autres travaux",
                                            "code": "6110009",
                                            "owner": 108.9,
                                            "tenant": 0,
                                            "vat": 18.9
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
            "name": "00003 - Etienne DUCHEMIN, Sarah DUCHEMIN, Louis DUCHEMIN",
            "property_lots": [
                {
                    "id": 4,
                    "name": "00002 - 1B (APPARTEMENT) - 00003 - Etienne DUCHEMIN, Sarah DUCHEMIN, Louis DUCHEMIN",
                    "code": "00002",
                    "ref": "1B",
                    "nature": "APPARTEMENT",
                    "expenses": [
                        {
                            "name": "common_expense",
                            "accounts": [
                                {
                                    "id": 481,
                                    "name": "6100003 - Réparation protection incendie",
                                    "code": "6100003",
                                    "apportionments": [
                                        {
                                            "shares": 250,
                                            "total_shares": 1000,
                                            "owner": 302.5,
                                            "tenant": 0,
                                            "vat": 52.5,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                },
                                {
                                    "id": 578,
                                    "name": "6110009 - Autres travaux",
                                    "code": "6110009",
                                    "apportionments": [
                                        {
                                            "shares": 250,
                                            "total_shares": 1000,
                                            "owner": 121,
                                            "tenant": 0,
                                            "vat": 21,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                }
                            ],
                            "apportionments": [
                                {
                                    "id": 2,
                                    "name": "0001 - Charges communes (Q. 1000)",
                                    "total_shares": 1000,
                                    "shares": 250,
                                    "accounts": [
                                        {
                                            "id": 481,
                                            "name": "6100003 - Réparation protection incendie",
                                            "code": "6100003",
                                            "owner": 302.5,
                                            "tenant": 0,
                                            "vat": 52.5
                                        },
                                        {
                                            "id": 578,
                                            "name": "6110009 - Autres travaux",
                                            "code": "6110009",
                                            "owner": 121,
                                            "tenant": 0,
                                            "vat": 21
                                        }
                                    ]
                                }
                            ]
                        }
                    ]
                },
                {
                    "id": 4,
                    "name": "00005 - 1B-C (CAVE) - 00003 - Etienne DUCHEMIN, Sarah DUCHEMIN, Louis DUCHEMIN",
                    "code": "00005",
                    "ref": "1B-C",
                    "nature": "CAVE",
                    "expenses": [
                        {
                            "name": "common_expense",
                            "accounts": [
                                {
                                    "id": 481,
                                    "name": "6100003 - Réparation protection incendie",
                                    "code": "6100003",
                                    "apportionments": [
                                        {
                                            "shares": 175,
                                            "total_shares": 1000,
                                            "owner": 211.75,
                                            "tenant": 0,
                                            "vat": 36.75,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                },
                                {
                                    "id": 578,
                                    "name": "6110009 - Autres travaux",
                                    "code": "6110009",
                                    "apportionments": [
                                        {
                                            "shares": 175,
                                            "total_shares": 1000,
                                            "owner": 84.7,
                                            "tenant": 0,
                                            "vat": 14.7,
                                            "name": "0001 - Charges communes (Q. 1000)"
                                        }
                                    ]
                                }
                            ],
                            "apportionments": [
                                {
                                    "id": 2,
                                    "name": "0001 - Charges communes (Q. 1000)",
                                    "total_shares": 1000,
                                    "shares": 175,
                                    "accounts": [
                                        {
                                            "id": 481,
                                            "name": "6100003 - Réparation protection incendie",
                                            "code": "6100003",
                                            "owner": 211.75,
                                            "tenant": 0,
                                            "vat": 36.75
                                        },
                                        {
                                            "id": 578,
                                            "name": "6110009 - Autres travaux",
                                            "code": "6110009",
                                            "owner": 84.7,
                                            "tenant": 0,
                                            "vat": 14.7
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
}
```

*/