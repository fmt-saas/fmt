<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\purchase\accounting\invoice;

use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\accounting\Account;
use finance\accounting\Journal;
use fmt\setting\Setting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat\Wizard\Accounting;
use realestate\ownership\Ownership;
use realestate\purchase\accounting\AccountingEntry;
use realestate\purchase\accounting\AccountingEntryLine;

class Invoice extends \purchase\accounting\invoice\Invoice {

    public static function getColumns() {
        return [
            'supplier_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'description'       => 'The supplier the invoice relates to.',
                'relation'          => ['suppliership_id' => 'supplier_id'],
                'store'             => true
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'description'       => 'The supplier the invoice relates to.',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true,
                'dependents'        => ['supplier_id']
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\InvoiceLine',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete',
                'dependencies'      => ['total', 'price'],
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'has_fund_usage' => [
                'type'              => 'boolean',
                'description'       => 'Use one or more reserve funds for paying this invoice.',
                'default'           => false
            ],

            'has_instant_reinvoice' => [
                'type'              => 'boolean',
                'description'       => 'Immediate reinvoicing of private charges.',
                'help'              => 'When enabled, private charges are automatically reinvoiced as soon as they are recorded, without waiting for the end of the period or manual grouping.',
                'default'           => false
            ],

            'fund_usage_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\purchase\accounting\FundUsageLine',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Lines of reserve funds usages for paying the invoice.',
                'ondetach'          => 'delete',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\purchase\accounting\AccountingEntry',
                'foreign_field'     => 'origin_object_id',
                'domain'            => ['origin_object_class', '=', 'realestate\purchase\accounting\invoice\Invoice'],
                'description'       => 'Accounting entries relating to the invoice.',
                'help'              => "Purchase invoices might be subject to several accounting entries."
            ],

            'emission_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the invoice was emitted.',
                'required'          => true,
                'onupdate'          => 'onupdateEmissionDate'
            ],

            'posting_date' => [
                'type'              => 'date',
                'description'       => 'The date on which the invoice is recorded in the accounting system.',
                'default'           => function () { return time(); },
                'visible'           => ['has_date_range', '=', false],
                'dependents'        => ['fiscal_period_id']
            ],

