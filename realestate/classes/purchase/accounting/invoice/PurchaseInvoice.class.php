<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\purchase\accounting\invoice;

use documents\Document;
use documents\DocumentType;
use documents\processing\DocumentProcess;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\accounting\Account;
use finance\accounting\Journal;
use finance\accounting\MiscOperation;
use finance\accounting\MiscOperationLine;
use finance\bank\CondominiumBankAccount;
use finance\bank\SuppliershipBankAccount;
use fmt\setting\Setting;
use identity\User;
use purchase\supplier\Suppliership;
use realestate\property\Condominium;
use realestate\finance\accounting\AccountingEntry;
use realestate\finance\accounting\AccountingEntryLine;
use realestate\sale\pay\Funding;

class PurchaseInvoice extends \purchase\accounting\invoice\PurchaseInvoice {

    public static function getLink() {
        return "/app/#/condo/:condo_id/accounting/purchase-invoice/object.id";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the invoice refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => false,
                'onupdate'          => 'onupdateCondoId'
            ],

            'document_data' => [
                'type'              => 'binary',
                'description'       => 'Raw binary data of the uploaded document',
                'help'              => 'This field is meant to be used for the subsequent document creation, and is emptied once the document creation is confirmed.',
                'onupdate'          => 'onupdateDocumentData'
            ],

            'document_name' => [
                'type'              => 'string',
                'description'       => "Name of the processed document."
            ],

