<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace purchase\accounting\invoice;

use finance\accounting\Account;
use finance\accounting\AccountingEntry;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use fmt\setting\Setting;
use sale\pay\Funding;

class PurchaseInvoice extends \finance\accounting\invoice\Invoice {

    public static function getName() {
        return 'Purchase invoice';
    }

    public function getTable() {
        return 'purchase_accounting_invoice_invoice';
    }

    public static function getDescription() {
        return 'A purchase invoice is a legal document issued after some goods have been bought from a supplier.';
    }

    public static function getColumns() {
        return [

            /**
             * Override Finance Invoice columns
             */

            'invoice_type' => [
                'type'              => 'string',
                'description'       => 'Document type: invoice or a credit note.',
                'selection'         => [
                    'invoice',
                    'credit_note'
                ],
                'default'           => 'invoice'
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\accounting\invoice\PurchaseInvoiceLine',
                'foreign_field'     => 'purchase_invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete',
                'dependencies'      => ['total', 'price']
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Funding',
                'foreign_field'     => 'purchase_invoice_id',
                'domain'            => ['funding_type', '=', 'purchase_invoice'],
                'description'       => 'Fundings created from the invoice.'
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short description of the invoice.',
                'help'              => 'For manual encoding, this can be set manually and must be synced with lines descriptions.',
                'multilang'         => true,
                'onupdate'          => 'onupdateDescription'
            ],

            /**
             * Specific Purchase Invoice columns
             */

            'supplier_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'description'       => 'The supplier the invoice relates to.',
                'required'          => true
            ],

            'payment_reference' => [
                'type'              => 'string',
                'description'       => 'Code provided by the supplier to use as reference in the wire transfer.'
            ],

            'has_mandate' => [
                'type'              => 'boolean',
                'description'       => 'Mark invoice as to be paid through a mandate.',
                'help'              => 'The Condominium has an active SEPA mandate for paying invoices from this supplier and payment will be made through it.',
                'default'           => false
            ],

            'supplier_invoice_number' => [
                'type'              => 'string',
                'required'          => true,
                'description'       => 'Invoice number assigned from the supplier side.'
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'purchase_invoice_id',
                'description'       => 'Accounting entries relating to the invoice.',
                'help'              => "Purchase invoices might be subject to several accounting entries."
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the invoice relates to.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'help'              => "Fiscal Year is automatically assigned based on posting_date.",
                'function'          => 'calcFiscalYearId',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the invoice relates to.",
                'help'              => "Period is automatically assigned based on posting_date.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['fiscal_year_id', '=', 'object.fiscal_year_id']],
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'emission_date' => [
                'type'              => 'date',
                'description'       => 'The date on which the invoice is recorded in the accounting system.',
                'onupdate'          => 'onupdateEmissionDate'
            ],

            'posting_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the invoice was emitted (by the system of origin).',
                'help'              => 'For sale invoices, this value is the same as posting_date.',
                'dependents'        => ['fiscal_year_id', 'fiscal_period_id']
            ],

        ];
    }

    protected static function onupdateDescription($self, $lang) {
        $self->read(['description', 'invoice_lines_ids' => ['description']]);
        foreach($self as $id => $purchaseInvoice) {
            if(!$purchaseInvoice['description'] || strlen($purchaseInvoice['description']) <= 0) {
                continue;
            }
            foreach($purchaseInvoice['invoice_lines_ids'] as $invoice_line_id => $invoiceLine) {
                if(!$invoiceLine['description'] || strlen($invoiceLine['description']) <= 0) {
                    PurchaseInvoiceLine::id($invoice_line_id)->update(['description' => $purchaseInvoice['description']], $lang);
                }
            }
            AccountingEntry::search([['purchase_invoice_id', '=', $id]])
                ->update(['description' => $purchaseInvoice['description']]);
        }
    }

    protected static function onupdateEmissionDate($self) {
        $self->read(['emission_date']);
        foreach($self as $id => $invoice) {
            self::id($id)->update(['posting_date' => $invoice['emission_date']]);
        }
    }

    protected static function calcFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'posting_date']);
        foreach($self as $id => $invoice) {
            $fiscalYear = FiscalYear::search([ ['condo_id', '=', $invoice['condo_id']], ['date_from', '<=', $invoice['posting_date']], ['date_to', '>=', $invoice['posting_date']] ])->first();
            if($fiscalYear) {
                $result[$id] = $fiscalYear['id'];
            }
        }
        return $result;
    }

    protected static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['posting_date', 'fiscal_year_id' => ['fiscal_periods_ids' => ['date_from', 'date_to']]]);
        foreach($self as $id => $invoice) {
            foreach($invoice['fiscal_year_id']['fiscal_periods_ids'] ?? [] as $period_id => $period) {
                if($invoice['posting_date'] >= $period['date_from'] && $invoice['posting_date'] <= $period['date_to']) {
                    $result[$id] = $period_id;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Assign current Fiscal Year of the Condominium, if any.
     */
    public static function oncreate($self, $orm) {
        $self->read(['condo_id', 'posting_date']);
        foreach($self as $id => $invoice) {
            if($invoice['posting_date'] && $invoice['condo_id']) {
                // #memo - posting date is set on time() by default
                $fiscalYear = FiscalYear::search([['condo_id', '=', $invoice['condo_id']], ['date_from', '<=', $invoice['posting_date']], ['date_to', '>=', $invoice['posting_date']]])->first();
                if($fiscalYear) {
                    // #memo - prevent updating 'state'
                    $orm->update(self::getType(), $id, ['fiscal_year_id' => $fiscalYear['id']], null, true);
                }
            }
        }
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($values['condo_id'])) {
            if(isset($event['emission_date'])) {
                $result['posting_date'] = $event['emission_date'];
                $result['date_from'] = $event['emission_date'];
                $result['date_to'] = $event['emission_date'];
                // force updating fiscal_year accordingly
                $event['posting_date'] = $event['emission_date'];
            }
            if(isset($event['posting_date']) || isset($event['date_from'])) {
                if(isset($event['posting_date'])) {
                    $fiscalYear = FiscalYear::search([['condo_id', '=', $values['condo_id']], ['date_from', '<=', $event['posting_date']], ['date_to', '>=', $event['posting_date']]])
                        ->read(['id', 'name', 'fiscal_periods_ids' => ['name', 'date_from', 'date_to']])
                        ->first();
                }
                else {
                    $fiscalYear = FiscalYear::search([['condo_id', '=', $values['condo_id']], ['date_from', '<=', $event['date_from']], ['date_to', '>=', $event['date_from']]])
                        ->read(['id', 'name', 'fiscal_periods_ids' => ['name', 'date_from', 'date_to']])
                        ->first();
                }
                if($fiscalYear) {
                    $result['fiscal_year_id'] = [
                        'id'    => $fiscalYear['id'],
                        'name'  => $fiscalYear['name']
                    ];
                    if(isset($event['posting_date'])) {
                        foreach($fiscalYear['fiscal_periods_ids'] ?? [] as $period_id => $period) {
                            if($event['posting_date'] >= $period['date_from'] && $event['posting_date'] <= $period['date_to']) {
                                $result['fiscal_period_id'] = [
                                    'id'    => $period_id,
                                    'name'  => $period['name']
                                ];
                                break;
                            }
                        }
                    }

                }
            }
        }
        return $result;
    }


    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Draft invoice, pending and still waiting to be completed.',
                'icon' => 'edit',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the invoice status based on the `invoice` field.',
                        'policies'    => [
                            'is_proforma', 'can_be_invoiced',
                        ],
                        'onbefore'  => 'onbeforeInvoice',
                        'status'    => 'posted',
                    ]
                ],
            ],
            'posted' => [
                'description' => 'Invoice can no longer be modified and can be sent to the customer.',
                'icon' => 'receipt_long',
                'transitions' => [
                    'cancel' => [
                        'description' => 'Set the invoice and receivables statuses as cancelled.',
                        'onafter' => 'onafterCancel',
                        'status' => 'cancelled',
                    ],
                    'cancel-keep-receivables' => [
                        'description' => 'Set the invoice status as cancelled and set receivables statuses back to pending.',
                        'onafter' => 'onafterCancelKeepReceivables',
                        'status' => 'cancelled',
                    ],
                ],
            ],
            'cancelled' => [
                'description' => 'The invoice was cancelled.',
                'icon' => 'cancel',
                'transitions' => [
                ],
            ],
        ];
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_be_invoiced' => [
                'description' => 'Verifies that the proforma can be invoiced.',
                'function'    => 'policyCanBeInvoiced'
            ],
            'is_proforma' => [
                'description' => 'Verifies that the invoice is still a proforma.',
                'function'    => 'policyIsProforma'
            ],
            'is_posted' => [
                'description' => 'Verifies that the invoice is posted.',
                'function'    => 'policyIsPosted'
            ]
        ]);
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'create_fundings' => [
                'description'   => 'Create the funding according to the invoice.',
                'policies'      => [],
                'function'      => 'doCreateFundings'
            ],
            'assign_invoice_number' => [
                'description'   => 'Creates accounting entries according to invoice lines.',
                'policies'      => [],
                'function'      => 'doAssignInvoiceNumber'
            ],
            'generate_accounting_entries' => [
                'description'   => 'Creates accounting entries according to invoice lines.',
                'policies'      => [],
                'function'      => 'doGenerateAccountingEntries'
            ]
        ]);
    }

    protected static function policyIsPosted($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $invoice) {
            if($invoice['status' !== 'posted']) {
                $result[$id] = [
                    'invalid_invoice_status' => 'Invoice must be posted.'
                ];
            }
        }
        return $result;
    }

    protected static function policyIsProforma($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $invoice) {
            if($invoice['status' !== 'proforma']) {
                $result[$id] = [
                    'invalid_invoice_status' => 'Invoice must be a proforma.'
                ];
            }
        }
        return $result;
    }

    protected static function policyCanBeInvoiced($self, $dispatch): array {
        $result = [];
        $self->read(['due_date', 'invoice_lines_ids' => ['vat_rate']]);
        foreach($self as $id => $invoice) {
            if(!$invoice['due_date']) {
                $result[$id] = [
                    'missing_due_date' => 'Due date is mandatory for emitting the invoice.'
                ];
                continue;
            }

            foreach($invoice['invoice_lines_ids'] as $line_id => $line) {
                if($line['vat_rate'] >= 1) {
                    $result[$id] = [
                        'invalid_vat_rate' => 'Vat rate must be a fraction of the price.'
                    ];
                    continue;
                }
            }
        }
        return $result;
    }

    protected static function onbeforeInvoice($self) {
        $self
            ->do('generate_accounting_entries')
            ->do('assign_invoice_number')
            ->do('validate_accounting_entries');
    }

    protected static function doAssignInvoiceNumber($self) {
        $self->read(['organisation_id']);
        foreach($self as $id => $invoice) {
            $format = Setting::get_value(
                    'purchase',
                    'accounting',
                    'invoice.sequence_format',
                    '%2d{year}-%05d{sequence}',
                    [
                        'organisation_id'   => $invoice['organisation_id'],
                        'condo_id'          => null
                    ]
                );
            $year = Setting::get_value('finance', 'accounting', 'fiscal_year', date('Y'), ['organisation_id' => $invoice['organisation_id'], 'condo_id' => null]);

            $sequence = Setting::fetch_and_add(
                    'purchase',
                    'accounting',
                    'invoice.sequence',
                    1,
                    [
                        'organisation_id'   => $invoice['organisation_id'],
                        'condo_id'          => null
                    ]
                );

            if($sequence) {
                $invoice_number = Setting::parse_format($format, [
                        'year'      => $year,
                        'org'       => $invoice['organisation_id'],
                        'sequence'  => $sequence
                    ]);
                self::id($id)->update(['invoice_number' => $invoice_number]);
            }
        }
    }

    /**
     * Create the fundings for paying the purchase the invoice.
     */
    protected static function doCreateFundings($self) {
        $self->read(['id', 'name', 'price', 'payment_reference', 'due_date', 'funding_id']);

        foreach($self as $id => $invoice) {
            $funding = Funding::create([
                    'description'         => $invoice['name'],
                    'funding_type'        => 'purchase_invoice',
                    'purchase_invoice_id' => $invoice['id'],
                    'due_amount'          => $invoice['price'],
                    'is_paid'             => false,
                    'payment_reference'   => $invoice['payment_reference'],
                    'due_date'            => $invoice['due_date']
                ])
                ->first();

            self::id($invoice['id'])
                ->update(['funding_id' => $funding['id']]);
        }
    }

    /**
     * Create the accounting entries according tp invoices lines.
     */
    protected static function doGenerateAccountingEntries($self) {
        $self->read(['id', 'organisation_id', 'accounting_entries_ids']);
        foreach($self as $id => $invoice) {
            try {
                // remove previously created entries, if any (there should be none)
                AccountingEntry::ids($invoice['accounting_entries_ids'])->delete(true);
                // generate accounting entries
                $accounting_entries = self::computeAccountingEntries($id);

                if(empty($accounting_entries)) {
                    throw new \Exception('invalid_invoice', EQ_ERROR_UNKNOWN);
                }

                $journal = Journal::search([['organisation_id', '=', $invoice['organisation_id']], ['journal_type', '=', 'SALE']])->read(['id'])->first();

                if(!$journal) {
                    throw new \Exception('missing_mandatory_journal', EQ_ERROR_INVALID_CONFIG);
                }

                // create new entries objects and assign to the sale journal
                foreach($accounting_entries as $entry) {
                    $entry['journal_id'] = $journal['id'];
                    AccountingEntry::create($entry);
                }
            }
            catch(\Exception $e) {
                trigger_error($e->getMessage(), EQ_REPORT_ERROR);
            }
        }
    }

    private static function computeAccountingEntries($invoice_id) {
        $result = [];

        // retrieve specific accounts numbers
        $account_sales = Setting::get_value('sale', 'accounting', 'account_sales', 'not_found');
        $account_sales_taxes = Setting::get_value('sale', 'accounting', 'account_sales-taxes', 'not_found');
        $account_trade_debtors = Setting::get_value('sale', 'accounting', 'account_trade-debtors', 'not_found');
        // $account_downpayments = Setting::get_value('sale', 'accounting', 'account_downpayment', 'not_found');

        $accountSales = Account::search(['code', '=', $account_sales])->read(['id', 'description'])->first();
        $accountSalesTaxes = Account::search(['code', '=', $account_sales_taxes])->read(['id', 'description'])->first();
        $accountTradeDebtors = Account::search(['code', '=', $account_trade_debtors])->read(['id', 'description'])->first();
        // $accountDownpayments = Account::search(['code', '=', $account_downpayments])->first();

        try {
            if(!$accountSales) {
                throw new \Exception('APP::missing mandatory account sales', EQ_ERROR_INVALID_CONFIG);
            }

            if(!$accountSalesTaxes) {
                throw new \Exception('APP::missing mandatory account sales taxes', EQ_ERROR_INVALID_CONFIG);
            }

            if(!$accountTradeDebtors) {
                throw new \Exception('APP::missing mandatory account trade debtors', EQ_ERROR_INVALID_CONFIG);
            }

            $invoice = self::id($invoice_id)->read(['id', 'price', 'invoice_type', 'invoice_lines_ids'])->first();

            if(!$invoice) {
                throw new \Exception('ORM::unknown invoice ['.$invoice_id.']', EQ_ERROR_INVALID_PARAM);
            }

            $map_accounting_entries = [];

            // fetch invoice lines
            $lines = PurchaseInvoiceLine::ids($invoice['invoice_lines_ids'])
                ->read([
                    'total', 'price',
                    'price_id' => [
                        'accounting_rule_id' => [
                            'vat_rule_id' => ['account_id'],
                            'accounting_rule_line_ids' => ['share', 'account_id']
                        ]
                    ]
                ]);

            // #todo - purchase invoice should not imply an internal catalog with Suppliers prices
            foreach($lines as $lid => $line) {

                if(!isset($line['price_id'])) {
                    throw new \Exception("APP::invoice line [{$lid}] without price for invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                }

                if(!isset($line['price_id']['accounting_rule_id'])) {
                    throw new \Exception("APP::invoice line [{$lid}] without accounting rule for invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                }

                if(!isset($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'])
                    || !count($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'])) {
                    throw new \Exception("APP::invoice line [{$lid}] without accounting rule lines for invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                }

                if(!isset($line['price_id']['accounting_rule_id']['vat_rule_id'])) {
                    throw new \Exception("APP::invoice line [{$lid}] without VAT rule for invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                }

                // #memo - Only one VAT rate can be applied per line: we should only retrieve the associated account.
                $vat_account_id = $line['price_id']['accounting_rule_id']['vat_rule_id']['account_id'];

                if(!isset($map_accounting_entries[$vat_account_id])) {
                    $map_accounting_entries[$vat_account_id] = 0.0;
                }

                $vat_amount = ($line['price'] < 0 ? -1.0 : 1.0) * (abs($line['price']) - abs($line['total']));
                $map_accounting_entries[$vat_account_id] += $vat_amount;

                $remaining_amount = $line['total'];

                $count_rules = count($line['price_id']['accounting_rule_id']['accounting_rule_line_ids']);
                $i = 1;

                foreach($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'] as $rule_line_id => $ruleLine) {
                    if(!isset($ruleLine['account_id'], $ruleLine['share']) || $ruleLine['account_id'] <= 0 || $ruleLine['share'] <= 0) {
                        throw new \Exception("APP::invalid accounting rule line [{$rule_line_id}] (missing account_id or share) for invoice line [{$lid}] of invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                    }

                    // last line
                    if($i == $count_rules) {
                        $amount = $remaining_amount;
                    }
                    else {
                        $amount = round($line['total'] * $ruleLine['share'], 2);
                        $remaining_amount -= $amount;
                    }

                    if(!isset($map_accounting_entries[$ruleLine['account_id']])) {
                        $map_accounting_entries[$ruleLine['account_id']] = 0.0;
                    }

                    $map_accounting_entries[$ruleLine['account_id']] += $amount;

                    ++$i;
                }
            }

            // create credit lines on sales & taxes accounts
            foreach($map_accounting_entries as $account_id => $amount) {
                $account = Account::id($account_id)->read(['description'])->first();
                $result[] = [
                        'name'                  => $account['description'],
                        'has_invoice'           => true,
                        'purchase_invoice_id'   => $invoice_id,
                        'account_id'            => $account_id,
                        'debit'                 => ($invoice['invoice_type'] == 'credit_note')?$amount:0.0,
                        'credit'                => ($invoice['invoice_type'] == 'invoice')?$amount:0.0
                    ];
            }

            // create a debit line on account "trade debtors"
            $result[] = [
                    'name'                      => $accountTradeDebtors['description'],
                    'has_invoice'               => true,
                    'purchase_invoice_id'       => $invoice_id,
                    'account_id'                => $accountTradeDebtors['id'],
                    'debit'                     => ($invoice['invoice_type'] == 'invoice')?$invoice['price']:0.0,
                    'credit'                    => ($invoice['invoice_type'] == 'credit_note')?$invoice['price']:0.0
                ];

        }
        catch(\Exception $e) {
            // log error
            trigger_error($e->getMessage(), EQ_REPORT_ERROR);
            // force returning an empty array
            $result = [];
        }

        return $result;
    }
}
