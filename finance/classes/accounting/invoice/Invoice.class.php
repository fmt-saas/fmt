<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting\invoice;

use core\setting\Setting;
use equal\orm\Model;
use finance\accounting\AccountingEntry;

class Invoice extends Model {

    public static function getName() {
        return 'Invoice';
    }

    public static function getDescription() {
        return 'An invoice is a legal document issued by a seller and given to a buyer, that relates to a sale and is part of the accounting system.';
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the invoice refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'function'          => 'calcName',
                'description'       => 'Label of the invoice, depending on its status'
            ],

            'reference' => [
                'type'              => 'string',
                'description'       => 'Note or comments to be addressed to the customer.',
                'help'              => 'This is an arbitrary text field (to be added at the top of invoices), such as customer reference or any comments to be addressed to the customer.'
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'The organisation that emitted/received the invoice.',
                'default'           => 1
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the fund request relates to.",
                'required'          => true,
                'dependents'        => ['fiscal_period_id']
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the invoice relates to (from posting_date).",
                'help'              => "Period is automatically assigned when invoice is validated.",
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the invoice.',
                'selection'         => [
                    'proforma',             // draft invoice (no number yet)
                    'posted',               // final invoice (with unique number and accounting entries)
                    'cancelled'             // the invoice has been cancelled (through reversing entries)
                ],
                'default'           => 'proforma',
                'dependents'        => ['name'],
                'help'              => "Status set to 'invoice' means the invoice has been emitted with a unique number and accounting entries. `cancelled` means that the invoice has been cancelled through a credit note (and related reversing entries)."
            ],

            'invoice_type' => [
                'type'              => 'string',
                'description'       => 'Document type: invoice or a credit note.',
                'selection'         => [
                    'invoice',
                    'credit_note',
                    'fund_request',
                    'expense_statement'
                ],
                'default'           => 'invoice'
            ],

            'invoice_number' => [
                'type'              => 'string',
                'description'       => 'Number of the invoice, according to organization logic.',
                'dependents'        => ['name']
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'description'       => 'The funding related to the invoice.'
            ],

            'reversed_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\invoice\Invoice',
                'description'       => 'Credit note that was created for cancelling the invoice, if any.',
                'visible'           => ['status', '=', 'cancelled']
            ],

            'payment_status' => [
                'type'              => 'string',
                'selection'         => [
                    'debit_balance',    // buyer still has to pay something
                    'credit_balance',   // reimbursement to buyer is required
                    'balanced'          // fully paid and balanced
                ],
                'visible'           => ['status', '=', 'posted'],
                'default'           => 'pending'
            ],

            'payment_reference' => [
                'type'              => 'string',
                'description'       => 'Message for identifying payments related to the invoice.',
                'help'              => 'Reference can hold various formatted information : VCS (+++123/4567/89101+++), ISO 11649 (RFnn...), free text (max. 140 chars).'
            ],

            'posting_date' => [
                'type'              => 'date',
                'description'       => 'The date on which the invoice is recorded in the accounting system.',
                'default'           => function () { return time(); },
                'dependents'        => ['fiscal_period_id']
            ],

            'emission_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the invoice was emitted (by the system of origin).',
                'help'              => 'For sale invoices, this value is the same as posting_date.',
            ],

            'due_date' => [
                'type'              => 'date',
                'description'       => 'Deadline for the payment is expected.'
            ],

            'has_date_range' => [
                'type'              => 'boolean',
                'description'       => 'Service delivered over a period of time.',
                'default'           => false
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => 'First date of the date range.',
                'visible'           => ['has_date_range', '=', true]
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'Last date of the date range.',
                'visible'           => ['has_date_range', '=', true]
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the invoice.',
                'function'          => 'calcTotal',
                'store'             => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Final tax-included invoiced amount.',
                'function'          => 'calcPrice',
                'store'             => true
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\invoice\InvoiceLine',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete',
                'dependents'        => ['total', 'price']
            ],

            'invoice_line_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\invoice\InvoiceLineGroup',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Groups of lines of the invoice.',
                'order'             => 'order',
                'ondetach'          => 'delete',
                'dependents'        => ['total', 'price']
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Accounting entry of the invoice."
            ],

            /*
            // #todo
            'accounting_entry_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'origin_object_id',
                'domain'            => [['origin_object_class', '=', 'finance\accounting\invoice\Invoice'], ['accounting_entry_id', '=', 'object.accounting_entry_id']],
                'description'       => 'Accounting entries relating to the lines of the invoice.',
                'ondetach'          => 'delete'
            ]
            */

        ];
    }


    public static function getActions() {
        return [
            'validate_accounting_entries' => [
                'description'   => 'Validate accounting entry (that should be pending) to be accounted in balance.',
                'policies'      => [],
                'function'      => 'doValidateAccountingEntries'
            ]
        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['status', 'invoice_number']);
        foreach($self as $id => $invoice) {
            if($invoice['status'] === 'proforma') {
                $result[$id] = '[proforma]';
            }
            elseif($invoice['invoice_number']) {
                $result[$id] = $invoice['invoice_number'];
            }
        }
        return $result;
    }

    /**
     * #memo - we need this value even if it can still change (i.e. accounting entry is not yet validated)
     */
    protected static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['status', 'posting_date', 'fiscal_year_id' => ['fiscal_periods_ids' => ['date_from', 'date_to']]]);
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

    public static function calcTotal($self): array {
        $result = [];
        $self->read(['invoice_lines_ids' => ['total']]);
        foreach($self as $id => $invoice) {
            $result[$id] = array_reduce($invoice['invoice_lines_ids']->get(true), function ($c, $a) {
                return $c + $a['total'];
            }, 0.0);
        }

        return $result;
    }

    protected static function computePrice($id): float {
        $result = 0.0;
        $invoice = static::id($id)->read(['invoice_lines_ids' => ['price']])->first();

        if($invoice) {
            $currency_decimal_precision = Setting::get_value('core', 'locale', 'currency.decimal_precision', 2);

            $price = array_reduce($invoice['invoice_lines_ids']->get(true), function ($c, $a) {
                return $c + $a['price'];
            }, 0.0);

            $result = round($price, $currency_decimal_precision);
        }

        return $result;
    }

    public static function calcPrice($self): array {
        $result = [];
        $self->read(['invoice_lines_ids' => ['price']]);
        foreach($self as $id => $invoice) {
            $result[$id] = static::computePrice($id);
        }
        return $result;
    }

    protected static function doValidateAccountingEntries($self) {
        $self->read(['accounting_entry_id' => ['status']]);
        foreach($self as $id => $invoice) {
            if($invoice['accounting_entry_id']['status'] == 'pending') {
                AccountingEntry::id($invoice['accounting_entry_id']['id'])->transition('validate');
            }
        }
    }

}
