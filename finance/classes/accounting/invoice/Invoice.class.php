<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting\invoice;

use core\setting\Setting;
use equal\orm\Model;

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
                'relation'          => ['invoice_number'],
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
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the invoice.',
                'selection'         => [
                    'proforma',             // draft invoice (no number yet)
                    'invoice',              // final invoice (with unique number and accounting entries)
                    'cancelled'             // the invoice has been cancelled (through reversing entries)
                ],
                'default'           => 'proforma',
                'help'              => "Status set to 'invoice' means the invoice has been emitted with a unique number and accounting entries. `cancelled` means that the invoice has been cancelled through a credit note (and related reversing entries)."
            ],

            'invoice_type' => [
                'type'              => 'string',
                'description'       => 'Document type: invoice or a credit note.',
                'selection'         => [
                    'invoice',
                    'credit_note',
                    'fund_request'
                ],
                'default'           => 'invoice'
            ],

            'invoice_purpose' => [
                'type'              => 'string',
                'description'       => 'Is the invoice concerning a sale to a customer or a purchase from a supplier.',
                'selection'         => [
                    'sell',
                    'buy'
                ],
                'default'          => 'buy'
            ],

            'invoice_number' => [
                'type'              => 'string',
                'description'       => 'Number of the invoice, according to organization logic.',
                'required'          => true,
                'dependents'        => ['name']
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
                    'pending',          // non-paid, payment terms delay running
                    'overdue',          // non-paid, and payment terms delay is over
                    'debit_balance',    // partially paid: buyer still has to pay something
                    'credit_balance',   // over paid: reimbursement to buyer is required
                    'balanced'          // fully paid and balanced
                ],
                'visible'           => ['status', '=', 'invoice'],
                'default'           => 'pending'
            ],

            'payment_reference' => [
                'type'              => 'string',
                'description'       => 'Message for identifying payments related to the invoice.'
            ],

            'emission_date' => [
                'type'              => 'datetime',
                'description'       => 'Date at which the invoice was emitted.'
            ],

            'due_date' => [
                'type'              => 'date',
                'description'       => 'Deadline for the payment is expected.',
                'default'           => strtotime('+1 month')
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

    public static function calcPrice($self): array {
        $result = [];
        $self->read(['invoice_lines_ids' => ['price']]);
        $currency_decimal_precision = Setting::get_value('core', 'locale', 'currency.decimal_precision', 2);
        foreach($self as $id => $invoice) {
            $price = array_reduce($invoice['invoice_lines_ids']->get(true), function ($c, $a) {
                return $c + $a['price'];
            }, 0.0);

            $result[$id] = round($price, $currency_decimal_precision);
        }

        return $result;
    }

}