            'due_date' => [
                'type'              => 'date',
                'description'       => 'Deadline for the payment is expected.',
                'required'          => true
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Draft invoice, pending and still waiting to be completed.',
                'icon' => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the invoice status based on the `invoice` field. Assign invoice number, generate accounting entries and validate accounting entries.',
                        'policies'    => [
                            'can_be_invoiced',
                            'can_be_allocated'
                        ],
                        'onbefore'  => 'onbeforeInvoice',
                        'status'    => 'invoice',
                    ]
                ],
            ],
            'invoice' => [
                'description' => 'Invoice can no longer be modified and can be sent to the customer.',
                'icon' => 'receipt_long',
                'transitions' => [
                    'cancel' => [
                        'description' => 'Set the invoice and receivables statuses as cancelled.',
                        'onafter' => 'onafterCancel',
                        'status' => 'cancelled',
                    ]
                ],
            ],
            'cancelled' => [
                'description' => 'The invoice is cancelled. There are no transitions available.',
                'icon' => 'cancel',
                'transitions' => []
            ],
        ];
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_be_allocated' => [
                'description' => 'Verifies that an invoice can be allocated of the posting date(s).',
                'function'    => 'policyCanBeAllocated'
            ],
        ]);
    }

    public static function policyCanBeInvoiced($self): array {
        $result = [];
        $self->read([
                'price',
                'invoice_lines_ids' => [
                    'total', 'price', 'vat_rate', 'owner_share', 'tenant_share'
                ],
                'fund_usage_lines_ids' => [
                    'fund_account_id', 'amount', 'apportionment_id', 'expense_account_id'
                ]
            ]);
        foreach($self as $id => $invoice) {
            $lines_total = 0.0;
            foreach($invoice['invoice_lines_ids'] as $line_id => $invoiceLine) {
                if(($invoiceLine['owner_share'] + $invoiceLine['tenant_share']) != 100) {
                    // error : invalid (non-balanced) owner/tenant ratio
                    $result[$id] = [
                        'invalid_owner_tenant_ratio' => 'Invalid (non-balanced) owner/tenant ratio.'
                    ];
                    continue 2;
                }
                if(round($invoiceLine['total'] * (1 + $invoiceLine['vat_rate']), 2) != $invoiceLine['price']) {
                    // error : non matching price from vat excl amount & applicable vat rate
                    $result[$id] = [
                        'non_matching_price' => 'Non matching price from vat excl amount & applicable vat rate.'
                    ];
                    continue 2;
                }
                $lines_total += $invoiceLine['price'];
            }
            if($invoice['price'] != $lines_total) {
                // error : non matching invoice price with sum of invoice lines prices
                $result[$id] = [
                    'non_matching_lines_total' => 'Invoice total and lines total do not match.'
                ];
                continue;
            }
            $usage_total = 0.0;
            $map_fund_accounts = [];
            foreach($invoice['fund_usage_lines_ids'] as $usage_line_id => $fundUsageLine) {
                if(isset($map_fund_accounts[$fundUsageLine['fund_account_id']])) {
                    // error: same account twice
                    $result[$id] = [
                        'duplicated_account' => 'A same expense account cannot be used twice.'
                    ];
                    continue 2;
                }
                $map_fund_accounts[$fundUsageLine['fund_account_id']] = true;
                $usage_total += $fundUsageLine['amount'];
                if(!$fundUsageLine['apportionment_id']) {
                    //error: non apportionment
                    $result[$id] = [
                        'missing_apportionment' => 'Apportionment is mandatory.'
                    ];
                    continue 2;
                }
                if(!$fundUsageLine['expense_account_id']) {
                    //error: no expense account
                    $result[$id] = [
                        'missing_expense_account' => 'Expense account is mandatory.'
                    ];
                    continue 2;
                }
            }
            if($usage_total > $invoice['price']) {
                // error: overflowing reserve funds allocation
                $result[$id] = [
                    'exceeding_fund_allocation' => 'Fund usage cannot exceed invoice total.'
                ];
                continue;
            }

        }
/*
#todo - FundUsageLines
* il faut que le montant du compte de réserve choisi soit suffisant pa rapport au montant assigné
pour le trouver il faut prendre la dernière balance périodique, et ajouter tous les mouvements jusqu'à la date de facture

*/
        return $result;
    }


    /**
     * Checks that if the invoice must be split, no part of it must be assigned to a non-open fiscal period (or fiscal year).
     */
    public static function policyCanBeAllocated($self) {
        $result = [];
        $self->read(['posting_date', 'has_date_range', 'date_from', 'date_to', 'condo_id']);
        foreach($self as $id => $invoice) {
            $date_from = $date_to = $invoice['posting_date'];
            if($invoice['has_date_range']) {
                $date_from = $invoice['date_from'];
                $date_to = $invoice['date_to'];
            }
            $allocation_dates = self::computeAllocationDates($date_from, $date_to, $invoice['condo_id']);
            if(empty($allocation_dates)) {
                $result[$id] = [
                    'invalid_posting_dates' => 'Unable to generate allocation dates.'
                ];
                continue;
            }
            // for each date, which should correspond to a fiscal period, check the status of the corresponding fiscal year
            foreach($allocation_dates as $date) {
                $fiscalPeriod = FiscalPeriod::search([['date_from', '=', $date], ['condo_id', '=', $invoice['condo_id']]])
                    ->read(['fiscal_year_id' => ['id', 'status']])
                    ->first();
                if(!$fiscalPeriod) {
                    $result[$id] = [
                        'invalid_allocation_fiscal_period' => 'Fiscal period targeted by the posting date(s) is missing.'
                    ];
                    trigger_error("APP::Attempting to assign (partly or full) a purchase invoice with no matching fiscal period for date " . date('Y-m-d', $date) . ".", EQ_REPORT_WARNING);
                    break;
                }
                if(!$fiscalPeriod['fiscal_year_id'] || !in_array($fiscalPeriod['fiscal_year_id']['status'], ['preopen', 'open'])) {
                    $result[$id] = [
                        'invalid_allocation_fiscal_year' => 'At least one fiscal year targeted by the invoice allocated is in a non-open state.'
                    ];
                    trigger_error("APP::Attempting to assign (partly or full) a purchase invoice on non-open fiscal year {$fiscalPeriod['fiscal_year_id']['id']} for date " . date('Y-m-d', $date) . ".", EQ_REPORT_WARNING);
                    break;
                }
            }
        }
        return $result;
    }

    private static function computeAllocationDates($date_from, $date_to, $condo_id) {
        $result = [];
        // there should be none or exactly one matching fiscal year
        $fiscalYear = FiscalYear::search([['condo_id', '=', $condo_id], ['date_from', '<=', $date_from], ['date_to', '>=', $date_from]])
            ->read(['id', 'status'])
            ->first();

        if(!$fiscalYear) {
            trigger_error('APP::Missing required fiscal year for assigning (partly or full) a purchase invoice.', EQ_REPORT_WARNING);
        }

        $fiscalPeriod = FiscalPeriod::search([['fiscal_year_id', '=', $fiscalYear['id']], ['date_from', '<=', $date_from], ['date_to', '>=', $date_from]])
            ->read(['date_from'])
            ->first();

        if(!$fiscalPeriod) {
            trigger_error('APP::Missing required fiscal period for assigning a purchase invoice on fiscal year ' . $fiscalYear['name'] . '.', EQ_REPORT_WARNING);
        }
        else {
            $current_date = $fiscalPeriod['date_from'];
            while($current_date <= $date_to) {
                $result[] = $current_date;
                if($current_date == $date_to) {
                    break;
                }
                // find the next period (we take all the existing periods for a condo_id and select the one whose date_from is the earliest among those with a date_from greater than the current_date)
                $nextPeriod = FiscalPeriod::search([
                            ['condo_id', '=', $condo_id], ['date_from', '>', $current_date]
                        ],
                        ['sort' => ['date_from' => 'asc'], 'limit' => 1]
                    )
                    ->read(['date_from'])
                    ->first();

                if(!$nextPeriod) {
                    trigger_error('APP::Missing required period for assigning (partly or full) a purchase invoice.', EQ_REPORT_WARNING);
                    $result = [];
                    break;
                }
                $current_date = $nextPeriod['date_from'];
            }
        }
        return $result;
    }

    public static function onbeforeInvoice($self) {
        $self
            ->do('generate_accounting_entries')
            ->do('assign_invoice_number')
            ->do('validate_accounting_entries');
    }


    /**
     * Generates the initial accounting entry.
     *
     * The accounting entry created is meant to be instantly validated (with invoice validation action).
     *
     *
     */
    public static function doGenerateAccountingEntries($self) {
        $self->read([
                'id', 'condo_id', 'price', 'description',
                'posting_date', 'has_date_range', 'date_from', 'date_to',
                'has_instant_reinvoice',
                'has_fund_usage',
                'fiscal_year_id',
                'suppliership_id' => [
                    'code'
                ],
                'invoice_lines_ids' => [
                    'expense_account_id',
                    'price',
                    'is_private_expense',
                    'owner_share',
                    'tenant_share',
                    'ownership_id'
                ],
                'fund_usage_lines_ids' => [
                    'amount',
                    'fund_account_id',
                    'expense_account_id'
                ]
            ]);

        foreach($self as $id => $invoice) {
            $date_from = $date_to = $invoice['posting_date'];

            if($invoice['has_date_range']) {
                $date_from = $invoice['date_from'];
                $date_to = $invoice['date_to'];
            }

            // retrieve journal dedicated to purchases
            $journal = Journal::search([['condo_id', '=', $invoice['condo_id']], ['code', '=', 'PUR']])->first();
            if(!$journal) {
                trigger_error("APP::unable to find a match for journal PUR for condominium {$invoice['condo_id']}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_journal", EQ_ERROR_INVALID_CONFIG);
            }

            // retrieve accounts for private expenses
            $privateExpenseAccount = Account::search([['condo_id', '=', $invoice['condo_id']], ['operation_assignment', '=', 'private_expenses']])
                ->read(['id', 'name'])
                ->first();
            if(!$privateExpenseAccount) {
                trigger_error("APP::unable to find a match for private_exepense account for condominium {$invoice['condo_id']}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_journal", EQ_ERROR_INVALID_CONFIG);
            }

            // #memo - use of the `reinvoiced_private_expense_account` has been deprecated


            // retrieve the accounting account relating to the supplier
            $assignmentAccount = Account::search([
                    ['condo_id', '=', $invoice['condo_id']],
                    ['operation_assignment', '=', 'suppliers']
                ])
                ->read(['code'])
                ->first();

            if(!$assignmentAccount) {
                trigger_error("APP::unable to find a match for journal PUR for condominium {$invoice['condo_id']}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_supplier_assignment_account", EQ_ERROR_INVALID_CONFIG);
            }

            $supplier_account_code = $assignmentAccount['code'] . $invoice['suppliership_id']['code'];
            $supplierAccount = Account::search([['code', '=', $supplier_account_code], ['condo_id', '=', $invoice['condo_id']]])->first();
            if(!$supplierAccount) {
                trigger_error("APP::unable to find a match for supplier code {$supplier_account_code}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_supplier_account", EQ_ERROR_INVALID_CONFIG);
            }

            // create the accounting entry for the purchase invoice
            $accountingEntry = AccountingEntry::create([
                    'condo_id'              => $invoice['condo_id'],
                    'journal_id'            => $journal['id'],
                    'invoice_id'            => $id,
                    'fiscal_year_id'        => $invoice['fiscal_year_id'],
                    // #memo - if necessary, entry_date will be reassigned based on selected fiscal year and matching period (so that dates remain in ascending order)
                    'entry_date'            => $date_from,
                    'origin_object_class'   => self::getType(),
                    'origin_object_id'      => $id
                ])
                ->first();

            // map for keeping track of scheduled accounting entries based on periods dates (ued as key)
            $map_planned_accounting_entries = [];

            $description = $invoice['description'];

            if($date_from == $date_to) {
                $description .= ' (' . date('Y-m-d', $date_from) . ')';
            }
            else {
                $description .= ' (' . date('Y-m-d', $date_from) . ' - ' . date('Y-m-d', $date_to) . ')';
            }

            // 1) create the credit line on the supplier account
            AccountingEntryLine::create([
                    'condo_id'              => $invoice['condo_id'],
                    'accounting_entry_id'   => $accountingEntry['id'],
                    'name'                  => $description,
                    'account_id'            => $supplierAccount['id'],
                    'debit'                 => 0.0,
                    'credit'                => $invoice['price']
                ]);


            // 2) create entry lines for reserve funds use, if any
            // #memo - reserve fund use is always considered for a single date
            if($invoice['has_fund_usage']) {
                foreach($invoice['fund_usage_lines_ids'] as $usage_line_id => $fundUsageLine) {

                    // create the debit line on the expense account (use of reserve fund)
                    AccountingEntryLine::create([
                            'condo_id'              => $invoice['condo_id'],
                            'accounting_entry_id'   => $accountingEntry['id'],
                            'name'                  => $invoice['description'],
                            'account_id'            => $fundUsageLine['expense_account_id'],
                            'debit'                 => $fundUsageLine['amount'],
                            'credit'                => 0.0
                        ]);

                    // create the credit line on the reserve fund
                    AccountingEntryLine::create([
                            'condo_id'              => $invoice['condo_id'],
                            'accounting_entry_id'   => $accountingEntry['id'],
                            'name'                  => $invoice['description'],
                            'account_id'            => $fundUsageLine['fund_account_id'],
                            'debit'                 => 0.0,
                            'credit'                => $fundUsageLine['amount']
                        ]);
                }
            }

            // 3) create entry lines for private expenses, if any, and keep track of what should be taken into account by the working capital

            // #memo - in case of a private expense, only first date is used for accounting
            foreach($invoice['invoice_lines_ids'] as $invoice_line_id => $invoiceLine) {
                if($invoiceLine['is_private_expense']) {

                    $ownership = Ownership::id($invoiceLine['ownership_id'])->read(['ownership_account_id'])->first();

                    // create the debit line on the private expense account
                    AccountingEntryLine::create([
                            'condo_id'              => $invoice['condo_id'],
                            'accounting_entry_id'   => $accountingEntry['id'],
                            'name'                  => $invoice['description'],
                            'account_id'            => $privateExpenseAccount['id'],
                            'invoice_line_id'       => $invoice_line_id,
                            'debit'                 => $invoiceLine['price'],
                            'credit'                => 0.0
                        ]);

                    if($invoice['has_instant_reinvoice']) {
                        // create the debit line on the ownership account
                        AccountingEntryLine::create([
                                'condo_id'              => $invoice['condo_id'],
                                'accounting_entry_id'   => $accountingEntry['id'],
                                'name'                  => $invoice['description'],
                                'account_id'            => $ownership['ownership_account_id'],
                                'invoice_line_id'       => $invoice_line_id,
                                'debit'                 => 0.0,
                                'credit'                => $invoiceLine['price']
                            ]);

                        // create the credit line on the private expense
                        AccountingEntryLine::create([
                                'condo_id'              => $invoice['condo_id'],
                                'accounting_entry_id'   => $accountingEntry['id'],
                                'name'                  => $invoice['description'],
                                'account_id'            => $privateExpenseAccount['id'],
                                'invoice_line_id'       => $invoice_line_id,
                                'debit'                 => 0.0,
                                'credit'                => $invoiceLine['price']
                            ]);
                    }

                }
            }

            // 4) single date: create lines relating to the common expenses
            if($date_from === $date_to) {
                foreach($invoice['invoice_lines_ids'] as $invoice_line_id => $invoiceLine) {
                    if($invoiceLine['is_private_expense']) {
                        continue;
                    }
                    // create the debit line on the expense account (use of reserve fund)
                    AccountingEntryLine::create([
                            'condo_id'              => $invoice['condo_id'],
                            'accounting_entry_id'   => $accountingEntry['id'],
                            'name'                  => $invoice['description'],
                            'account_id'            => $invoiceLine['expense_account_id'],
                            'invoice_line_id'       => $invoice_line_id,
                            'debit'                 => $invoiceLine['price'],
                            'credit'                => 0.0
                        ]);
                }
            }
            // 5) date range: split common expenses
            else {
                $total_days = ( ($date_to - $date_from) / 86400 ) + 1;

                // retrieve dates for allocating amounts to accounting entries
                $allocation_dates = self::computeAllocationDates($date_from, $date_to, $invoice['condo_id']);

                // retrieve the account for deferred expenses
                $deferredExpensesAccount = Account::search([
                        ['condo_id', '=', $invoice['condo_id']],
                        ['operation_assignment', '=', 'deferred_expenses']
                    ])
                    ->first();

                if(!$deferredExpensesAccount) {
                    throw new \Exception("missing_mandatory_deferred_expenses_account", EQ_ERROR_INVALID_CONFIG);
                }

                foreach($invoice['invoice_lines_ids'] as $invoice_line_id => $invoiceLine) {
                    if($invoiceLine['is_private_expense']) {
                        continue;
                    }

                    $total_amount = $remaining_amount = $invoiceLine['price'];

                    for($i = 0, $n = count($allocation_dates); $i < $n; ++$i) {

                        $period_date_from = $allocation_dates[$i];
                        $period_date_to = ($i+1 < $n) ? $allocation_dates[$i+1] : $date_to;

                        // first date of the date range
                        if($i == 0) {
                            // create the debit line for the whole common expense
                            AccountingEntryLine::create([
                                    'condo_id'              => $invoice['condo_id'],
                                    'accounting_entry_id'   => $accountingEntry['id'],
                                    'name'                  => $description,
                                    'account_id'            => $invoiceLine['expense_account_id'],
                                    'invoice_line_id'       => $invoice_line_id,
                                    'debit'                 => $invoiceLine['price'],
                                    'credit'                => 0.0
                                ]);

                            // compute paid amount pro-rata based on the duration of the date range.
                            $intersect_from = max($date_from, $period_date_from);
                            $intersect_to = min($date_to, $period_date_to);
                            $intersect_days = ( ($intersect_to - $intersect_from) / 86400 ) + 1;
                            $ratio = round($intersect_days / $total_days, 4);
                            $amount = round($total_amount * $ratio, 2);
                            // #memo - no entry line with $amount here: resulting allocated amount for first period will be the delta with following deferred lines
                            $remaining_amount -= $amount;
                        }
                        // handle expense deferring
                        else {

                            // 1) create deferred entry lines
                            $description = $invoice['description'];
                            $description .= ' (' . date('Y-m-d', $period_date_from) . ' - ' . date('Y-m-d', $period_date_to) . ')';

                            if($i == $n-1) {
                                $amount = $remaining_amount;
                            }
                            else {
                                //  we allocate the paid amount pro-rata based on the duration of the date range.
                                $intersect_from = max($date_from, $period_date_from);
                                $intersect_to = min($date_to, $period_date_to);

                                $intersect_days = ( ($intersect_to - $intersect_from) / 86400 ) + 1;
                                $ratio = round($intersect_days / $total_days, 4);
                                $amount = round($total_amount * $ratio, 2);
                                $remaining_amount -= $amount;
                            }

                            // create the debit line for the deferred expense
                            AccountingEntryLine::create([
                                    'condo_id'              => $invoice['condo_id'],
                                    'accounting_entry_id'   => $accountingEntry['id'],
                                    'name'                  => $description,
                                    'account_id'            => $deferredExpensesAccount['id'],
                                    'invoice_line_id'       => $invoice_line_id,
                                    'debit'                 => $amount,
                                    'credit'                => 0.0
                                ]);

                            // create the credit line for the expense
                            AccountingEntryLine::create([
                                    'condo_id'              => $invoice['condo_id'],
                                    'accounting_entry_id'   => $accountingEntry['id'],
                                    'name'                  => $description,
                                    'account_id'            => $invoiceLine['expense_account_id'],
                                    'invoice_line_id'       => $invoice_line_id,
                                    'debit'                 => 0.0,
                                    'credit'                => $amount
                                ]);

                            // 2) schedule a symmetrical accounting entry for the related period ('planned')
                            $plannedFiscalYear = FiscalYear::search([['condo_id', '=', $invoice['condo_id']], ['date_from', '<=', $period_date_from], ['date_to', '>=', $period_date_from]])->first();

                            if(!$plannedFiscalYear) {
                                throw new \Exception("missing_mandatory_matching_fiscal_year", EQ_ERROR_INVALID_CONFIG);
                            }

                            // put all lines related to a period on a single accounting entry
                            if(!isset($map_planned_accounting_entries[$period_date_from])) {
                                $map_planned_accounting_entries[$period_date_from] = AccountingEntry::create([
                                        'condo_id'              => $invoice['condo_id'],
                                        'journal_id'            => $journal['id'],
                                        'invoice_id'            => $id,
                                        'fiscal_year_id'        => $plannedFiscalYear['id'],
                                        'entry_date'            => $period_date_from,
                                        'origin_object_class'   => self::getType(),
                                        'origin_object_id'      => $id
                                    ])
                                    ->first();
                            }

                            $plannedAccountingEntry = $map_planned_accounting_entries[$period_date_from];

                            // create the credit line for the deferred expense
                            AccountingEntryLine::create([
                                    'condo_id'              => $invoice['condo_id'],
                                    'accounting_entry_id'   => $plannedAccountingEntry['id'],
                                    'name'                  => $description,
                                    'account_id'            => $deferredExpensesAccount['id'],
                                    'invoice_line_id'       => $invoice_line_id,
                                    'debit'                 => 0.0,
                                    'credit'                => $amount
                                ]);

                            // create the debit line for the expense
                            AccountingEntryLine::create([
                                    'condo_id'              => $invoice['condo_id'],
                                    'accounting_entry_id'   => $plannedAccountingEntry['id'],
                                    'name'                  => $description,
                                    'account_id'            => $invoiceLine['expense_account_id'],
                                    'invoice_line_id'       => $invoice_line_id,
                                    'debit'                 => $amount,
                                    'credit'                => 0.0
                                ]);

                        }

                    }
                }
            }

            // mark all scheduled accounting entries as planned
            foreach($map_planned_accounting_entries as $period_date_from => $plannedAccountingEntry) {
                AccountingEntry::id($plannedAccountingEntry['id'])->transition('plan');
            }

        }
    }


    public static function doAssignInvoiceNumber($self) {
        $self->read(['condo_id', 'fiscal_year_id' => ['code'], 'fiscal_period_id' => ['code']]);
        foreach($self as $id => $invoice) {
            $format = Setting::get_value(
                    'purchase',
                    'accounting',
                    'invoice.sequence_format',
                    '%2d{year}-%05d{sequence}',
                    [
                        'condo_id'          => $invoice['condo_id']
                    ]
                );

            $sequence = Setting::fetch_and_add(
                    'purchase',
                    'accounting',
                    "invoice.sequence.{$invoice['fiscal_year_id']['code']}.{$invoice['fiscal_period_id']['code']}",
                    1,
                    [
                        'condo_id'          => $invoice['condo_id']
                    ]
                );

            if($sequence) {
                $invoice_number = Setting::parse_format($format, [
                        'year'      => $invoice['fiscal_year_id']['code'],
                        'period'    => $invoice['fiscal_period_id']['code'],
                        'condo'     => $invoice['condo_id'],
                        'sequence'  => $sequence
                    ]);
                self::id($id)->update(['invoice_number' => $invoice_number]);
            }
        }
    }

    public static function doValidateAccountingEntries($self) {
        $self->read(['accounting_entries_ids' => ['status']]);
        foreach($self as $id => $invoice) {
            foreach($invoice['accounting_entries_ids'] as $accounting_entry_id => $accountingEntry) {
                if($accountingEntry['status'] == 'pending') {
                    AccountingEntry::id($accounting_entry_id)->transition('validate');
                }
            }
        }
    }

    public static function onchange($event, $values) {
        $result = [];
        if(array_key_exists('invoice_lines_ids', $event)) {
            $result['price'] = static::computePrice($values['id']);
        }
        if(isset($event['emission_date'])) {
            $result['due_date'] = strtotime('+30 days', strtotime('last day of this month', $event['emission_date']));
        }
        return array_merge(parent::onchange($event, $values), $result);
    }
}