            'supplier_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'description'       => 'The supplier the invoice relates to.',
                'relation'          => ['suppliership_id' => 'supplier_id'],
                'store'             => true
            ],

            'supplier_identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'The supplier the invoice relates to.',
                'relation'          => ['suppliership_id' => ['supplier_id' => 'identity_id']],
                'store'             => true,
                'instant'           => true
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'description'       => 'The supplier the invoice relates to.',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'onupdate'          => 'onupdateSuppliershipId',
                'required'          => true,
                'dependents'        => ['supplier_id', 'supplier_identity_id']
            ],

            'suppliership_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\SuppliershipBankAccount',
                'description'       => 'The bank account of the supplier to be used.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['suppliership_id', '=', 'object.suppliership_id']],
                'onupdate'          => 'onupdateSuppliershipBankAccountId',
            ],

            'condo_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'The bank account of the condominium to be used.',
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null],
                    ['bank_account_type', '=', 'bank_current'],
                    ['object_class', '=', 'finance\bank\CondominiumBankAccount']
                ]
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoiceLine',
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

            // #memo - this info is not relevant for the whole invoice and is mostly (exclusively ?) used at line level
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

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\finance\accounting\AccountingEntry',
                'description'       => "Accounting entry of the invoice."
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\finance\accounting\AccountingEntry',
                'foreign_field'     => 'origin_object_id',
                'domain'            => ['origin_object_class', '=', 'realestate\purchase\accounting\invoice\PurchaseInvoice'],
                'description'       => 'Accounting entries relating to the invoice.',
                'help'              => "Purchase invoices might be subject to several accounting entries."
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'purchase_invoice_id',
                'domain'            => ['funding_type', '=', 'purchase_invoice'],
                'description'       => 'Fundings created from the invoice.'
            ],

            // #memo - emission_date presence is checked upon completion / validation
            'emission_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'Date at which the invoice was emitted by the supplier.',
                // 'required'          => true,
                'dependents'        => ['fiscal_year_id', 'fiscal_period_id']
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the invoice relates to.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'help'              => "Fiscal Year is automatically assigned based on emission_date or posting_date.",
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
                'help'              => "Period is automatically assigned based on emission_date or posting_date.",
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null],
                    ['fiscal_year_id', '=', 'object.fiscal_year_id'],
                    ['status', '=', 'open']
                ],
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'posting_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'The date on which the invoice is recorded in the accounting system.',
                'visible'           => ['has_date_range', '=', false],
                'onupdate'          => 'onupdatePostingDate',
                'dependents'        => ['fiscal_year_id', 'fiscal_period_id']
            ],

            'due_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'Deadline for the payment is expected.',
                // 'required'          => true
            ],

            'document_process_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\processing\DocumentProcess',
                'description'       => 'Document Process the invoice originates from.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Received Document that the invoice is issued from.',
                'help'              => 'Target document has is_origin set to true.'
            ],

            'documents_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\Document',
                'foreign_field'     => 'purchase_invoice_id',
                'description'       => 'All documents linked to the purchase invoice.',
            ],

            'document_link' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url.relative',
                'description'       => 'URL for visualizing the document.',
                'function'          => 'calcDocumentLink',
            ],

            'email_subject' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Subject of the email at the origin of the purchase invoice\'s document.',
                'store'             => false,
                'relation'          => ['document_id' => ['email_id' => 'subject']]
            ],

            'email_body' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/html.medium',
                'description'       => 'Body of the email at the origin of the purchase invoice\'s document.',
                'store'             => false,
                'relation'          => ['document_id' => ['email_id' => 'body']]
            ],

            'payable_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Expected Final tax-included invoiced amount.',
                'help'              => 'This field is used to compare with sum of invoice lines prices.',
                'dependents'        => ['price']
            ],

            'total_vat' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total tax amount included in invoiced amount.',
                'help'              => 'This field may be required for an ACP subject to VAT.'
            ],

            'has_payment_on_hold' => [
                'type'              => 'boolean',
                'description'       => 'Payment should not be made for now.',
                'default'           => false
            ],

            'on_hold_description' => [
                'type'              => 'string',
                'description'       => 'Short description explaining the reason of holding back the payment.',
                'visible'           => ['has_payment_on_hold', '=', true]
            ],

            // #todo - for now we limit this to VCS - with validation
            'payment_reference' => [
                'type'              => 'string',
                'description'       => 'Code provided by the supplier to use as reference in the wire transfer.'
            ],

            'has_date_range' => [
                'type'              => 'boolean',
                'description'       => 'Service delivered over a period of time.',
                'help'              => '',
                'default'           => true
            ],

            // #memo - some actions of this entity rely on status from DocumentProcessing
            'document_process_status' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Current status of the Document Processing.',
                'help'              => "This value is used in addition to the status, in order to check allowed actions.",
                'selection'         => [
                    'created',
                    'assigned',
                    'completed',
                    'validated',
                    'integrated',
                    'cancelled'
                ],
                'relation'          => ['document_process_id' => 'status'],
                'store'             => true,
                'readonly'          => true
            ],

            'assigned_employee_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'hr\employee\Employee',
                'description'       => 'Employee currently in charge of the processing.',
                'help'              => 'Assigned employee can evolve over time, and might depend on Role.',
                'relation'          => ['document_process_id' => 'assigned_employee_id'],
                'store'             => true,
                'readonly'          => true,
                'instant'           => true
            ],

            'alert' => [
                'type'              => 'computed',
                'usage'             => 'icon',
                'result_type'       => 'string',
                'description'       => 'Alert flag for the invoice.',
                'help'              => "Indicates if there is an issue with the invoice that needs attention, by providing an icon: success, info, warn, major, error.",
                'function'          => 'calcAlert',
                'onrevert'          => 'onrevertAlert',
                'store'             => true
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Proforma invoice, pending and still waiting to be completed.',
                'icon' => 'edit',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the invoice status based on the `invoice` field.',
                        'help'        => 'Assign invoice number, generate accounting entries and validate accounting entries.',
                        'policies'    => [
                            'can_be_invoiced',
                            'can_be_allocated'
                        ],
                        'onbefore'  => 'onbeforePost',
                        'onafter'   => 'onafterPost',
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

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'create_fundings' => [
                'description'   => 'Create the funding according to the invoice.',
                'policies'      => ['is_posted'],
                'function'      => 'doCreateFundings'
            ],
            'update_document_json' => [
                'description'   => 'Update the document data JSON with the newly provided data.',
                'policies'      => [],
                'function'      => 'doUpdateDocumentJson'
            ],
            'assign_invoice_number' => [
                'description'   => 'Creates accounting entries according to invoice lines.',
                'policies'      => [],
                'function'      => 'doAssignInvoiceNumber'
            ],
            'unlock' => [
                'description'   => 'Unlocks the invoice for editing.',
                'help'          => 'Reverts posted accounting entries, preserves the original sequence number, deletes the entries from the invoice, and returns the invoice to a proforma state while keeping its invoice_number. Allowed only if no entry has been cleared.',
                'policies'      => [
                    // not going to cancel but we use can_cancel to check the status and whether there are any "cleared" lines
                    'can_cancel'
                ],
                'function'      => 'doUnlock'
            ],
            'cancel' => [
                'description'   => 'Cancels the invoice permanently (non-reversible).',
                'help'          => 'Creates invisible reversal entries for all posted accounting entries and marks both the invoice and its entries as invisible. The invoice is permanently voided without creating a credit note and cannot be edited again.',
                'policies'      => ['can_cancel'],
                'function'      => 'doCancel'
            ],
            'mark_completed' => [
                'description'   => 'Mark the (proforma) invoice as complete and ready to be validated.',
                'help'          => 'Checks that required information are present and consistent, and relay to next person in charge, through related Document Process.',
                'policies'      => ['can_mark_completed'],
                'function'      => 'doMarkCompleted'
            ],
            'mark_validated' => [
                'description'   => 'Mark the (proforma) invoice as validated and ready to be posted (integrated).',
                'help'          => 'Invoice has been reviewed, and Employee with required role requests the invoice to be marked as validated.',
                'policies'      => ['can_mark_validated'],
                'function'      => 'doMarkValidated'
            ],
            'mark_cancelled' => [
                'description'   => 'Mark the (proforma) invoice as cancelled (not to be imported).',
                'policies'      => ['can_mark_cancelled'],
                'function'      => 'doMarkCancelled'
            ],
            'reverse_completed' => [
                'description'   => 'Move the (proforma) invoice back to the `assigned` step.',
                'policies'      => [/*'can_reverse_completed'*/],
                'function'      => 'doReverseCompleted'
            ],
            'reverse_validated' => [
                'description'   => 'Move the (proforma) invoice back to the `completed` step.',
                'policies'      => [/*'can_reverse_validated'*/],
                'function'      => 'doReverseValidated'
            ],
            'remove' => [
                'description'   => 'Remove the invoice and the processing.',
                'help'          => 'This action is meant to be used when the import was a mistake or the invoice is a duplicate. This action cannot be undone.',
                'policies'      => ['can_remove'],
                'function'      => 'doRemove'
            ]

        ]);
    }

    protected static function doCancel($self) {
        $self->read(['status', 'accounting_entry_id', 'document_process_id']);

        foreach($self as $id => $purchaseInvoice) {

            if($purchaseInvoice['status'] !== 'posted') {
                continue;
            }

            // revert document workflow if any
            if($purchaseInvoice['document_process_id']) {
                DocumentProcess::id($purchaseInvoice['document_process_id'])
                    // revert back to 'validated'
                    ->transition('revert');
            }

            // cancel accounting entry
            if($purchaseInvoice['accounting_entry_id']) {
                AccountingEntry::id($purchaseInvoice['accounting_entry_id'])->do('cancel');
            }

            // update invoice status
            self::id($id)->update([
                'status' => 'cancelled',
                'accounting_entry_id' => null,
                'document_process_status' => null,
                'alert' => null
            ]);

            // revert document workflow if any
            if($purchaseInvoice['document_process_id']) {
                DocumentProcess::id($purchaseInvoice['document_process_id'])
                    ->transition('cancel');
            }

        }
    }

    protected static function doUnlock($self) {
        $self->read(['status', 'accounting_entry_id', 'document_process_id']);
        foreach($self as $id => $purchaseInvoice) {
            if($purchaseInvoice['status'] !== 'posted') {
                continue;
            }

            if($purchaseInvoice['document_process_id']) {
                DocumentProcess::id($purchaseInvoice['document_process_id'])->transition('revert');
                // reset computed relation fields
                self::id($id)
                    ->update([
                        'document_process_status' => null,
                        'alert' => null
                    ]);
            }

            // reverse planned accounting entries, any
            AccountingEntry::search([
                    ['purchase_invoice_id', '=', $id],
                    ['status', '=', 'validated']
                ])
                ->do('cancel');

            self::id($id)
                ->update(['status' => 'proforma'])
                ->update(['accounting_entry_id' => null]);
        }
    }

    protected static function policyCanMarkCancelled($self) {
        $result = [];
        foreach($self as $id => $purchaseInvoice) {
        }
        return $result;
    }

    protected static function policyCanMarkCompleted($self) {
        $result = [];
        $self->read(['document_process_status', 'document_process_id']);
        foreach($self as $id => $purchaseInvoice) {
            if($purchaseInvoice['document_process_status'] !== 'assigned') {
                $result[$id] = [
                        'wrong_document_status_assigned' => 'Only `assigned` documents can be marked as complete.'
                    ];
                continue;
            }

            // #memo - it is the invoice (and not the DocumentProcess) that is responsible for ensuring that all required information is complete
            // #todo - this should be called through a ValidationRule
            try {
                // #memo - `assert-valid` controller is called at `MarkValidated` step
                \eQual::run('do', 'realestate_purchase_accounting_invoice_PurchaseInvoice_assert-complete', ['id' => $id]);
            }
            catch(\Exception $e) {
                trigger_error("APP::PurchaseInvoice [{$id}] cannot be marked as completed: " . $e->getMessage(), EQ_REPORT_WARNING);

                // resulting JSON violates the purchase-invoice JSON Schema in a way that is not covered by ValidationRule (shouldn't occur)
                $errors = unserialize($e->getMessage());

                // logs specific errors to ease debugging
                if(isset($errors['invalid_document'])) {
                    foreach($errors['invalid_document'] as $error_id => $error_message) {
                        if(is_array($error_message)) {
                            $error_message = json_encode($error_message);
                        }
                        trigger_error("APP::unexpected error on PurchaseInvoice [{$id}]: {$error_id} - {$error_message}", EQ_REPORT_ERROR);
                    }
                }
                elseif(isset($errors['missing_document'])) {
                }
                elseif(isset($errors['missing_document_type'])) {
                }
                elseif(isset($errors['invalid_document_type'])) {
                }
                elseif(isset($errors['missing_document_json'])) {
                }
                elseif(isset($errors['document_validation_error'])) {
                }

                $result[$id] = [
                        ($e->getCode()) => 'Some mandatory fields are missing or invalid.'
                    ];
            }
            finally {
                self::id($id)->update(['alert' => null]);
            }
        }
        return $result;
    }

    protected static function policyCanMarkValidated($self) {
        $result = [];
        $self->read(['document_process_status', 'document_process_id']);

        foreach($self as $id => $purchaseInvoice) {
            if($purchaseInvoice['document_process_status'] !== 'completed') {
                $result[$id] = [
                        'wrong_document_status_completed' => 'Only `completed` documents can be marked as valid.'
                    ];
                continue;
            }

            try {
                \eQual::run('do', 'realestate_purchase_accounting_invoice_PurchaseInvoice_assert-valid', ['id' => $id]);
            }
            catch(\Exception $e) {
                trigger_error("APP::PurchaseInvoice [{$id}] cannot be marked as completed: " . $e->getMessage(), EQ_REPORT_WARNING);
                $result[$id] = [
                        ($e->getCode()) => 'Some mandatory fields are missing or invoice is a duplicate.'
                    ];
            }
            finally {
                self::id($id)->update(['alert' => null]);
            }

        }
        return $result;
    }

    protected static function doMarkValidated($self) {
        $self->read(['document_process_id']);
        foreach($self as $id => $purchaseInvoice) {
            if(!$purchaseInvoice['document_process_id']) {
                continue;
            }
            DocumentProcess::id($purchaseInvoice['document_process_id'])->transition('validate');
            // reset computed relation fields
            self::id($id)->update([
                    'document_process_status' => null,
                    'alert' => null
                ]);
        }
    }

    protected static function doMarkCompleted($self) {
        $self->read(['document_process_id']);
        foreach($self as $id => $purchaseInvoice) {
            if(!$purchaseInvoice['document_process_id']) {
                continue;
            }
            try {
                DocumentProcess::id($purchaseInvoice['document_process_id'])->transition('complete');
            }
            catch(\Exception $e) {
                // this should not occur (if so, check policy can_mark_complete)
                trigger_error("APP::PurchaseInvoice [{$id}] cannot be marked as completed: " . $e->getMessage(), EQ_REPORT_WARNING);
                // throw $e;
            }
            // reset computed relation fields
            self::id($id)->update(['document_process_status' => null]);
        }
    }

    protected static function doMarkCancelled($self) {
        $self->read(['document_process_id']);
        foreach($self as $id => $purchaseInvoice) {
            if(!$purchaseInvoice['document_process_id']) {
                continue;
            }
            self::id($id)->delete();
            if($purchaseInvoice['document_process_id']) {
                DocumentProcess::id($purchaseInvoice['document_process_id'])->transition('cancel');
            }
        }
    }

    protected static function doReverseValidated($self) {
        $self->read(['document_process_id']);
        foreach($self as $id => $purchaseInvoice) {
            if(!$purchaseInvoice['document_process_id']) {
                continue;
            }
            DocumentProcess::id($purchaseInvoice['document_process_id'])->transition('revert');
            // reset computed relation fields
            self::id($id)->update(['document_process_status' => null]);
        }
    }

    protected static function doReverseCompleted($self) {
        $self->read(['document_process_id']);
        foreach($self as $id => $purchaseInvoice) {
            if(!$purchaseInvoice['document_process_id']) {
                continue;
            }
            DocumentProcess::id($purchaseInvoice['document_process_id'])->transition('revert');
            // reset computed relation fields
            self::id($id)->update(['document_process_status' => null]);
        }
    }

    protected static function doRemove($self) {
        // #memo - status is checked in can_remove, but also here for consistency security
        $self->read(['status', 'document_process_id']);
        foreach($self as $id => $purchaseInvoice) {
            if($purchaseInvoice['status'] !== 'proforma') {
                throw new \Exception('cannot_remove_non_proforma', EQ_ERROR_NOT_ALLOWED);
                continue;
            }

            self::id($id)->delete();
            if($purchaseInvoice['document_process_id']) {
                DocumentProcess::id($purchaseInvoice['document_process_id'])
                    // #memo - this sets the DocumentProcess has_target_object to false
                    ->update(['document_invoice_id' => null])
                    ->do('remove');
            }
        }
    }

    protected static function policyCanRemove($self) {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $purchaseInvoice) {
            if($purchaseInvoice['status'] === 'proforma') {
                $result[$id] = [
                        'cannot_remove_non_proforma' => 'Only non-posted invoice can be removed.'
                    ];
                continue;
            }
        }
        return [];
    }

    /**
     * Check whether purchase invoices can be cancelled.
     *
     * Rules:
     * - Invoice must be in "posted" status.
     * - At least one related accounting entry must be validated.
     * - No accounting entry may contain cleared lines.
     *
     * Returns an array of policy violations indexed by invoice id.
     */
    protected static function policyCanCancel($self) {
        $result = [];
        $self->read(['status', 'accounting_entries_ids' => ['status', 'has_cleared_lines']]);
        foreach($self as $id => $purchaseInvoice) {
            if($purchaseInvoice['status'] !== 'posted') {
                $result[$id] = [
                        'non_posted_invoice' => 'Only posted invoice can be cancelled or unlocked.'
                    ];
                continue;
            }
            $has_validated = false;
            $has_cleared_lines = false;
            foreach($purchaseInvoice['accounting_entries_ids'] as $accounting_entry_id => $accountingEntry) {
                if($accountingEntry['status'] === 'validated') {
                    $has_validated = true;
                }
                if($accountingEntry['has_cleared_lines']) {
                    $has_cleared_lines = true;
                    break;
                }
            }
            if(!$has_validated) {
                $result[$id] = [
                        'non_validated_entry' => 'Only invoice with validated entry can be cancelled or unlocked.'
                    ];
            }
            if($has_cleared_lines) {
                $result[$id] = [
                        'has_cleared_lines' => 'Accounting entry has already been cleared.'
                    ];
            }
        }
        return $result;
    }

    public static function getPolicies(): array {

        // #todo - make sure VCS ['payment_reference'] is valid
        return array_merge(parent::getPolicies(), [
            'can_be_allocated' => [
                'description' => 'Verifies that an invoice can be allocated of the posting date(s).',
                'function'    => 'policyCanBeAllocated'
            ],
            'can_cancel' => [
                'description' => 'Checks if the invoice can be cancelled.',
                'help'        => 'Ensures the (posted) invoice can be permanently cancelled: none of its accounting entries may be cleared, the invoice must not already be cancelled, and the fiscal period must allow cancellation.',
                'function'    => 'policyCanCancel'
            ],
            'can_remove' => [
                'description' => 'Checks if the invoice can be removed.',
                'help'        => 'Ensures the invoice is still a draft (proforma).',
                'function'    => 'policyCanRemove'
            ],
            // policies relating to Document Process
            'can_mark_cancelled' => [
                'description' => 'Checks if the invoice processing can be cancelled.',
                'function'    => 'policyCanMarkCancelled'
            ],
            'can_mark_completed' => [
                'description' => 'Checks if the invoice processing can be marked as completed.',
                'function'    => 'policyCanMarkCompleted'
            ],
            'can_mark_validated' => [
                'description' => 'Checks if the invoice processing can be marked as validated.',
                'function'    => 'policyCanMarkValidated'
            ]
        ]);
    }

    protected static function doCreateFundings($self) {
        $self->read([
                'condo_id', 'name', 'price', 'payment_reference', 'due_date', 'has_mandate', 'has_payment_on_hold',
                'funding_id',
                'suppliership_id',
                'suppliership_bank_account_id' => ['bank_account_id']
            ]);

        foreach($self as $id => $purchaseInvoice) {
            // ignore invoices that already have a funding
            if($purchaseInvoice['funding_id']) {
                continue;
            }
            // retrieve the condo's current account
            $bankAccount = CondominiumBankAccount::search([
                    ['condo_id', '=', $purchaseInvoice['condo_id']],
                    ['bank_account_type', '=', 'bank_current']
                ])
                ->first();

            $suppliershipAccount = Account::search([
                    ['condo_id', '=', $purchaseInvoice['condo_id']],
                    ['suppliership_id', '=', $purchaseInvoice['suppliership_id']],
                    ['operation_assignment', '=', 'suppliers']
                ])
                ->first();

            if(!$suppliershipAccount) {
                throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
            }

            $values = [
                    'condo_id'                          => $purchaseInvoice['condo_id'],
                    'description'                       => $purchaseInvoice['name'],
                    'funding_type'                      => 'purchase_invoice',
                    'purchase_invoice_id'               => $id,
                    'bank_account_id'                   => $bankAccount['id'],
                    'suppliership_id'                   => $purchaseInvoice['suppliership_id'],
                    'counterpart_bank_account_id'       => $purchaseInvoice['suppliership_bank_account_id']['bank_account_id'],
                    'accounting_account_id'             => $suppliershipAccount['id'],
                    'due_amount'                        => -$purchaseInvoice['price'],
                    'is_paid'                           => false,
                    'due_date'                          => $purchaseInvoice['due_date'],
                    'has_mandate'                       => $purchaseInvoice['has_mandate']
                ];

            if($purchaseInvoice['payment_reference'] && strlen($purchaseInvoice['payment_reference']) > 0) {
                // #memo - if not set, payment_reference will be computed based on invoice id
                $values['payment_reference'] = $purchaseInvoice['payment_reference'];
            }

            // no funding if payment on hold
            if(!$purchaseInvoice['has_payment_on_hold']) {
                $funding = Funding::create($values)->first();
                self::id($purchaseInvoice['id'])
                    ->update(['funding_id' => $funding['id']]);
            }
        }
    }

    // #memo - this is not used here - PurchaseInvoice are controlled through DocumentProcess and we use ValidationRule and a `validate` controller
    protected static function policyCanBeInvoiced($self, $dispatch): array {
        $result = [];
        return $result;
    }


    /**
     * Checks that if the invoice must be split, no part of it must be assigned to a non-open fiscal period (or fiscal year).
     */
    protected static function policyCanBeAllocated($self) {
        $result = [];
        $self->read(['posting_date', 'has_date_range', 'date_from', 'date_to', 'condo_id', 'fiscal_period_id' => ['date_from', 'date_to']]);
        foreach($self as $id => $invoice) {

            if($invoice['has_date_range']) {
                $date_from = $invoice['date_from'];
                $date_to = $invoice['date_to'];
            }
            elseif($invoice['posting_date']) {
                $date_from = $date_to = $invoice['posting_date'];
            }
            else {
                $date_from = $invoice['fiscal_period_id']['date_from'];
                $date_to = $invoice['fiscal_period_id']['date_to'];
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
                if(!$fiscalPeriod['fiscal_year_id'] || !in_array($fiscalPeriod['fiscal_year_id']['status'], ['preopen', 'open', 'preclosed'], true)) {
                    $result[$id] = [
                        'invalid_allocation_fiscal_year' => 'At least one fiscal year targeted by the invoice allocated is in a non-writable state.'
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
        $fiscalPeriods = FiscalPeriod::search(
                [
                    ['condo_id', '=', $condo_id],
                    ['date_from', '<=', $date_to],
                    ['date_to', '>=', $date_from]
                ],
                ['sort' => ['date_from' => 'asc']]
            )
            ->read(['date_from', 'date_to', 'fiscal_year_id' => ['id', 'name', 'status']])
            ->get();

        if(empty($fiscalPeriods)) {
            trigger_error('APP::Missing required fiscal periods for assigning (partly or full) a purchase invoice.', EQ_REPORT_WARNING);
            return [];
        }

        $expected_date = $date_from;

        foreach($fiscalPeriods as $fiscalPeriod) {
            if($fiscalPeriod['date_to'] < $expected_date) {
                continue;
            }

            if($fiscalPeriod['date_from'] > $expected_date) {
                trigger_error('APP::Missing required period for assigning (partly or full) a purchase invoice.', EQ_REPORT_WARNING);
                return [];
            }

            $result[] = $fiscalPeriod['date_from'];

            if($fiscalPeriod['date_to'] >= $date_to) {
                return $result;
            }

            $expected_date = $fiscalPeriod['date_to'] + 86400;
        }

        trigger_error('APP::Missing required period for assigning (partly or full) a purchase invoice.', EQ_REPORT_WARNING);
        return [];
    }

    protected static function onbeforePost($self) {
        $self
            ->do('generate_accounting_entries')
            ->do('assign_invoice_number')
            ->do('validate_accounting_entries')
            ->do('create_fundings');
    }

    protected static function onafterPost($self) {
        $self->read(['document_process_id']);
        foreach($self as $id => $invoice) {

            if($invoice['document_process_id']) {
                $dp = DocumentProcess::id($invoice['document_process_id']);

                $documentProcess = $dp->read(['status'])->first();
                $status = $documentProcess['status'];

                if($status !== 'integrated') {

                    if($status !== 'validated') {
                        $dp->update(['status' => 'validated']);
                    }

                    $dp->transition('integrate');
                }
            }

            // reset computed relation fields
            self::id($id)->update([
                    'document_process_status' => null,
                    'alert' => null,
                    'name'  => null
                ]);
        }
    }

    protected static function onrevertAlert($self) {
        $self->read(['document_process_id']);
        foreach($self as $id => $purchaseInvoice) {
            if(!$purchaseInvoice['document_process_id']) {
                continue;
            }
            DocumentProcess::id($purchaseInvoice['document_process_id'])
                ->update(['alert' => null]);
        }
    }

    /**
     * Cascade change to related DocumentProcess & Document
     */
    protected static function onupdateCondoId($self) {
        $self->read(['condo_id', 'document_process_id', 'document_id']);

        foreach($self as $id => $purchaseInvoice) {
            if($purchaseInvoice['document_process_id']) {
                DocumentProcess::id($purchaseInvoice['document_process_id'])
                    ->update(['condo_id' => $purchaseInvoice['condo_id']]);
            }
            if($purchaseInvoice['document_id']) {
                Document::id($purchaseInvoice['document_id'])
                    ->update(['condo_id' => $purchaseInvoice['condo_id']]);
            }

            // attempt to automatically assign the condominium's primary bank account
            $bankAccount = CondominiumBankAccount::search([
                    ['condo_id', '=', $purchaseInvoice['condo_id']],
                    ['bank_account_type', '=', 'bank_current'],
                    ['is_primary', '=', true]
                ])
                ->first();

            if($bankAccount) {
                self::id($id)->update(['condo_bank_account_id' => $bankAccount['id']]);
            }
        }

    }

    protected static function onupdateSuppliershipId($self) {
        $self->read(['condo_id', 'suppliership_id']);

        foreach($self as $id => $purchaseInvoice) {
            // attempt to automatically assign the supplier's primary bank account
            $bankAccount = SuppliershipBankAccount::search([
                    ['suppliership_id', '=', $purchaseInvoice['suppliership_id']],
                    ['is_primary', '=', true]
                ])
                ->first();

            if($bankAccount) {
                self::id($id)->update(['suppliership_bank_account_id' => $bankAccount['id']]);
            }
        }
    }

    protected static function onupdatePostingDate($self) {
        $self->read(['condo_id', 'posting_date', 'has_date_range']);
        foreach($self as $id => $purchaseInvoice) {
            if(!$purchaseInvoice['posting_date']) {
                continue;
            }
            if(!$purchaseInvoice['has_date_range']) {
                continue;
            }
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $purchaseInvoice['condo_id']],
                    ['date_from', '<=', $purchaseInvoice['posting_date']],
                    ['date_to', '>=', $purchaseInvoice['posting_date']]
                ])
                ->read(['id', 'name', 'fiscal_periods_ids' => ['name', 'date_from', 'date_to']])
                ->first();
            if(!$fiscalYear) {
                continue;
            }
            foreach($fiscalYear['fiscal_periods_ids'] ?? [] as $period_id => $period) {
                if($purchaseInvoice['posting_date'] >= $period['date_from'] && $purchaseInvoice['posting_date'] <= $period['date_to']) {
                    self::id($id)->update([
                        'date_from' => $period['date_from'],
                        'date_to' => $period['date_to']
                    ]);
                    break;
                }
            }
        }
    }

    protected static function onupdateSuppliershipBankAccountId($self) {
        /*
        $self->read(['condo_id', 'document_process_id', 'document_id', 'suppliership_bank_account_id']);

            if($purchaseInvoice['document_process_id']) {
                DocumentProcess::id($purchaseInvoice['document_process_id'])
                    ->update(['condo_id' => $purchaseInvoice['condo_id']]);
            }
            if($purchaseInvoice['document_id']) {
                Document::id($purchaseInvoice['document_id'])
                    ->update(['condo_id' => $purchaseInvoice['condo_id']]);
            }
        foreach($self as $id => $purchaseInvoice) {
            // attempt to automatically assign the supplier's primary bank account
            $bankAccount = SuppliershipBankAccount::search([
                    ['suppliership_id', '=', $purchaseInvoice['suppliership_id']],
                    ['is_primary', '=', true]
                ])
                ->first();

            if($bankAccount) {
                self::id($id)->update(['suppliership_bank_account_id' => $bankAccount['id']]);
            }
        }
        */
        // #todo - mettre à jour le document lié aussi
    }


    /**
     * Generates the initial accounting entry.
     *
     * The accounting entry created is meant to be instantly validated (with invoice validation action).
     *
     *
     */
    protected static function doGenerateAccountingEntries($self) {
        $self->read([
                'id', 'condo_id', 'price', 'description',
                'invoice_type',
                'emission_date', 'posting_date', 'has_date_range', 'date_from', 'date_to',
                'has_instant_reinvoice',
                'has_fund_usage',
                'fiscal_year_id',
                'fiscal_period_id' => ['date_from', 'date_to'],
                'accounting_entries_ids',
                'suppliership_id',
                'invoice_lines_ids' => [
                    'expense_account_id',
                    'description',
                    'price',
                    'is_private_expense',
                    'has_instant_reinvoice',
                    'owner_share',
                    'tenant_share',
                    'ownership_id',
                    'property_lot_id'
                ],
                'fund_usage_lines_ids' => [
                    'amount',
                    'fund_account_id',
                    'expense_account_id',
                    'description'
                ]
            ]);

        foreach($self as $id => $invoice) {

            // remove previously created entries, if any (there should be none)
            AccountingEntry::search([
                    ['status', '=', 'pending'],
                    ['origin_object_class', '=', 'realestate\purchase\accounting\invoice\PurchaseInvoice'],
                    ['origin_object_id', '=', $id]
                ])
                ->delete(true);

            $date_from = $date_to = $invoice['posting_date'];

            if($invoice['has_date_range']) {
                $date_from = $invoice['date_from'];
                $date_to = $invoice['date_to'];
            }
            else {
                $date_from = $invoice['fiscal_period_id']['date_from'];
                $date_to = $invoice['fiscal_period_id']['date_to'];
            }

            // retrieve journal dedicated to purchases
            $journal = Journal::search([['condo_id', '=', $invoice['condo_id']], ['journal_type', '=', 'PURC']])->first();
            if(!$journal) {
                trigger_error("APP::unable to find a match for journal PUR for condominium {$invoice['condo_id']}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_journal", EQ_ERROR_INVALID_CONFIG);
            }

            // retrieve accounts for private expenses
            $privateExpenseAccount = Account::search([['condo_id', '=', $invoice['condo_id']], ['operation_assignment', '=', 'private_expenses']])
                ->read(['id', 'name'])
                ->first();

            if(!$privateExpenseAccount) {
                trigger_error("APP::unable to find a match for private_expense account for condominium {$invoice['condo_id']}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_journal", EQ_ERROR_INVALID_CONFIG);
            }

            // #memo - use of the `reinvoiced_private_expense_account` has been deprecated

            // create the accounting entry for the purchase invoice
            $accountingEntry = AccountingEntry::create([
                    'condo_id'              => $invoice['condo_id'],
                    'journal_id'            => $journal['id'],
                    'fiscal_year_id'        => $invoice['fiscal_year_id'],
                    // #memo - if necessary, entry_date will be reassigned based on selected fiscal year and matching period (so that dates remain in ascending order)
                    'entry_date'            => $invoice['posting_date'],
                    'origin_object_class'   => self::getType(),
                    'origin_object_id'      => $id,
                    'purchase_invoice_id'   => $id
                ])
                ->first();

            self::id($id)->update(['accounting_entry_id' => $accountingEntry['id']]);

            // map for keeping track of scheduled accounting entries based on periods dates (ued as key)
            $map_planned_accounting_entries = [];

            $suppliershipAccount = Account::search([
                    ['condo_id', '=', $invoice['condo_id']],
                    ['suppliership_id', '=', $invoice['suppliership_id']]
                ])
                ->first();

            // 1) create the credit line on the supplier account
            AccountingEntryLine::create([
                    'condo_id'              => $invoice['condo_id'],
                    'accounting_entry_id'   => $accountingEntry['id'],
                    'description'           => $invoice['description'],
                    'account_id'            => $suppliershipAccount['id'],
                    'debit'                 => ($invoice['price'] > 0.0) ? 0.0 : abs($invoice['price']),
                    'credit'                => ($invoice['price'] > 0.0) ? abs($invoice['price']) : 0.0
                ]);


            // 2) create entry lines for reserve funds use, if any
            // #memo - reserve fund use is always considered for a single date
            if($invoice['has_fund_usage']) {
                foreach($invoice['fund_usage_lines_ids'] as $usage_line_id => $fundUsageLine) {

                    // pay with reserve fund : create the debit line on the reserve fund
                    AccountingEntryLine::create([
                            'condo_id'              => $invoice['condo_id'],
                            'accounting_entry_id'   => $accountingEntry['id'],
                            'description'           => $fundUsageLine['description'] ?? $invoice['description'],
                            'account_id'            => $fundUsageLine['fund_account_id'],
                            'fund_usage_line_id'    => $usage_line_id,
                            'debit'                 => ($fundUsageLine['amount'] > 0.0) ? abs($fundUsageLine['amount']) : 0.0,
                            'credit'                => ($fundUsageLine['amount'] > 0.0) ? 0.0 : abs($fundUsageLine['amount'])
                        ]);

                    // cancel the expense : create the credit line on the expense account (use of reserve fund)
                    AccountingEntryLine::create([
                            'condo_id'              => $invoice['condo_id'],
                            'accounting_entry_id'   => $accountingEntry['id'],
                            'description'           => $fundUsageLine['description'] ?? $invoice['description'],
                            'account_id'            => $fundUsageLine['expense_account_id'],
                            'fund_usage_line_id'    => $usage_line_id,
                            'debit'                 => ($fundUsageLine['amount'] > 0.0) ? 0.0 : abs($fundUsageLine['amount']),
                            'credit'                => ($fundUsageLine['amount'] > 0.0) ? abs($fundUsageLine['amount']) : 0.0
                        ]);

                }
            }

            // 3) create entry lines for private expenses, if any, and keep track of what should be taken into account by the working capital

            // delete any previously created pending MiscOperation
            MiscOperation::search(['purchase_invoice_id', '=', $id])->delete(true);

            // #memo - in case of a private expense, only first date is used for accounting
            foreach($invoice['invoice_lines_ids'] as $invoice_line_id => $invoiceLine) {
                if($invoiceLine['is_private_expense']) {
                    $ownershipAccount = Account::search([
                            ['condo_id', '=', $invoice['condo_id']],
                            ['ownership_id', '=', $invoiceLine['ownership_id']],
                            ['operation_assignment', '=', 'co_owners_working_fund']
                        ])
                        ->first();

                    if(!$ownershipAccount) {
                        throw new \Exception('missing_ownership_accounting_account', EQ_ERROR_INVALID_PARAM);
                    }

                    // create the debit line on the private expense account
                    AccountingEntryLine::create([
                            'condo_id'                  => $invoice['condo_id'],
                            'accounting_entry_id'       => $accountingEntry['id'],
                            'description'               => $invoiceLine['description'],
                            'account_id'                => $privateExpenseAccount['id'],
                            'purchase_invoice_line_id'  => $invoice_line_id,
                            'debit'                     => ($invoiceLine['price'] > 0.0) ? abs($invoiceLine['price']) : 0.0,
                            'credit'                    => ($invoiceLine['price'] > 0.0) ? 0.0 : abs($invoiceLine['price'])
                        ]);

                    if($invoiceLine['has_instant_reinvoice']) {
                        // #todo - il faut émettre quelque chose ici (facture de vente ou autre)
                        // with immediate la valider directement pour que les écritures soient dans la compta
                        // sinon il n'y a rien qui demande au ownership le paiement

                        $saleJournal = Journal::search([
                                ['condo_id', '=', $invoice['condo_id']],
                                ['journal_type', '=', 'SALE']
                            ])
                            ->first();

                        $miscOperation = MiscOperation::create([
                                'condo_id'              => $invoice['condo_id'],
                                'description'           => 'Refacturation ' . $invoice['description'],
                                'purchase_invoice_id'   => $id,
                                'journal_id'            => $saleJournal['id'],
                                'posting_date'          => $invoice['posting_date']
                            ])
                            ->first();

                        // create the debit line on the ownership account
                        MiscOperationLine::create([
                                'condo_id'                  => $invoice['condo_id'],
                                'misc_operation_id'         => $miscOperation['id'],
                                'description'               => $invoice['description'],
                                'account_id'                => $ownershipAccount['id'],
                                'debit'                     => ($invoiceLine['price'] > 0.0) ? abs($invoiceLine['price']) : 0.0,
                                'credit'                    => ($invoiceLine['price'] > 0.0) ? 0.0 : abs($invoiceLine['price'])
                            ]);

                        // create the credit line on the private expense - we must force the ownership_id and property_lot_id
                        MiscOperationLine::create([
                                'condo_id'                  => $invoice['condo_id'],
                                'misc_operation_id'         => $miscOperation['id'],
                                'is_private_expense'        => true,
                                'description'               => $invoice['description'],
                                'account_id'                => $privateExpenseAccount['id'],
                                'debit'                     => ($invoiceLine['price'] > 0.0) ? 0.0 : abs($invoiceLine['price']),
                                'credit'                    => ($invoiceLine['price'] > 0.0) ? abs($invoiceLine['price']) : 0.0,
                                'ownership_id'              => $invoiceLine['ownership_id'],
                                'property_lot_id'           => $invoiceLine['property_lot_id'],
                                'owner_share'               => $invoiceLine['owner_share'],
                                'tenant_share'              => $invoiceLine['tenant_share']
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
                            'condo_id'                  => $invoice['condo_id'],
                            'accounting_entry_id'       => $accountingEntry['id'],
                            'description'               => $invoice['description'],
                            'account_id'                => $invoiceLine['expense_account_id'],
                            'purchase_invoice_line_id'  => $invoice_line_id,
                            'debit'                     => ($invoiceLine['price'] > 0.0) ? abs($invoiceLine['price']) : 0.0,
                            'credit'                    => ($invoiceLine['price'] > 0.0) ? 0.0 : abs($invoiceLine['price'])
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

                    $total_amount = round($invoiceLine['price'], 2);
                    $remaining_amount = $total_amount;

                    for($i = 0, $n = count($allocation_dates); $i < $n; ++$i) {

                        if(abs($remaining_amount) <= 0.01) {
                            break;
                        }

                        $period_date_from = $allocation_dates[$i];
                        $period_date_to = ($i+1 < $n) ? ($allocation_dates[$i+1] - 86400) : $date_to;

                        // first date of the date range
                        if($i == 0) {
                            // create the debit line for the whole common expense
                            AccountingEntryLine::create([
                                    'condo_id'                  => $invoice['condo_id'],
                                    'accounting_entry_id'       => $accountingEntry['id'],
                                    'description'               => $invoiceLine['description'],
                                    'account_id'                => $invoiceLine['expense_account_id'],
                                    'purchase_invoice_line_id'  => $invoice_line_id,
                                    'debit'                     => ($invoiceLine['price'] > 0.0) ? abs($invoiceLine['price']) : 0.0,
                                    'credit'                    => ($invoiceLine['price'] > 0.0) ? 0.0 : abs($invoiceLine['price'])
                                ]);

                            // compute paid amount pro-rata based on the duration of the date range.
                            $intersect_from = max($date_from, $period_date_from);
                            $intersect_to = min($date_to, $period_date_to);
                            $intersect_days = ( ($intersect_to - $intersect_from) / 86400 ) + 1;
                            $ratio = $intersect_days / $total_days;
                            $amount = round($total_amount * $ratio, 2);
                            // #memo - no entry line with $amount here: resulting allocated amount for first period will be the delta with following deferred lines
                            $remaining_amount = round($remaining_amount - $amount, 2);
                        }

                        $date_range_within_posting_period =
                            $date_from >= $invoice['fiscal_period_id']['date_from']
                            && $date_to <= $invoice['fiscal_period_id']['date_to'];

                        if($date_range_within_posting_period) {
                            continue;
                        }

                        // 1) create deferred entry lines
                        $description = $invoice['description'];
                        $description .= ' (' . date('Y-m-d', $period_date_from) . ' - ' . date('Y-m-d', $period_date_to) . ')';

                        if($i == $n-1) {
                            $amount = round($remaining_amount, 2);
                        }
                        else {
                            //  we allocate the paid amount pro-rata based on the duration of the date range.
                            $intersect_from = max($date_from, $period_date_from);
                            $intersect_to = min($date_to, $period_date_to);

                            $intersect_days = ( ($intersect_to - $intersect_from) / 86400 ) + 1;
                            $ratio = $intersect_days / $total_days;
                            $amount = round($total_amount * $ratio, 2);
                            $remaining_amount = round($remaining_amount - $amount, 2);
                        }

                        // create the debit line for the deferred expense
                        AccountingEntryLine::create([
                                'condo_id'                  => $invoice['condo_id'],
                                'accounting_entry_id'       => $accountingEntry['id'],
                                'description'               => $description,
                                'account_id'                => $deferredExpensesAccount['id'],
                                'purchase_invoice_line_id'  => $invoice_line_id,
                                'debit'                     => ($amount > 0.0) ? abs($amount) : 0.0,
                                'credit'                    => ($amount > 0.0) ? 0.0 : abs($amount)
                            ]);

                        // create the credit line for the expense
                        AccountingEntryLine::create([
                                'condo_id'                  => $invoice['condo_id'],
                                'accounting_entry_id'       => $accountingEntry['id'],
                                'description'               => $description,
                                'account_id'                => $invoiceLine['expense_account_id'],
                                'purchase_invoice_line_id'  => $invoice_line_id,
                                'debit'                     => ($amount > 0.0) ? 0.0 : abs($amount),
                                'credit'                    => ($amount > 0.0) ? abs($amount) : 0.0
                            ]);

                        // 2) schedule a symmetrical accounting entry for the related period
                        $plannedFiscalYear = FiscalYear::search([['condo_id', '=', $invoice['condo_id']], ['date_from', '<=', $period_date_from], ['date_to', '>=', $period_date_from]])->first();

                        if(!$plannedFiscalYear) {
                            throw new \Exception("missing_mandatory_matching_fiscal_year", EQ_ERROR_INVALID_CONFIG);
                        }

                        // put all lines related to a period on a single accounting entry
                        if(!isset($map_planned_accounting_entries[$period_date_from])) {
                            $map_planned_accounting_entries[$period_date_from] = AccountingEntry::create([
                                    'condo_id'              => $invoice['condo_id'],
                                    'journal_id'            => $journal['id'],
                                    'fiscal_year_id'        => $plannedFiscalYear['id'],
                                    'entry_date'            => $period_date_from,
                                    'origin_object_class'   => self::getType(),
                                    'origin_object_id'      => $id,
                                    'purchase_invoice_id'   => $id
                                ])
                                ->first();
                        }

                        $plannedAccountingEntry = $map_planned_accounting_entries[$period_date_from];

                        // create the credit line for the deferred expense
                        AccountingEntryLine::create([
                                'condo_id'                  => $invoice['condo_id'],
                                'accounting_entry_id'       => $plannedAccountingEntry['id'],
                                'description'               => $description,
                                'account_id'                => $deferredExpensesAccount['id'],
                                'purchase_invoice_line_id'  => $invoice_line_id,
                                'debit'                     => ($amount > 0.0) ? 0.0 : abs($amount),
                                'credit'                    => ($amount > 0.0) ? abs($amount) : 0.0
                            ]);

                        // create the debit line for the expense
                        AccountingEntryLine::create([
                                'condo_id'                  => $invoice['condo_id'],
                                'accounting_entry_id'       => $plannedAccountingEntry['id'],
                                'description'               => $description,
                                'account_id'                => $invoiceLine['expense_account_id'],
                                'purchase_invoice_line_id'  => $invoice_line_id,
                                'debit'                     => ($amount > 0.0) ? abs($amount) : 0.0,
                                'credit'                    => ($amount > 0.0) ? 0.0 : abs($amount)
                            ]);



                    }
                }
            }

            // validate all scheduled accounting entries
            foreach($map_planned_accounting_entries as $period_date_from => $plannedAccountingEntry) {
                AccountingEntry::id($plannedAccountingEntry['id'])->transition('validate');
            }

        }
    }

    protected static function doAssignInvoiceNumber($self) {
        $self->read(['condo_id', 'invoice_number', 'fiscal_year_id' => ['code'], 'fiscal_period_id' => ['code']]);
        foreach($self as $id => $invoice) {
            // #memo - unlocked invoices are set to status `proforma`, but keep their invoice number
            if($invoice['invoice_number']) {
                continue;
            }
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
                        'year'      => substr($invoice['fiscal_year_id']['code'] ?? '', 0, 2),
                        'period'    => $invoice['fiscal_period_id']['code'] ?? 0,
                        'condo'     => $invoice['condo_id'],
                        'sequence'  => $sequence
                    ]);
                self::id($id)->update([
                        'invoice_number' => $invoice_number,
                        'name'           => null
                    ]);
            }
        }
    }

    protected static function doValidateAccountingEntries($self) {
        $self->read(['accounting_entries_ids' => ['status']]);
        foreach($self as $id => $invoice) {
            foreach($invoice['accounting_entries_ids'] as $accounting_entry_id => $accountingEntry) {
                if($accountingEntry['status'] == 'pending') {
                    AccountingEntry::id($accounting_entry_id)->transition('validate');
                }
            }
            // post related miscellaneous operations, if any
            MiscOperation::search(['purchase_invoice_id', '=', $id])
                ->transition('publish')
                ->transition('post');
        }
    }

    protected static function calcAlert($self, $orm) {
        $result = [];
        foreach($self as $id => $purchaseInvoice) {
            $messages_ids = $orm->search('core\alert\Message',[ ['object_class', '=', 'realestate\purchase\accounting\invoice\PurchaseInvoice'], ['object_id', '=', $id]]);
            if($messages_ids > 0 && count($messages_ids)) {
                $max_alert = 0;
                $map_alert = array_flip([
                    'notice',           // weight = 1, might lead to a warning
                    'warning',          // weight = 2, might be important, might require an action
                    'important',        // weight = 3, requires an action
                    'error'             // weight = 4, requires immediate action
                ]);
                $messages = $orm->read(\core\alert\Message::getType(), $messages_ids, ['severity']);
                foreach($messages as $mid => $message){
                    $weight = $map_alert[$message['severity']];
                    if($weight > $max_alert) {
                        $max_alert = $weight;
                    }
                }
                switch($max_alert) {
                    case 0:
                        $result[$id] = 'info';
                        break;
                    case 1:
                        $result[$id] = 'warn';
                        break;
                    case 2:
                        $result[$id] = 'major';
                        break;
                    case 3:
                    default:
                        $result[$id] = 'error';
                        break;
                }
            }
            else {
                $result[$id] = 'success';
            }
        }
        return $result;
    }

    protected static function calcFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'posting_date']);
        foreach($self as $id => $invoice) {
            if(!$invoice['posting_date']) {
                continue;
            }
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $invoice['condo_id']],
                    ['date_from', '<=', $invoice['posting_date']],
                    ['date_to', '>=', $invoice['posting_date']]
                ])
                ->first();
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
            if(!$invoice['posting_date']) {
                continue;
            }
            foreach($invoice['fiscal_year_id']['fiscal_periods_ids'] ?? [] as $period_id => $period) {
                if($invoice['posting_date'] >= $period['date_from'] && $invoice['posting_date'] <= $period['date_to']) {
                    $result[$id] = $period_id;
                    break;
                }
            }
        }
        return $result;
    }

    private static function computeDocumentLink($document_id) {
        return '/document/' . $document_id;
    }

    protected static function calcDocumentLink($self) {
        $result = [];
        $self->read(['document_id']);
        foreach($self as $id => $invoice) {
            if($invoice['document_id']) {
                $result[$id] = self::computeDocumentLink($invoice['document_id']);
            }
        }
        return $result;
    }

    /**
     * This method is used to create the document based on received data, and start the processing.
     * #memo - this differs slightly from Bank Statements since invoices can be imported in 1 step.
     */
    protected static function onupdateDocumentData($self, $auth) {
        $self->read(['document_process_id', 'document_name', 'document_data']);
        $documentType = DocumentType::search(['code', '=', 'invoice'])->first();
        $user = User::id($auth->userId())->read(['employee_id'])->first();

        foreach($self as $id => $invoice) {
            if(!$invoice['document_process_id']) {
                $collection = DocumentProcess::create([
                        'name'                  => $invoice['document_name'],
                        'document_type_id'      => $documentType['id'],
                        'assigned_employee_id'  => $user['employee_id']
                    ]);
            }
            else {
                $collection = DocumentProcess::id($invoice['document_process_id']);
            }

            $documentProcess = $collection
                ->update(['data' => $invoice['document_data']])
                ->read(['document_id'])
                ->first();

            self::id($id)
                ->update([
                    'document_process_id' => $documentProcess['id'],
                    'document_id'         => $documentProcess['document_id'],
                    'document_data'       => null
                ]);
        }
    }

    /**
     * #memo - posting_date is synched based on emission_date in parent::onchange($event, $values)
     */
    public static function onchange($event, $values) {
        $result = [];
        if(isset($values['condo_id'])) {
            if(isset($event['emission_date'])) {
                $result['date_from'] = $event['emission_date'];
                $result['date_to'] = $event['emission_date'];

                $has_date_range = $event['has_date_range'] ?? $values['has_date_range'] ?? false;

                if($has_date_range) {
                    // force updating date_from and date_to accordingly
                    $event['has_date_range'] = true;
                }

                $result['posting_date'] = $event['emission_date'];
                // force updating fiscal_year and fiscal_period accordingly
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
                                // force updating date_from and date_to accordingly
                                $event['fiscal_period_id'] = $period_id;
                                break;
                            }
                        }
                    }
                }
            }
        }
        if(array_key_exists('invoice_lines_ids', $event)) {
            $result['price'] = static::computePrice($values['id']);
        }
        if(isset($event['emission_date'])) {
            $result['due_date'] = strtotime('+30 days', strtotime('last day of this month', $event['emission_date']));
        }
        if(isset($event['document_data']['name'])) {
            $result['document_name'] = $event['document_data']['name'];
        }
        if(isset($event['document_id'])) {
            $result['document_link'] = self::computeDocumentLink($event['document_id']);
        }
        if(array_key_exists('suppliership_id', $event)) {
            if(isset($event['suppliership_id'])) {
                $suppliership = Suppliership::id($event['suppliership_id'])->read(['supplier_id' => ['identity_id']])->first();
                $result['supplier_identity_id'] = $suppliership['supplier_id']['identity_id'];
            }
            $result['suppliership_bank_account_id'] = null;
        }
        if(isset($event['payment_reference'])) {
            $result['payment_reference'] = str_replace(['+', '/', ' '], '', $event['payment_reference']);
        }

        if(isset($event['has_date_range']) && $event['has_date_range']) {
            // if given, assign date_from and date_to based on fiscal period
            $fiscal_period_id = $event['fiscal_period_id'] ?? $values['fiscal_period_id'] ?? null;
            if($fiscal_period_id) {
                $fiscalPeriod = FiscalPeriod::id($fiscal_period_id)
                    ->read(['date_from', 'date_to'])
                    ->first();
                if($fiscalPeriod) {
                    $result['date_from'] = $fiscalPeriod['date_from'];
                    $result['date_to'] = $fiscalPeriod['date_to'];
                }
            }
        }

        // do not apply parent onchange, to reduce complexity
        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['status', 'document_process_id' => ['status'], 'fiscal_period_id' => ['status']]);
        foreach($self as $id => $invoice) {
            $allowed_fields = [
                    'status', 'alert', 'name', 'price', 'total', 'document_process_status', 'assigned_employee_id', 'invoice_number', 'payment_status', 'has_payment_on_hold', 'customer_ref', 'funding_id', 'accounting_entry_id', 'reversed_invoice_id'
                ];
            if(count(array_diff(array_keys($values), $allowed_fields)) > 0) {
                if($invoice['status'] !== 'proforma') {
                    return ['status' => ['non_editable' => 'Purchase Invoice cannot be updated after recording.']];
                }
                if($invoice['document_process_id'] && $invoice['document_process_id']['status'] === 'integrated') {
                    return ['status' => ['non_editable' => 'Purchase Invoice cannot be updated after Document processing.']];
                }
            }
            if($invoice['fiscal_period_id'] && $invoice['fiscal_period_id']['status'] !== 'open') {
                return ['fiscal_period_id' => ['closed_fiscal_period' => 'Invoice cannot be allocated to a closed fiscal period.']];
            }
        }
    }

    /**
     * Sync back manually encoded values to the JSON structure of the linked document, if any.
     * Values are mapped according to the relevant schema : `urn:fmt:json-schema:finance:purchase-invoice`
     *
     */
    protected static function doUpdateDocumentJson($self) {
        $self->read([
                'condo_id',
                'document_process_id',
                'invoice_lines_ids',
                'suppliership_id',
                'suppliership_bank_account_id',
                'supplier_invoice_number',
                'invoice_type',
                'due_date',
                'emission_date',
                'payment_reference',
                'payable_amount',
                'has_date_range',
                'date_from',
                'date_to'
            ]);

        foreach($self as $id => $invoice) {
            if(!$invoice['document_process_id']) {
                continue;
            }

            $fields = [];

            if(isset($invoice['condo_id'])) {
                $condominium = Condominium::id($invoice['condo_id'])->read(['name', 'address_street', 'address_city', 'address_zip', 'address_country'])->first();
                $fields['customer']['name'] = $condominium['name'];
                $fields['customer']['address']['street'] = $condominium['address_street'];
                $fields['customer']['address']['city'] = $condominium['address_city'];
                $fields['customer']['address']['postal_code'] = $condominium['address_zip'];
                $fields['customer']['address']['country'] = $condominium['address_country'];
            }

            if(isset($invoice['suppliership_bank_account_id'])) {
                $bankAccount = SuppliershipBankAccount::id($invoice['suppliership_bank_account_id'])->read(['bank_account_iban', 'bank_account_bic'])->first();
                $fields['payment']['iban'] = $bankAccount['bank_account_iban'];
                $fields['payment']['bic'] = $bankAccount['bank_account_bic'];
            }

            if(isset($invoice['suppliership_id'])) {
                $suppliership = Suppliership::id($invoice['suppliership_id'])->read(['supplier_id' => ['name', 'vat_number', 'registration_number', 'address_street', 'address_city', 'address_zip', 'address_country']])->first();
                $fields['supplier']['name'] = $suppliership['supplier_id']['name'];
                $fields['supplier']['vat_id'] = $suppliership['supplier_id']['vat_number'];
                $fields['supplier']['company_id'] = $suppliership['supplier_id']['registration_number'];
                $fields['supplier']['address']['street'] = $suppliership['supplier_id']['address_street'];
                $fields['supplier']['address']['city'] = $suppliership['supplier_id']['address_city'];
                $fields['supplier']['address']['postal_code'] = $suppliership['supplier_id']['address_zip'];
                $fields['supplier']['address']['country'] = $suppliership['supplier_id']['address_country'];
            }

            if(isset($invoice['invoice_type'])) {
                $fields['invoice_type'] = $invoice['invoice_type'];
            }

            if(isset($invoice['supplier_invoice_number'])) {
                $fields['invoice_number'] = $invoice['supplier_invoice_number'];
            }

            if(isset($invoice['due_date'])) {
                $fields['due_date'] = date('c', $invoice['due_date']);
            }

            if(isset($invoice['emission_date'])) {
                $fields['issue_date'] = date('c', $invoice['emission_date']);
            }

            if(isset($invoice['payment_reference'])) {
                $fields['payment']['payment_id'] = $invoice['payment_reference'];
            }

            // #memo - 30 = Bank transfer (credit transfer); 48 = Direct debit (withdrawal)
            // #todo - handle SEPA based on active contract with supplier, if any
            // $fields['payment']['payment_means_code'] = 30;

            // #memo - this field never changes : it is computed through the lines
            if(isset($invoice['payable_amount'])) {
                $fields['totals']['total_incl_tax'] = $invoice['payable_amount'];
                $fields['totals']['payable_amount'] = $invoice['payable_amount'];
            }

            if($invoice['has_date_range'] && isset($invoice['date_from'], $invoice['date_to'])) {
                $fields['invoice_period']['start_date'] = date('c', $invoice['date_from']);
                $fields['invoice_period']['end_date'] = date('c', $invoice['date_to']);
            }

            if(count($invoice['invoice_lines_ids'])) {
                // re-sync all lines
                $invoiceLines = PurchaseInvoiceLine::ids($invoice['invoice_lines_ids'])->read(['description', 'qty', 'unit_price', 'vat_rate', 'total']);
                $line_index = 1;
                $fields['lines'] = [];
                foreach($invoiceLines as $invoiceLine) {
                    $line = [
                        'id'            => (string) ($line_index++),
                        'description'   => $invoiceLine['description'],
                        'quantity'      => $invoiceLine['qty'],
                        'unit_price'    => $invoiceLine['unit_price'],
                        'unit_code'     => 'C62',
                        'amount'        => $invoiceLine['total'],
                        'tax'           => [
                            'category_id'   => 'S',
                            'percent'       => intval(round($invoiceLine['vat_rate'], 2) * 100),
                            'scheme_id'     => 'VAT'
                        ]
                    ];

                    $fields['lines'][] = $line;
                }
            }

            DocumentProcess::id($invoice['document_process_id'])->do('update_document_json', $fields);
        }
    }

    protected static function onafterupdate($self, $auth) {
        $self->read(['state', 'document_id', 'condo_id']);
        $user = User::id($auth->userId())->read(['employee_id'])->first();

        foreach($self as $id => $purchaseInvoice) {
            if($purchaseInvoice['state'] === 'instance' && !$purchaseInvoice['document_id']) {
                $documentType = DocumentType::search(['code', '=', 'invoice'])->first();
                $data = \eQual::run('get', 'documents_processing_PurchaseInvoice_empty');

                $document = Document::create([
                        'condo_id'              => $purchaseInvoice['condo_id'],
                        'name'                  => sprintf("%s %06d", 'facture d\'achat', $id),
                        'purchase_invoice_id'   => $id,
                        'document_type_id'      => $documentType['id'],
                        'document_json'         => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                        'is_origin'             => true,
                        'is_source'             => false
                    ])
                    ->first();

                $documentProcess = DocumentProcess::create([
                        'condo_id'              => $purchaseInvoice['condo_id'],
                        'name'                  => sprintf("%s %06d", 'facture d\'achat', $id),
                        'description'           => 'facture d\'achat - encodage manuel',
                        'document_id'           => $document['id'],
                        'document_invoice_id'   => $id,
                        'document_type_id'      => $documentType['id'],
                        'document_origin'       => 'manual',
                        'has_target_object'     => true,
                        'assigned_employee_id'  => $user['employee_id']
                    ])
                    ->first();

                self::id($id)->update([
                        'document_id'           => $document['id'],
                        'document_process_id'   => $documentProcess['id']
                    ]);
            }
        }
        $self->do('update_document_json');
    }

}
