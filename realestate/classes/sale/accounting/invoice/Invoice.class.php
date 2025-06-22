<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\sale\accounting\invoice;

use fmt\setting\Setting;
use finance\accounting\AccountingEntry;
use finance\accounting\Journal;
use sale\receivable\Receivable;

class Invoice extends \sale\accounting\invoice\Invoice {

    public static function getName() {
        return 'Sale invoice';
    }

    public function getTable() {
        return 'sale_accounting_invoice_invoice';
    }

    public static function getDescription() {
        return 'A sale invoice is a legal document issued after some goods have been sold to a customer.';
    }

    public static function getColumns() {

        return [
            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Funding',
                'foreign_field'     => 'invoice_id',
                'description'       => 'The fundings relating to the invoice.'
            ],
        ];
    }



    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Draft invoice, still waiting to be completed and for customer approval.',
                'icon' => 'edit',
                'transitions' => [
                    'invoice' => [
                        'description' => 'Update the invoice status to `invoice`.',
                        'policies'    => [
                            'can_be_invoiced',
                        ],
                        'onbefore'  => 'onbeforeInvoice',
                        'onafter'   => 'onafterInvoice',
                        'status'    => 'posted',
                    ],
                    'cancel-proforma' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'onafter' => 'onafterCancelProforma',
                        'status'  => 'proforma',
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

    public static function onbeforeInvoice($self) {
        $self
            ->do('generate_accounting_entries')
            ->do('assign_invoice_number')
            ->do('validate_accounting_entries');
    }

    /**
     * Generate the fundings for a collection of invoices that just transitioned to "invoiced".
     * Fundings must be created here because due_date is set at invoice emission.
    */
    public static function onafterInvoice($self) {
        try {
            // #memo - failing in emitting the fundings cannot interrupt the transition
            $self->do('create_funding');
        }
        catch(\Exception $e) {
            trigger_error("APP::error while creating invoices funding: {$e->getMessage()}", EQ_REPORT_ERROR);
        }
    }

    public static function onafterCancelProforma($self) {
        $self->read(['id']);
        foreach($self as $invoice) {
            $receivables_ids = Receivable::search([
                    ['status', '=', 'invoiced'],
                    ['invoice_id', '=', $invoice['id']],
                ])
                ->ids();

            Receivable::ids($receivables_ids)
                ->update([
                    'status'          => 'pending',
                    'invoice_id'      => null,
                    'invoice_line_id' => null
                ]);

            Invoice::id($invoice['id'])
                ->delete();
        }
    }

    public static function onafterCancel($self) {
        $self->do('reverse');
    }

    /**
     * Create the accounting entries according tp invoices lines.
     */
    public static function doGenerateAccountingEntries($self) {
        $self->read(['id', 'condo_id', 'accounting_entries_ids']);
        foreach($self as $id => $invoice) {
            try {
                // remove previously created entries, if any (there should be none)
                AccountingEntry::ids($invoice['accounting_entries_ids'])->delete(true);
                // generate accounting entries
                $accounting_entries = self::computeAccountingEntries($id);

                if(empty($accounting_entries)) {
                    throw new \Exception('invalid_invoice', EQ_ERROR_UNKNOWN);
                }

                $journal = Journal::search([['condo_id', '=', $invoice['condo_id']], ['journal_type', '=', 'SALE']])->read(['id'])->first();

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

    public static function doAssignInvoiceNumber($self) {
        $self->read(['condo_id', 'fiscal_year_id' => ['code'], 'fiscal_period_id' => ['code']]);
        foreach($self as $id => $invoice) {
            $format = Setting::get_value(
                    'sale',
                    'accounting',
                    'invoice.sequence_format',
                    '%2d{year}-%05d{sequence}',
                    [
                        'condo_id'          => $invoice['condo_id']
                    ]
                );

            $sequence = Setting::fetch_and_add(
                    'sale',
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

    private static function computeAccountingEntries($invoice_id) {
        $result = [];
        // #todo (defined on inherited classes)
        return $result;
    }


}
