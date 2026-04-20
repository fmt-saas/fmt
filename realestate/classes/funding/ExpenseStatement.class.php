<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\funding;

use documents\Document;
use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;
use finance\accounting\Account;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\accounting\MiscOperationLine;
use finance\bank\BankStatementLine;
use finance\bank\CondominiumBankAccount;
use realestate\finance\accounting\CondoFund;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\finance\accounting\AccountingEntry;
use realestate\finance\accounting\AccountingEntryLine;
use realestate\ownership\OwnershipCommunicationPreference;
use realestate\purchase\accounting\invoice\PurchaseInvoiceLine;
use realestate\sale\pay\Funding;

#memo - Expense statements are handled as sales invoices
class ExpenseStatement extends \realestate\sale\accounting\invoice\SaleInvoice {

    public static function getName() {
        return 'Expense Statement';
    }

    public static function getDescription() {
        return "An Expense Statement is issued at the end of the fiscal period and allows the co-owners to cover common expenses, as well as to reimburse any private charges they may have.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => "Short description of the request execution.",
                'store'             => true
            ],

            /* from finance\accounting\invoice\Invoice: */
            // 'condo_id'
            // 'fiscal_year_id'
            // 'fiscal_period_id'
            // 'accounting_entry_id'
            // 'emission_date'
            // 'due_date'

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the invoice refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'onupdate'          => 'onupdateCondoId',
                'oncreate'          => 'onupdateCondoId',
                'readonly'          => true
            ],

            'invoice_type' => [
                'type'              => 'string',
                'description'       => 'Document type (expense statements handled as sale invoices).',
                'default'           => 'expense_statement',
                'readonly'          => true
            ],

            'fiscal_period_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the invoice statement relates to.",
                'help'              => "Posting date is automatically assigned on the last day of the period.",
                'onupdate'          => 'onupdateFiscalPeriodId',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['fiscal_year_id', '=', 'object.fiscal_year_id']]
            ],

            /* from sale\accounting\invoice\Invoice: */
            // 'funding_id'

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => 'There is no customer for fund requests.',
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\ExpenseStatementOwnerLine',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete'
            ],

            /* additional fields*/

            'exporting_tasks_ids' => [
                'type'              => 'one2many',
                'description'       => "Reference to the task for exporting paper mails for assembly invitation, if any.",
                'foreign_object'    => 'documents\export\ExportingTask',
                'foreign_field'     => 'object_id',
                'domain'            => [
                    ['object_class', '=', 'realestate\funding\ExpenseStatement']
                ]
            ],

            'statements_exporting_task_id' => [
                'type'              => 'many2one',
                'description'       => "Reference to the task for exporting paper mails for expense statement correspondences, if any.",
                'foreign_object'    => 'documents\export\ExportingTask'
            ],

            'expense_statement_correspondences_ids' => [
                'type'              => 'one2many',
                'description'       => "Correspondences sent for the expense statement.",
                'foreign_object'    => 'realestate\funding\ExpenseStatementCorrespondence',
                'foreign_field'     => 'expense_statement_id'
            ],

            'statement_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'description'       => 'Bank account to use for the request.',
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null],
                    ['object_class', '=', 'finance\bank\CondominiumBankAccount']
                ]
            ],

            'statement_owners_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\ExpenseStatementOwner',
                'foreign_field'     => 'expense_statement_id',
                'description'       => "List of Owners Statements.",
                'ondelete'          => 'cascade'
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'expense_statement_id',
                'description'       => 'The fundings that relate to the execution (sale invoice).'
            ],

            'common_total' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total amount assigned from common expenses.',
                'dependents'        => ['price', 'total']
            ],

            'private_total' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total amount assigned from private expenses.',
                'dependents'        => ['price', 'total']
            ],

            'provisions_total' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total amount assigned from requested provisions.',
                'dependents'        => ['price', 'total']
            ],

            'assigned_delta' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Rounding delta between allocation and expenses, if any.'
            ],

            'schema' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'application/json',
                'function'          => 'calcSchema',
                'store'             => false,
                'help'              => 'This field is not intended to be stored and can safely be computed at any time since its relies on immutable data.'
            ],

            'is_cutoff_at_period_end' => [
                'type'              => 'boolean',
                'description'       => 'Indicates that the cutoff is applied at the end of the accounting period.',
                'default'           => false,
                'onupdate'          => 'onupdateIsCutoffAtPeriodEnd'
            ],

            'is_cutoff_at_document_date' => [
                'type'              => 'boolean',
                'description'       => "Indicates that the cutoff is applied based on the document issuance date.",
                'default'           => true,
                'onupdate'          => 'onupdateIsCutoffAtDocumentDate'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Draft expense statement, pending and still waiting to be completed.',
                'icon' => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the invoice status based on the `invoice` field. Assign invoice number, generate accounting entries and validate accounting entries.',
                        'policies'    => [
                            'can_be_invoiced',
                            'is_valid',
                            'is_balanced',
                            'can_generate_statement',
                            'can_generate_accounting_entries',
                            'can_generate_fundings',
                            'can_assign_invoice_number',
                            'can_clear_accounting_entry_lines',
                            'can_validate_accounting_entries',
                            'can_close_fiscal_period',
                            'can_generate_expense_statement_correspondences',
                            'can_send_expense_statements'
                        ],
                        'onbefore'  => 'onbeforeInvoice',
                        'status'    => 'posted'
                    ]
                ],
            ],
            'posted' => [
                'description' => 'Expense statement can no longer be modified and can be sent to the customer.',
                'icon' => 'receipt_long',
                // il faut valider qu'on puisse faire ca : periode concernée en preclosed (sinon message disant qu'il faut réouvrir)
                'transitions' => [
                    'cancel' => [
                        'description'   => 'Set the invoice and receivables statuses as cancelled.',
                        'policies'      => [],
                        'onafter'       => 'onafterCancel',
                        'status'        => 'cancelled'
                    ]
                ],
            ],
            'cancelled' => [
                'description' => 'The expense statement is cancelled. There are no transitions available.',
                'icon' => 'cancel',
                'transitions' => []
            ],
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'generate_statement' => [
                'description'   => 'Generate the request lines according to the property lots of the condominium and their respective shares.',
                'policies'      => ['can_generate_statement'],
                'function'      => 'doGenerateStatement'
            ],
            'generate_expense_statement_correspondences' => [
                'description'   => 'Generate correspondences for each Ownership.',
                'policies'      => ['can_generate_statement'],
                'function'      => 'doGenerateExpenseStatementCorrespondences'
            ],
            'generate_fundings' => [
                'description'   => 'Generate fundings for each involved ownership.',
                'policies'      => [],
                'function'      => 'doGenerateFundings'
            ],
            'clear_accounting_entry_lines' => [
                'description'   => 'Mark original accounting entries (records) as cleared by expense statement.',
                'policies'      => [],
                'function'      => 'doClearAccountingEntryLines'
            ],
            'close_fiscal_period' => [
                'description'   => 'Mark related fiscal period as closed (and fiscal year if last period).',
                'policies'      => [],
                'function'      => 'doCloseFiscalPeriod'
            ],
            'send_expense_statements' => [
                'description'   => 'Send expense statement correspondences.',
                'policies'      => [],
                'function'      => 'doSendExpenseStatements'
            ],
            'cancel' => [
                'description'   => 'Cancel the sale invoice. No further change will be possible.',
                'help'          => 'Void the accounting entry and set status to `cancelled`. By default (optional), a credit note can be create.',
                'policies'      => [],
                'function'      => 'doCancel'
            ],
            'unlock' => [
                'description'   => 'Unlock the sale invoice, to allow re-posting after modifications.',
                'help'          => 'Self voiding accounting entries will be left as `reversed`, and invoice will be set back to `proforma`.',
                // #todo
                'policies'      => [/*can_unlock*/],
                'function'      => 'doUnlock'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'has_mandatory_data' => [
                'description' => 'Checks & validate values required for activation.',
                'function'    => 'policyHasMandatoryData'
            ],
            'can_cancel' => [
                'description' => 'Verifies that there are no invoiced executions.',
                'function'    => 'policyCanCancel'
            ],
            'can_generate_statement' => [
                'description' => 'Verifies that the allocation of a fund request can still be updated.',
                'function'    => 'policyCanGenerateStatement'
            ],
            'can_generate_accounting_entries' => [
                'description' => 'Verifies that accounting entries can be generated for the statement.',
                'function'    => 'policyCanGenerateAccountingEntries'
            ],
            'can_generate_fundings' => [
                'description' => 'Verifies that fundings can be generated for the statement.',
                'function'    => 'policyCanGenerateFundings'
            ],
            'can_assign_invoice_number' => [
                'description' => 'Verifies that an invoice number can be assigned.',
                'function'    => 'policyCanAssignInvoiceNumber'
            ],
            'can_clear_accounting_entry_lines' => [
                'description' => 'Verifies that accounting entry lines can be cleared by the statement.',
                'function'    => 'policyCanClearAccountingEntryLines'
            ],
            'can_validate_accounting_entries' => [
                'description' => 'Verifies that generated accounting entries can be validated.',
                'function'    => 'policyCanValidateAccountingEntries'
            ],
            'can_close_fiscal_period' => [
                'description' => 'Verifies that the related fiscal period can be closed.',
                'function'    => 'policyCanCloseFiscalPeriod'
            ],
            'can_generate_expense_statement_correspondences' => [
                'description' => 'Verifies that expense statement correspondences can be generated.',
                'function'    => 'policyCanGenerateExpenseStatementCorrespondences'
            ],
            'can_send_expense_statements' => [
                'description' => 'Verifies that expense statements can be sent.',
                'function'    => 'policyCanSendExpenseStatements'
            ],
            'is_valid' => [
                'description' => 'Verifies that the Expense Statement can be validated (is valid).',
                'function'    => 'policyIsValid'
            ],
            'is_balanced' => [
                'description' => 'Verifies that request amount matches allocated amount.',
                'function'    => 'policyIsBalanced'
            ]
        ]);
    }

    protected static function doCancel($self) {
        $self->read(['status', 'accounting_entry_id']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['status'] !== 'posted') {
                continue;
            }
            AccountingEntry::id($expenseStatement['accounting_entry_id'])->do('cancel');
            self::id($id)
                ->update(['status' => 'cancelled']);

            AccountingEntryLine::search(['clearing_expense_statement_id', '=', $id])
                ->update(['is_cleared' => false]);
        }
    }

    protected static function doUnlock($self) {
        $self->read(['status', 'accounting_entry_id']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['status'] !== 'posted') {
                continue;
            }
            AccountingEntry::id($expenseStatement['accounting_entry_id'])->do('cancel');
            self::id($id)
                ->update(['status' => 'proforma'])
                ->update(['accounting_entry_id' => null]);

            AccountingEntryLine::search(['clearing_expense_statement_id', '=', $id])
                ->update(['is_cleared' => false]);
        }
    }

    private static function normalizeMoneyAmount($amount): float {
        $amount = round((float) $amount, 2);
        return (abs($amount) < 0.01) ? 0.0 : $amount;
    }

    /**
     * Check that the statement is balanced: common_total + assigned_delta = sum(expense_statement_owners.expense_amount)
     */
    protected static function policyIsBalanced($self): array {
        $result = [];
        $self->read(['common_total', 'private_total', 'provisions_total', 'assigned_delta', 'statement_owners_ids' => ['expense_amount']]);
        foreach($self as $id => $expenseStatement) {
            $total_assigned = 0.0;
            foreach($expenseStatement['statement_owners_ids'] as $expense_statement_owner_id => $expenseStatementOwner) {
                $total_assigned += round($expenseStatementOwner['expense_amount'] ?? 0.0, 2);
            }
            $expected_total = round(($expenseStatement['common_total'] ?? 0.0) + ($expenseStatement['private_total'] ?? 0.0) + ($expenseStatement['provisions_total'] ?? 0.0) - ($expenseStatement['assigned_delta'] ?? 0.0), 2);
            if(round($total_assigned - $expected_total, 2) != 0.0) {
                $result[$id] = [
                    'unbalanced_allocation' => "The total assigned amount ({$total_assigned}) does not match the common total ({$expected_total})."
                ];
                continue;
            }
        }
        return $result;
    }

    protected static function policyIsValid($self): array {
        $result = [];
        $self->read(['statement_bank_account_id', 'payment_terms_id', 'condo_id', 'fiscal_period_id', 'fiscal_year_id']);
        foreach($self as $id => $expenseStatement) {
            // 1) check completeness
            if(!$expenseStatement['condo_id']) {
                $result[$id] = [
                    'missing_condominium' => 'The condominium is mandatory.'
                ];
            }
            if(!$expenseStatement['fiscal_year_id']) {
                $result[$id] = [
                    'missing_fiscal_year' => 'The fiscal year is mandatory.'
                ];
            }
            if(!$expenseStatement['fiscal_period_id']) {
                $result[$id] = [
                    'missing_fiscal_period' => 'The fiscal period is mandatory.'
                ];
            }
            if(!$expenseStatement['payment_terms_id']) {
                $result[$id] = [
                    'missing_payment_terms' => 'The payment terms are mandatory.'
                ];
            }
            if(!$expenseStatement['statement_bank_account_id']) {
                $result[$id] = [
                    'missing_bank_account' => 'The Bank Account is mandatory.'
                ];
            }
        }
        return $result;
    }

    protected static function policyCanCancel($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['status'] === 'cancelled') {
                $result[$id] = [
                    'invalid_status' => 'Already cancelled.'
                ];
                continue;
            }
        }
        return $result;
    }

    protected static function policyCanGenerateStatement($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['status'] != 'proforma') {
                $result[$id] = [
                    'invalid_status' => 'Invoiced statement cannot be re-generated.'
                ];
                continue;
            }
            // #todo - check that ownerships are set and continuous for all involved property lots of the period
        }
        return $result;
    }

    protected static function policyCanGenerateAccountingEntries($self): array {
        return [];
    }

    /**
     * Ensure every ownership involved in the statement has the required working-fund account.
     */
    protected static function policyCanGenerateFundings($self): array {
        $result = [];
        $self->read(['condo_id', 'statement_owners_ids' => ['ownership_id']]);
        foreach($self as $id => $expenseStatement) {
            foreach($expenseStatement['statement_owners_ids'] as $statement_owner_id => $statementOwner) {

                if(!$statementOwner['ownership_id']) {
                    $result[$id] = [
                        'missing_ownership' => 'A statement owner is missing its ownership reference.'
                    ];
                    continue;
                }

                $account = Account::search([
                        ['condo_id', '=', $expenseStatement['condo_id']],
                        ['ownership_id', '=', $statementOwner['ownership_id']],
                        ['operation_assignment', '=', 'co_owners_working_fund']
                    ])
                    ->first();

                if(!$account) {
                    trigger_error("APP::Missing working-fund account for ownership #{$statementOwner['ownership_id']} of statement #{$id}", EQ_REPORT_ERROR);
                    $result[$id] = [
                        "missing_working_fund_account" => "Missing working-fund account for one or more ownership."
                    ];
                }
            }
        }
        return $result;
    }

    protected static function policyCanAssignInvoiceNumber($self): array {
        return [];
    }

    protected static function policyCanClearAccountingEntryLines($self): array {
        return [];
    }

    protected static function policyCanValidateAccountingEntries($self): array {
        return [];
    }

    protected static function policyCanCloseFiscalPeriod($self): array {
        return [];
    }

    protected static function policyCanGenerateExpenseStatementCorrespondences($self): array {
        return [];
    }

    protected static function policyCanSendExpenseStatements($self): array {
        return [];
    }

    protected static function onupdateCondoId($self) {
        $self->read(['condo_id']);
        foreach($self as $id => $expenseStatement) {
            if(!$expenseStatement['condo_id']) {
                continue;
            }
            $bankAccount = CondominiumBankAccount::search([
                    ['condo_id', '=', $expenseStatement['condo_id']],
                    ['object_class', '=', 'finance\bank\CondominiumBankAccount'],
                    ['bank_account_type', '=', 'bank_current'],
                    ['is_primary', '=', true]
                ])
                ->first();
            if($bankAccount) {
                self::id($id)->update(['statement_bank_account_id' => $bankAccount['id']]);
            }
        }
    }

    protected static function onupdateFiscalPeriodId($self) {
        $self->read(['fiscal_period_id' => ['date_from', 'date_to']]);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['fiscal_period_id']) {
                self::id($id)->update(['posting_date' => $expenseStatement['fiscal_period_id']['date_to']]);
            }
        }
    }

    protected static function onupdateIsCutoffAtPeriodEnd($self) {
        $self->read(['is_cutoff_at_period_end']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['is_cutoff_at_period_end']) {
                self::id($id)->update(['is_cutoff_at_document_date' => false]);

            }
        }
    }

    protected static function onupdateIsCutoffAtDocumentDate($self) {
        $self->read(['is_cutoff_at_document_date']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['is_cutoff_at_document_date']) {
                self::id($id)->update(['is_cutoff_at_period_end' => false]);
            }
        }
    }

    protected static function policyHasMandatoryData($self): array {
        $result = [];
        $self->read(['condo_id', 'request_date', 'has_date_range', 'date_from', 'date_to', 'request_account_id', 'request_bank_account_id', 'payment_terms_id']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['has_date_range']) {
                if(!$expenseStatement['date_from']) {
                    $result[$id] = [
                        'missing_date_from' => 'The start date of the time range is mandatory.'
                    ];
                }
                if(!$expenseStatement['date_to']) {
                    $result[$id] = [
                        'missing_date_to' => 'The end date of the time range is mandatory.'
                    ];
                }
                if($expenseStatement['date_from'] > $expenseStatement['date_from']) {
                    $result[$id] = [
                        'invalid_date_interval' => 'The end date cannot be before start date.'
                    ];
                }
            }
            elseif(!$expenseStatement['request_date']) {
                $result[$id] = [
                    'missing_date' => 'The date of the request is mandatory.'
                ];
            }

            if(!$expenseStatement['condo_id']) {
                $result[$id] = [
                    'missing_condominium' => 'The condominium is mandatory.'
                ];
            }
            if(!$expenseStatement['request_account_id']) {
                $result[$id] = [
                    'missing_accounting_account' => 'The accounting account is mandatory.'
                ];
            }
            if(!$expenseStatement['request_bank_account_id']) {
                $result[$id] = [
                    'missing_bank_account' => 'The bank account is mandatory.'
                ];
            }
            if(!$expenseStatement['payment_terms_id']) {
                $result[$id] = [
                    'missing_payment_terms' => 'The payment terms are mandatory.'
                ];
            }
        }
        return $result;
    }

    protected static function onbeforeInvoice($self) {
        try {
            try {
                // generate subsequent data
                $self
                    ->do('generate_statement')
                    ->do('generate_accounting_entries');
            }
            catch(\Exception $e) {
                trigger_error("APP::Error while generating expense statement data: {$e->getMessage()}", EQ_REPORT_ERROR);
                throw $e;
            }

            try {
                // create a unique invoice number for the statement (handled as a sale invoice), based on Condo sequence
                $self->do('assign_invoice_number');
            }
            catch(\Exception $e) {
                trigger_error("APP::Error while generating number for expense statement: {$e->getMessage()}", EQ_REPORT_ERROR);
                throw $e;
            }

            try {
                $self
                    // mark involved accounting entries as cleared by the statement (to exclude them from future statements)
                    ->do('clear_accounting_entry_lines')
                    // validate accounting entries (to be considered in financial statements)
                    ->do('validate_accounting_entries')
                    // generate payment request for each ownership
                    ->do('generate_fundings')
                    // mark related fiscal period as closed (and fiscal year if last period)
                    ->do('close_fiscal_period');
            }
            catch(\Exception $e) {
                trigger_error("APP::Error while processing expense statement posting: {$e->getMessage()}", EQ_REPORT_ERROR);
                throw $e;
            }

            try {
                $self
                    // generate correspondences for each ownership
                    ->do('generate_expense_statement_correspondences')
                    // send emails to representatives of involved ownerships according to their communication preferences
                    ->do('send_expense_statements');
            }
            catch(\Exception $e) {
                trigger_error("APP::Error while generating expense statement data: {$e->getMessage()}", EQ_REPORT_ERROR);
                // #memo -do not relay exception here (non critical)
                // throw $e;
            }

        }
        catch(\Exception $e) {
            throw new \Exception('unexpected_error_at_invoicing', EQ_ERROR_UNKNOWN, $e);
        }
    }

    /**
     * Generate invites for each ownership.
     */
    protected static function doGenerateExpenseStatementCorrespondences($self) {
        $self->read(['condo_id', 'statement_owners_ids' => ['ownership_id']]);
        foreach($self as $id => $expenseStatement) {
            // remove any previously created invite
            ExpenseStatementCorrespondence::search(['expense_statement_id', '=', $id])->delete(true);

            $ownerships_ids = array_column($expenseStatement['statement_owners_ids']->get(true), 'ownership_id');
            $ownerships = Ownership::ids($ownerships_ids)->read(['representative_owner_id']);

            foreach($ownerships as $ownership_id => $ownership) {
                if(!$ownership['representative_owner_id']) {
                    continue;
                }

                // init prefs
                $communication_methods = [
                        'email'                     => false,
                        'postal'                    => false,
                        'postal_registered'         => false,
                        'postal_registered_receipt' => false
                    ];

                // fetch Ownership communication preferences
                $communicationPreference = OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $expenseStatement['condo_id']],
                        ['ownership_id', '=', $ownership_id],
                        ['communication_reason', '=', 'expense_statement']
                    ])
                    ->read([
                        'has_channel_email',
                        'has_channel_postal',
                        'has_channel_postal_registered',
                        'has_channel_postal_registered_receipt'
                    ])
                    ->first();

                if($communicationPreference) {
                    $communication_methods = [
                            'email'                     => $communicationPreference['has_channel_email'],
                            'postal'                    => $communicationPreference['has_channel_postal'],
                            'postal_registered'         => $communicationPreference['has_channel_postal_registered'],
                            'postal_registered_receipt' => $communicationPreference['has_channel_postal_registered_receipt']
                        ];
                }

                // if not requested otherwise, invite must be sent through registered postal mail
                if(!in_array(true, $communication_methods, true)) {
                    $communication_methods['postal_registered'] = true;
                }

                foreach($communication_methods as $communication_method => $communication_method_flag) {
                    if(!$communication_method_flag) {
                        continue;
                    }

                    ExpenseStatementCorrespondence::create([
                        'condo_id'                  => $expenseStatement['condo_id'],
                        'expense_statement_id'      => $id,
                        'ownership_id'              => $ownership_id,
                        'owner_id'                  => $ownership['representative_owner_id'],
                        'communication_method'      => $communication_method
                    ]);
                }
            }
        }
    }


    protected static function doSendExpenseStatements($self, $cron) {
        $self->read([
            'name',
            'condo_id',
            'statements_exporting_task_id',
            'expense_statement_correspondences_ids' => ['communication_method']
        ]);

        foreach($self as $id => $expenseStatement) {

            // remove previously created exporting task (and lines), if any
            if($expenseStatement['statements_exporting_task_id']) {
                ExportingTask::id($expenseStatement['statements_exporting_task_id'])->delete(true);
            }

            $map_communication_methods = [];

            foreach($expenseStatement['expense_statement_correspondences_ids'] as $fundRequestCorrespondence) {
                // update global map to acknowledge that at least one invitation uses that communication method
                $map_communication_methods[$fundRequestCorrespondence['communication_method']] = true;
            }

            if(isset($map_communication_methods['email'])) {
                // schedule queuing of invite emails
                $cron->schedule(
                    "realestate.expensestatement.send-statements.{$id}",
                    time() + (5 * 60),
                    'realestate_funding_ExpenseStatement_send-statements',
                    [
                        'id'  => $id
                    ]
                );
            }

            // handle non-digital communication methods
            if(count(array_diff(array_keys($map_communication_methods), ['email'])) > 0) {

                // schedule generation of a zip archive containing printable documents
                $exportingTask = ExportingTask::create([
                        'name'          => "{$expenseStatement['name']} - Export des courriers du décompte de charges",
                        'condo_id'      => $expenseStatement['condo_id'],
                        'object_class'  => static::class,
                        'object_id'     => $id
                    ])
                    ->first();

                foreach($map_communication_methods as $communication_method => $flag) {
                    if($communication_method === 'email') {
                        continue;
                    }
                    ExportingTaskLine::create([
                            'exporting_task_id' => $exportingTask['id'],
                            'name'              => "{$expenseStatement['name']} - Export du décompte de charges - {$communication_method}",
                            'controller'        => 'realestate_funding_ExpenseStatement_export-statements',
                            'params'            => json_encode([
                                    'id'                    => $id,
                                    'communication_method'  => $communication_method
                                ])
                        ]);
                }

                self::id($id)->update([
                        'statements_exporting_task_id' => $exportingTask['id']
                    ]);
            }
        }

    }

    /**
     * Generate the ExpenseStatementOwner and ExpenseStatementOwnerLine objects based on accounting entries of the period.
     *
     */
    protected static function doGenerateStatement($self) {
        $self->read(['condo_id', 'fiscal_period_id']);
        foreach($self as $id => $expenseStatement) {
            // remove any previously created owner statement
            ExpenseStatementOwner::search(['expense_statement_id', '=', $id])->delete(true);
            // remove annex documents, if any
            Document::search([
                    ['condo_id', '=', $expenseStatement['condo_id']],
                    ['expense_statement_id', '=', $id]
                ])
                ->delete(true);

            $data = self::computeExpenseStatementData($expenseStatement['fiscal_period_id']);
            self::id($id)
                ->update([
                    'provisions_total'  => self::normalizeMoneyAmount($data['provisions_total']),
                    'private_total'     => self::normalizeMoneyAmount($data['private_total']),
                    'common_total'      => self::normalizeMoneyAmount($data['common_total']),
                    'assigned_delta'    => self::normalizeMoneyAmount($data['assigned_delta'])
                ]);

            // temporarily link involved records with current statement (has no implication while statement is not posted and `is_cleared` is not set to true)
            AccountingEntryLine::ids($data['accounting_entry_lines_ids'])->update(['clearing_expense_statement_id' => $id]);

            foreach($data['owners'] as $owner) {

                $statementOwner = ExpenseStatementOwner::create([
                        'condo_id'              => $expenseStatement['condo_id'],
                        'fiscal_period_id'      => $expenseStatement['fiscal_period_id'],
                        'expense_statement_id'  => $id,
                        'ownership_id'          => $owner['id'],
                        'nb_days'               => $owner['nb_days'],
                        'date_from'             => $owner['date_from'],
                        'date_to'               => $owner['date_to']
                    ])
                    ->first();

                foreach($owner['lines'] as $line) {
                    // these are invoice lines
                    ExpenseStatementOwnerLine::create([
                            'condo_id'              => $expenseStatement['condo_id'],
                            'statement_owner_id'    => $statementOwner['id'],
                            'invoice_id'            => $id,
                            'ownership_id'          => $owner['id'],
                            'description'           => $line['description'],
                            'apportionment_id'      => $line['apportionment_id'],
                            'account_id'            => $line['account_id'],
                            'property_lot_id'       => $line['property_lot_id'],
                            'total_amount'          => self::normalizeMoneyAmount($line['total_amount'] ?? 0.0),
                            'owner_amount'          => self::normalizeMoneyAmount($line['owner'] ?? 0.0),
                            'tenant_amount'         => self::normalizeMoneyAmount($line['tenant'] ?? 0.0),
                            'vat_amount'            => self::normalizeMoneyAmount($line['vat'] ?? 0.0),
                            'assigned_delta'        => self::normalizeMoneyAmount($line['assigned_delta'] ?? 0.0),
                            'date'                  => $line['date'] ?? null,
                            'expense_type'          => $line['expense_type'],
                            'shares'                => $line['shares'] ?? null,
                            'date_from'             => $line['date_from'] ?? null,
                            'date_to'               => $line['date_to'] ?? null,
                            'nb_days'               => $line['nb_days'] ?? null,
                        ]);
                }

            }
        }
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
                'condo_id',
                'assigned_delta',
                'fiscal_year_id',
                'fiscal_period_id' => ['name', 'date_to'],
                'statement_owners_ids' => [
                    'ownership_id',
                    'statement_owner_lines_ids' => [
                        'name',
                        'expense_type',
                        'price',
                        'account_id',
                        'ownership_id'
                    ]
                ]
            ]);

        foreach($self as $id => $statement) {

            // remove any existing accounting entry (this should not occur, just in case of a previous unfinished action)
            AccountingEntry::search([
                    ['sale_invoice_id', '=', $id],
                    ['status', '=', 'pending']
                ])
                ->delete(true);

            // retrieve journal dedicated to purchases
            $journal = Journal::search([['condo_id', '=', $statement['condo_id']], ['journal_type', '=', 'SALE']])->first();
            if(!$journal) {
                trigger_error("APP::unable to find a match for journal PUR for condominium {$statement['condo_id']}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_journal", EQ_ERROR_INVALID_CONFIG);
            }

            // #todo - allow customization of label
            $description = 'Décompte de charge ' . $statement['fiscal_period_id']['name'];

            // create the accounting entry for the Expense Statement
            $accountingEntry = AccountingEntry::create([
                    'condo_id'              => $statement['condo_id'],
                    'journal_id'            => $journal['id'],
                    'fiscal_year_id'        => $statement['fiscal_year_id'],
                    'fiscal_period_id'      => $statement['fiscal_period_id']['id'],
                    // #memo - if necessary, entry_date will be reassigned based on selected fiscal year and matching period (so that dates remain in ascending order)
                    'entry_date'            => $statement['fiscal_period_id']['date_to'],
                    'origin_object_class'   => self::getType(),
                    'origin_object_id'      => $id,
                    'sale_invoice_id'       => $id,
                    'expense_statement_id'  => $id,
                    'description'           => $description
                ])
                ->first();

            // aggregate accounting entry lines by account
            $linesByAccount = [];

            // handle assigned delta (rounding adjustment), if any
            $assigned_delta = round($statement['assigned_delta'], 2);
            if($assigned_delta != 0.0) {
                // find the account based on operation_assignment
                $roundingAccount = Account::search([
                        ['condo_id', '=', $statement['condo_id']],
                        ['operation_assignment', '=', 'rounding_adjustment']
                    ])
                    ->first();

                if(!$roundingAccount) {
                    throw new \Exception('missing_rounding_account', EQ_ERROR_INVALID_CONFIG);
                }

                $account_id = $roundingAccount['id'];
                if(!isset($linesByAccount[$account_id])) {
                    $linesByAccount[$account_id] = ['debit' => 0.0, 'credit' => 0.0];
                }

                if($assigned_delta > 0.0) {
                    $linesByAccount[$account_id]['debit'] += abs($assigned_delta);
                }
                else {
                    $linesByAccount[$account_id]['credit'] += abs($assigned_delta);
                }
            }

            // retrieve accounting entry lines cleared by the expense statement
            $accountingEntryLines = AccountingEntryLine::search([
                    ['clearing_expense_statement_id', '=', $id]
                ])
                ->read(['account_id', 'debit', 'credit']);

            // reverse cleared lines and aggregate by account
            foreach($accountingEntryLines as $accountingEntryLine) {
                $account_id = $accountingEntryLine['account_id'];

                if(!isset($linesByAccount[$account_id])) {
                    $linesByAccount[$account_id] = ['debit' => 0.0, 'credit' => 0.0];
                }

                $linesByAccount[$account_id]['debit']  += $accountingEntryLine['credit'];
                $linesByAccount[$account_id]['credit'] += $accountingEntryLine['debit'];
            }

            // handle ownership debit lines
            foreach($statement['statement_owners_ids'] as $statementOwner) {

                // sum of field `price`, to be accounted on ownership debit
                $total_ownership = 0.0;
                foreach($statementOwner['statement_owner_lines_ids'] as $statementLine) {
                    $total_ownership += $statementLine['price'];
                }

                if(round($total_ownership, 2) == 0.0) {
                    continue;
                }

                $ownershipAccount = Account::search([
                        ['condo_id', '=', $statement['condo_id']],
                        ['ownership_id', '=', $statementOwner['ownership_id']],
                        ['operation_assignment', '=', 'co_owners_working_fund']
                    ])
                    ->first();

                if(!$ownershipAccount) {
                    throw new \Exception('missing_ownership_accounting_account', EQ_ERROR_INVALID_PARAM);
                }

                $account_id = $ownershipAccount['id'];
                if(!isset($linesByAccount[$account_id])) {
                    $linesByAccount[$account_id] = ['debit' => 0.0, 'credit' => 0.0];
                }

                $linesByAccount[$account_id]['debit'] += round($total_ownership, 2);
            }

            // create final aggregated accounting entry lines (one per account)
            foreach($linesByAccount as $account_id => $amounts) {

                $debit  = round($amounts['debit'], 2);
                $credit = round($amounts['credit'], 2);

                if($debit > $credit) {
                    $debit  = $debit - $credit;
                    $credit = 0.0;
                }
                elseif($credit > $debit) {
                    $credit = $credit - $debit;
                    $debit  = 0.0;
                }
                else {
                    // perfectly balanced, nothing to write
                    continue;
                }

                AccountingEntryLine::create([
                        'condo_id'              => $statement['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'description'           => $description,
                        'account_id'            => $account_id,
                        'debit'                 => $debit,
                        'credit'                => $credit
                    ]);
            }

            // link accounting entry to expense statement
            self::id($id)->update([
                    'accounting_entry_id' => $accountingEntry['id']
                ]);
        }
    }

    protected static function doGenerateFundings($self) {


        $self->read([
                'name',
                'posting_date',
                'date_to',
                'due_date',
                'statement_bank_account_id',
                'is_cutoff_at_period_end',
                'fiscal_year_id' => ['date_from'],
                'fiscal_period_id' => ['date_from', 'date_to'],
                'condo_id' => ['code'],
                'statement_owners_ids' => [
                    'ownership_id' => ['code'],
                    'statement_owner_lines_ids' => [
                        'price'
                    ]
                ]
            ]);

        foreach($self as $id => $expenseStatement) {

            foreach($expenseStatement['statement_owners_ids'] as $statement_owner_id => $statementOwner) {
                $ownership_id = $statementOwner['ownership_id']['id'];
                // a funding cannot be issued nor due in the past
                $issue_date = max(strtotime('today'), $expenseStatement['posting_date']);
                $due_date = $expenseStatement['due_date'];

                // remove previous Funding, if any
                Funding::search([
                        ['condo_id', '=', $expenseStatement['condo_id']['id']],
                        ['funding_type', '=', 'expense_statement'],
                        ['expense_statement_id', '=', $id],
                        ['ownership_id', '=', $ownership_id]
                    ])
                    ->delete(true);

                // #todo
                $date_to = $expenseStatement['posting_date'];
                if($expenseStatement['is_cutoff_at_period_end']) {
                    $date_to = $expenseStatement['date_to'];
                }

                $data = \eQual::run('get', 'finance_accounting_ownerAccountStatement_collect', [
                    'ownership_id'      => $ownership_id,
                    'date_from'         => $expenseStatement['fiscal_period_id']['date_from'],
                    'date_to'           => $expenseStatement['fiscal_period_id']['date_to']
                ]);

                $closing_balance = 0;

                if(count($data)) {
                    $closing_balance = end($data)['balance'] ?? 0;
                }

                $ownershipAccount = Account::search([
                        ['condo_id', '=', $expenseStatement['condo_id']['id']],
                        ['ownership_id', '=', $ownership_id],
                        ['operation_assignment', '=', 'co_owners_working_fund']
                    ])
                    ->first();

                if(!$ownershipAccount) {
                    throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                }

                Funding::create([
                        'condo_id'                          => $expenseStatement['condo_id']['id'],
                        'description'                       => $expenseStatement['name'],
                        'funding_type'                      => 'expense_statement',
                        'expense_statement_id'              => $id,
                        'ownership_id'                      => $ownership_id,
                        'bank_account_id'                   => $expenseStatement['statement_bank_account_id'],
                        'accounting_account_id'             => $ownershipAccount['id'],
                        'issue_date'                        => $issue_date,
                        'due_date'                          => $due_date,
                        'due_amount'                        => $closing_balance
                    ]);

            }
        }
    }


/*
    protected static function doGenerateFundings($self) {


        $self->read([
                'name',
                'posting_date',
                'due_date',
                'statement_bank_account_id',
                'fiscal_year_id' => ['date_from'],
                'fiscal_period_id' => ['date_from'],
                'condo_id' => ['code'],
                'statement_owners_ids' => [
                    'ownership_id' => ['code'],
                    'statement_owner_lines_ids' => [
                        'price'
                    ]
                ]
            ]);

        foreach($self as $id => $expenseStatement) {
            // #todo - supprimer les funding déjà existant si'il y en a
            foreach($expenseStatement['statement_owners_ids'] as $statement_owner_id => $statementOwner) {


                // Funding::search([
                //         ['condo_id', '=', $expenseStatement['condo_id']['id']],
                //         ['funding_type', '=', 'expense_statement'],
                //         ['expense_statement_id', '=', $id],
                //         ['ownership_id', '=', $ownership_id]
                //     ])
                //     ->delete(true);


                $ownership_id = $statementOwner['ownership_id']['id'];

                $due_amount = 0.0;
                foreach($statementOwner['statement_owner_lines_ids'] as $line_id => $ownerLine) {
                    // use both positive and negative amounts
                    $due_amount += $ownerLine['price'];
                }

                $ownershipAccount = Account::search([
                        ['condo_id', '=', $expenseStatement['condo_id']['id']],
                        ['ownership_id', '=', $ownership_id],
                        ['operation_assignment', '=', 'co_owners_working_fund']
                    ])
                    ->first();

                if(!$ownershipAccount) {
                    throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                }

                // a funding cannot be issued nor due in the past
                $issue_date = max(strtotime('today'), $expenseStatement['posting_date']);
                $due_date = $expenseStatement['due_date'];

                Funding::create([
                        'condo_id'                          => $expenseStatement['condo_id']['id'],
                        'description'                       => $expenseStatement['name'],
                        'funding_type'                      => 'expense_statement',
                        'expense_statement_id'              => $id,
                        'ownership_id'                      => $ownership_id,
                        'bank_account_id'                   => $expenseStatement['statement_bank_account_id'],
                        'accounting_account_id'             => $ownershipAccount['id'],
                        'issue_date'                        => $issue_date,
                        'due_date'                          => $due_date,
                        'due_amount'                        => $due_amount
                    ]);

            }
        }
    }
*/
    protected static function doClearAccountingEntryLines($self) {
        foreach($self as $id => $expenseStatement) {
            AccountingEntryLine::search(['clearing_expense_statement_id', '=', $id])
                ->update(['is_cleared' => true]);
        }
    }

    /**
     * Mark related fiscal period as closed (and fiscal year if last period).
     */
    protected static function doCloseFiscalPeriod($self) {
        $self->read(['fiscal_period_id' => ['date_to'], 'fiscal_year_id' => ['date_to']]);
        foreach($self as $id => $expenseStatement) {
            FiscalPeriod::id($expenseStatement['fiscal_period_id']['id'])->transition('close');
            if($expenseStatement['fiscal_period_id']['date_to'] === $expenseStatement['fiscal_year_id']['date_to']) {
                FiscalYear::id($expenseStatement['fiscal_year_id']['id'])->transition('close');
            }
        }
    }

    public static function onchange($event, $values): array {
        $result = [];
        if(isset($event['fiscal_period_id'])) {
            $fiscalPeriod = FiscalPeriod::id($event['fiscal_period_id'])->read(['date_to'])->first();
            if($fiscalPeriod) {
                $result['posting_date'] = $fiscalPeriod['date_to'];
            }
        }
        if(isset($event['is_cutoff_at_period_end'])) {
            if($event['is_cutoff_at_period_end']) {
                $result['is_cutoff_at_document_date'] = false;
            }
            else {
                $result['is_cutoff_at_document_date'] = true;
            }
        }
        if(isset($event['is_cutoff_at_document_date'])) {
            if($event['is_cutoff_at_document_date']) {
                $result['is_cutoff_at_period_end'] = false;
            }
            else {
                $result['is_cutoff_at_period_end'] = true;
            }
        }
        return $result;
    }

    /**
     * Fetch all required data to generate a 2 levels linearized structures [owners, lines].
     *
     * This result is meant to be used to generate ExpenseStatementOwner and ExpenseStatementOwnerLines before the Statement is invoiced.
     * Afterward, the consistency might be broken (in case of reopening of a fiscal period and changes impacting the expenses),
     * so ExpenseStatementOwner and ExpenseStatementOwnerLines will be the only source for generating `schema` and dependent documents.
     *
     * Build a resulting map with the following hierarchy:
     *  ownership > property_lot > {expense type} > apportionment > account > {share}
     *
     *  - {expense type} : is based on on the code of the account associated to each accounting entry line, and can be amongst these: 'provisions', 'private_expense', 'common_expense' ('reserve_fund' is merged with 'common_expense')
     *  - {share} : there are always two keys: 'owner' and 'tenant'. For reserve_fund, owner is always 100.
     *  - apportionment : for private expense, we usa a fake apportionment ('0'), so that the structure remains the same in all situations.
     *
     */
    private static function computeExpenseStatementData($fiscal_period_id) {
        $result = [];

        $fiscalPeriod = FiscalPeriod::id($fiscal_period_id)
            ->read(['condo_id', 'date_from', 'date_to'])
            ->first();

        if(!$fiscalPeriod) {
            throw new \Exception('unknown_period', EQ_ERROR_INVALID_PARAM);
        }

        // compute number of calendar days within the period
        $nb_days = round(($fiscalPeriod['date_to'] - $fiscalPeriod['date_from']) / 86400, 0) + 1;

        // #todo - il y a la notion de lots groupés - faire une map, par propriétaire, par lot :
        // on peut le faire par groupe de lots (si un lot est marqué avec primary_lot_id, il peut être ignoré pour les calculs)

        // #memo - fetch relevant accounting entries that apply to the chosen period
        //    * comptabiliser toutes les entrées comptables des comptes 6 et 7, quel que soit le journal
        //    * marquer les écritures comme "décomptées"
        $accountingEntryLines = AccountingEntryLine::search([
                ['fiscal_period_id', '=', $fiscal_period_id],
                // #memo - reversed entries are ignored since they are symmetrical (both entries are linked through reversed_entry_id field and sum to 0)
                ['status', '=', 'validated'],
                ['is_cleared', '=', false],
                // #memo - deprecated
                // ['is_visible', '=', true],
                ['is_carry_forward', '=', false],
                ['account_class', 'in', [6, 7]]
            ])
            ->read([
                'accounting_entry_id',
                'account_id', 'account_code', 'account_operation_assignment', 'debit', 'credit',
                'ownership_id',
                'sale_invoice_line_id',
                // #memo - we need this to retrieve details for private expenses
                'purchase_invoice_line_id',
                'bank_statement_line_id',
                'misc_operation_line_id',
                'fund_usage_line_id'
            ]);

        $map_accounting_entries_ids = [];
        foreach($accountingEntryLines as $accountingEntryLine) {
            $map_accounting_entries_ids[$accountingEntryLine['accounting_entry_id']] = true;
        }

        $accountingEntries = AccountingEntry::ids(array_keys($map_accounting_entries_ids))
            ->read([
                'entry_date',
                'status',
                'journal_id',
                // #memo - we need this to discard records from expense statements
                'expense_statement_id',
                // #memo - we need this to reject entry lines relating to fund requests global amounts
                'fund_request_execution_id'
            ])
            ->get();

        /*
            Prefetch required objects (condominium configuration)
        */

        // retrieve all ownerships of given Condo, whatever their history
        $ownerships = Ownership::search(['condo_id', '=', $fiscalPeriod['condo_id']])
            ->read(['name', 'date_from', 'date_to', 'property_lot_ownerships_ids' => ['property_lot_id', 'date_from', 'date_to']])
            ->get();

        // compute nb_days of Ownership to apply prorata
        // #memo - we assume ownerships remain consistent and that a property lot is always owned by someone (for a same property lot, sum of ownerships nb_days matches the nb_days of the period)
        // #memo - this can be adapted below if invoice line was encoded to map a specific time interval
        foreach($ownerships as $ownership_id => $ownership) {
            $start = max($fiscalPeriod['date_from'], $ownership['date_from'] ?? $fiscalPeriod['date_from']);
            $end   = min($fiscalPeriod['date_to'], $ownership['date_to'] ?? $fiscalPeriod['date_to']);
            $ownerships[$ownership_id]['nb_days']   = ($start <= $end) ? (($end-$start)/86400 + 1) : 0;
            $ownerships[$ownership_id]['date_from'] = $start;
            $ownerships[$ownership_id]['date_to']   = $end;

            foreach($ownerships[$ownership_id]['property_lot_ownerships_ids'] as $property_lot_ownership_id => $propertyLotOwnership) {
                $start = max($fiscalPeriod['date_from'], $propertyLotOwnership['date_from'] ?? $fiscalPeriod['date_from']);
                $end   = min($fiscalPeriod['date_to'], $propertyLotOwnership['date_to'] ?? $fiscalPeriod['date_to']);
                $ownerships[$ownership_id]['property_lots'][$propertyLotOwnership['property_lot_id']] = [
                    'nb_days'    => ($start <= $end) ? (($end-$start)/86400 + 1) : 0,
                    'date_from'  => $start,
                    'date_to'    => $end
                ];
            }
        }

        // retrieve applicable reserve funds
        $reserveFunds = CondoFund::search([
                ['condo_id', '=', $fiscalPeriod['condo_id']],
                ['fund_type', '=', 'reserve_fund']
            ])
            ->read(['expense_account_code', 'fund_account_id', 'expense_account_id', 'apportionment_id']);
        $map_reserve_funds = [];
        foreach($reserveFunds as $reserve_fund_id => $reserveFund) {
            $map_reserve_funds[$reserveFund['expense_account_code']] = $reserveFund;
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

        $map_result = [];

        // We need to keep track of the delta between the total entries and the actual distributed total (on which rounding operations are applied)

        /**
         * @var float $common_total
         * Amount that has been splitted in the current statement, without deferred expenses and deducing reserve funds usage, cumulating non-rounded values.
         */
        $common_total = 0.0;
        /**
         * @var float $private_total
         * Total amount of private expenses in the current statement (all owners included).
         */
        $private_total = 0.0;
        /**
         * @var float $provisions_total
         * Total amount of provisions in the current statement (all owners included).
         */
        $provisions_total = 0.0;
        /**
         * @var float $delta_total
         * Total of diffs between line amounts and assigned amounts, considering deferred expenses, cumulating rounded values.
         * This value is used for computing assigned_delta.
         */
        $delta_total = 0.0;

        // pass-1 - identify private expenses that have been reinvoiced
        $map_private_expenses = [];

        foreach($accountingEntryLines as $accountingEntryLine) {
            if(substr($accountingEntryLine['account_code'], 0, 3) === '643' && round($accountingEntryLine['credit'], 2) > 0 && $accountingEntryLine['sale_invoice_line_id']) {
                $map_private_expenses[$accountingEntryLine['id']] = true;
            }
        }

        // pass-2 - handle all expenses
        $map_accounting_entry_lines_ids = [];

        foreach($accountingEntryLines as $accountingEntryLine) {

            $accountingEntry = $accountingEntries[$accountingEntryLine['accounting_entry_id']];

            // ignore accounting entries not yet validated
            if($accountingEntry['status'] !== 'validated') {
                continue;
            }

            // ignore accounting entries already cleared by an expense statement
            if($accountingEntry['expense_statement_id']) {
                continue;
            }

            // ignore out of range accounting entries
            if($accountingEntry['entry_date'] < $fiscalPeriod['date_from'] || $accountingEntry['entry_date'] > $fiscalPeriod['date_to']) {
                continue;
            }

            $map_accounting_entry_lines_ids[$accountingEntryLine['id']] = true;

            // 1) provisions (fund requests with request_type=expense_provisions,work_provisions ; account 70x)
            if($accountingEntry['fund_request_execution_id']) {

                // consider only provisions
                if(!in_array($accountingEntryLine['account_operation_assignment'], ['expense_provisions', 'work_provisions'], true)) {
                    trigger_error("APP::skipping accounting entry line {$accountingEntryLine['id']} relating to non-provision fund request", EQ_REPORT_ERROR);
                    continue;
                }

                // we must take into account accounting entries on co-owners' 401xxx accounts
                $subAccountingEntryLines = AccountingEntryLine::search([
                        ['accounting_entry_id', '=', $accountingEntryLine['accounting_entry_id']],
                        ['account_class', '=', 4]
                    ])
                    ->read(['sale_invoice_line_id']);

                foreach($subAccountingEntryLines as $sub_accounting_entry_line_id => $subAccountingEntryLine) {

                    // #memo - ownership accounts are handled at accounting entry generation
                    // $map_accounting_entry_lines_ids[$subAccountingEntryLine['id']] = true;

                    $sourceLine = FundRequestExecutionLine::id($subAccountingEntryLine['sale_invoice_line_id'])
                        ->read([
                            'name',
                            'execution_line_entries_ids' => ['ownership_id', 'property_lot_id', 'called_amount'],
                            'invoice_id' => ['posting_date']
                        ])
                        ->first();

                    $posting_date = $sourceLine['invoice_id']['posting_date'] ?? null;

                    foreach($sourceLine['execution_line_entries_ids'] as $execution_line_entry_id => $executionLineEntry) {
                        $ownership_id = $executionLineEntry['ownership_id'];
                        $property_lot_id = $executionLineEntry['property_lot_id'];

                        $amount = -$executionLineEntry['called_amount'];

                        $provisions_total += $amount;

                        if(!isset($map_result[$ownership_id][$property_lot_id]['provisions'][0][$accountingEntryLine['account_id']])) {
                            $map_result[$ownership_id][$property_lot_id]['provisions'][0][$accountingEntryLine['account_id']] = [
                                'owner'         => 0.0,
                                'tenant'        => 0.0,
                                'vat'           => 0.0,
                                'description'   => $sourceLine['name'],
                                // type : "provisions"
                                'date'          => $posting_date
                            ];
                        }

                        $map_result[$ownership_id][$property_lot_id]['provisions'][0][$accountingEntryLine['account_id']]['owner'] += round($amount, 2);

                        $map_accounts_ids[$accountingEntryLine['account_id']] = true;
                        $map_property_lots_ids[$property_lot_id] = true;
                    }
                }

            }

            // 2) private expense (relates to a purchase invoice line or a bank statement line)
            /*
            Encodage des factures sur le compte correspondant à l'énergie consommée 61200
              + utilisation d'un compte dédié au décomptes de consommation (compteur) 61240
              + création d'un total consommations privatives
            */
            // #memo - consider both debit and credit lines here (to void already reinvoiced private expenses)
            elseif(substr($accountingEntryLine['account_code'], 0, 3) === '643') {

                // skip private expense that have been reinvoiced
                if(isset($map_private_expenses[$accountingEntryLine['id']])) {
                    continue;
                }

                if(isset($accountingEntryLine['purchase_invoice_line_id'])) {
                    $sourceLine = PurchaseInvoiceLine::id($accountingEntryLine['purchase_invoice_line_id'])
                        ->read([
                            'apportionment_id', 'description', 'vat_rate', 'owner_share', 'tenant_share', 'ownership_id', 'property_lot_id',
                            'invoice_id' => ['posting_date', 'has_date_range', 'date_from', 'date_to']
                        ])
                        ->first();
                    // retrieve date_from and date_to from purchase invoice line, to determine nb_days
                    if($sourceLine['invoice_id']['has_date_range']) {
                        $start = max($sourceLine['invoice_id']['date_from'], $ownerships[$sourceLine['ownership_id']]['date_from'] ?? $sourceLine['invoice_id']['date_from']);
                        $end   = min($sourceLine['invoice_id']['date_to'], $ownerships[$sourceLine['ownership_id']]['date_to'] ?? $sourceLine['invoice_id']['date_to']);
                    }
                    else {
                        $start = max($sourceLine['invoice_id']['posting_date'], $ownerships[$sourceLine['ownership_id']]['date_from'] ?? $sourceLine['invoice_id']['posting_date']);
                        $end   = min($sourceLine['invoice_id']['posting_date'], $ownerships[$sourceLine['ownership_id']]['date_to'] ?? $sourceLine['invoice_id']['posting_date']);
                    }

                    $posting_date = $sourceLine['invoice_id']['posting_date'] ?? null;
                }
                elseif(isset($accountingEntryLine['bank_statement_line_id'])) {
                    $sourceLine = BankStatementLine::id($accountingEntryLine['bank_statement_line_id'])
                        ->read([
                            'apportionment_id', 'description', 'vat_rate', 'owner_share', 'tenant_share', 'ownership_id', 'property_lot_id',
                            'bank_statement_id' => ['date']
                        ])
                        ->first();

                    if(!$sourceLine) {
                        throw new \Exception('missing_mandatory_bank_statement_line', EQ_ERROR_INVALID_CONFIG);
                    }

                    $posting_date = $sourceLine['bank_statement_id']['date'] ?? null;
                    $start = $posting_date;
                    $end = $posting_date;
                }
                elseif(isset($accountingEntryLine['misc_operation_line_id'])) {
                    $sourceLine = MiscOperationLine::id($accountingEntryLine['misc_operation_line_id'])
                        ->read([
                            'apportionment_id', 'description', 'vat_rate', 'owner_share', 'tenant_share', 'ownership_id', 'property_lot_id',
                            'misc_operation_id' => ['posting_date', 'has_date_range', 'date_from', 'date_to']
                        ])
                        ->first();

                    if(!$sourceLine) {
                        throw new \Exception('missing_mandatory_misc_operation_line', EQ_ERROR_INVALID_CONFIG);
                    }

                    // retrieve date_from and date_to from purchase invoice line, to determine nb_days
                    if($sourceLine['misc_operation_id']['has_date_range']) {
                        $start = max($sourceLine['misc_operation_id']['date_from'], $ownerships[$sourceLine['ownership_id']]['date_from'] ?? $sourceLine['misc_operation_id']['date_from']);
                        $end   = min($sourceLine['misc_operation_id']['date_to'], $ownerships[$sourceLine['ownership_id']]['date_to'] ?? $sourceLine['misc_operation_id']['date_to']);
                    }
                    else {
                        $start = max($sourceLine['misc_operation_id']['posting_date'], $ownerships[$sourceLine['ownership_id']]['date_from'] ?? $sourceLine['misc_operation_id']['posting_date']);
                        $end   = min($sourceLine['misc_operation_id']['posting_date'], $ownerships[$sourceLine['ownership_id']]['date_to'] ?? $sourceLine['misc_operation_id']['posting_date']);
                    }

                    $posting_date = $sourceLine['misc_operation_id']['posting_date'] ?? null;
                }
                else {
                    throw new \Exception('missing_mandatory_source_line', EQ_ERROR_INVALID_CONFIG);
                }

                $ownership_id = $sourceLine['ownership_id'];
                $property_lot_id = $sourceLine['property_lot_id'];

                $amount = ($accountingEntryLine['debit'] > 0) ? $accountingEntryLine['debit'] : -$accountingEntryLine['credit'];

                $private_total += $amount;

                if(!isset($map_result[$ownership_id][$property_lot_id]['private_expense'][0][$accountingEntryLine['account_id']])) {
                    $map_result[$ownership_id][$property_lot_id]['private_expense'][0][$accountingEntryLine['account_id']] = [];
                }

                $amount_vat = round($amount * $sourceLine['vat_rate'], 2);
                $amount_owner  = round($amount * ($sourceLine['owner_share'] / 100), 2);
                $amount_tenant = round($amount * (1 - $sourceLine['owner_share'] / 100), 2);
                $adjust = round($amount - $amount_owner - $amount_tenant, 2);
                $amount_owner += $adjust;

                $amount_tenant = round($amount - $amount_owner, 2);

                $map_result[$ownership_id][$property_lot_id]['private_expense'][0][$accountingEntryLine['account_id']][] = [
                        'owner'         => $amount_owner,
                        'tenant'        => $amount_tenant,
                        'vat'           => $amount_vat,
                        'description'   => $sourceLine['description'],
                        // type : "private_expense" / "consumption"
                        'date'          => $posting_date
                    ];

                $map_accounts_ids[$accountingEntryLine['account_id']] = true;
                $map_property_lots_ids[$property_lot_id] = true;
            }

            // 3) reserve fund
            // #memo - limit to lines related to use of reserve fund
            // #todo - change to 6813 ?
            // #todo - check for fund_usage_line_id assignment instead
            elseif(substr($accountingEntryLine['account_code'], 0, 4) === '6816') {

                // retrieve account according to account_id and ReserveFund
                $reserveFund = $map_reserve_funds[$accountingEntryLine['account_code']] ?? null;
                if(!$reserveFund) {
                    trigger_error("APP::unable to retrieve reserve fund with code {$accountingEntryLine['account_code']}", EQ_REPORT_ERROR);
                    throw new \Exception('missing_mandatory_reserve_fund', EQ_ERROR_INVALID_CONFIG);
                }

                $line_amount = ($accountingEntryLine['credit'] > 0) ? -$accountingEntryLine['credit'] : $accountingEntryLine['debit'];

                // #memo - reserve fund usage is considered as 'common_expense'
                $common_total += $line_amount;

                $apportionment_id = $reserveFund['apportionment_id'];
                $apportionment = $map_apportionments[$apportionment_id];

                foreach($ownerships as $ownership_id => $ownership) {
                    foreach($ownership['property_lots'] as $property_lot_id => $propertyLotOwnership) {

                        if($propertyLotOwnership['date_to'] && $propertyLotOwnership['date_to'] < $fiscalPeriod['date_from']) {
                            continue;
                        }
                        if(!isset($apportionment[$property_lot_id])) {
                            continue;
                        }

                        $prorata = $propertyLotOwnership['nb_days'] / $nb_days;
                        $shares = $apportionment[$property_lot_id];
                        $total_shares = $apportionments[$apportionment_id]['total_shares'];

                        $amount = $prorata * ($line_amount * $shares / $total_shares);

                        $amount_owner = round($amount, 2);
                        $adjust = round($amount, 2) - $amount_owner;
                        $amount_owner += $adjust;
                        // add up the delta (cents to reinvoice later): if delta is < 0, it will be reimbursed at some point
                        $delta_total += $amount - $amount_owner;

                        if(!isset($map_result[$ownership_id][$property_lot_id]['common_expense'][$apportionment_id][$accountingEntryLine['account_id']])) {
                            $map_result[$ownership_id][$property_lot_id]['common_expense'][$apportionment_id][$accountingEntryLine['account_id']] = [
                                    'shares'        => $shares,
                                    'total_shares'  => $total_shares,
                                    'total_amount'  => $line_amount,
                                    'owner'         => $amount_owner,
                                    'tenant'        => 0.0
                                ];
                        }
                        else {
                            // use of reserve fund only applies to the owners
                            $map_result[$ownership_id][$property_lot_id]['common_expense'][$apportionment_id][$accountingEntryLine['account_id']]['owner'] += round($amount, 2);
                            $map_result[$ownership_id][$property_lot_id]['common_expense'][$apportionment_id][$accountingEntryLine['account_id']]['total_amount'] += $line_amount;
                        }

                        $map_property_lots_ids[$property_lot_id] = true;
                    }
                }
                $map_accounts_ids[$accountingEntryLine['account_id']] = true;
            }

            // 4) common expense
            elseif(substr($accountingEntryLine['account_code'], 0, 1) === '6' || substr($accountingEntryLine['account_code'], 0, 1) === '7') {
                // handle all possible sources: PurchaseInvoiceLine, or BankStatementLine, or MiscOperation
                if(isset($accountingEntryLine['purchase_invoice_line_id'])) {
                    $sourceLine = PurchaseInvoiceLine::id($accountingEntryLine['purchase_invoice_line_id'])
                        ->read([
                            'apportionment_id', 'owner_share', 'tenant_share', 'vat_rate'
                        ])
                        ->first();

                    if(!$sourceLine) {
                        throw new \Exception('missing_mandatory_sale_invoice_line', EQ_ERROR_INVALID_CONFIG);
                    }
                }
                elseif(isset($accountingEntryLine['bank_statement_line_id'])) {
                    $sourceLine = BankStatementLine::id($accountingEntryLine['bank_statement_line_id'])
                        ->read([
                            'apportionment_id', 'owner_share', 'tenant_share', 'vat_rate'
                        ])
                        ->first();

                    if(!$sourceLine) {
                        throw new \Exception('missing_mandatory_bank_statement_line', EQ_ERROR_INVALID_CONFIG);
                    }
                }
                elseif(isset($accountingEntryLine['misc_operation_line_id'])) {
                    $sourceLine = MiscOperationLine::id($accountingEntryLine['misc_operation_line_id'])
                        ->read([
                            'apportionment_id', 'owner_share', 'tenant_share', 'vat_rate'
                        ])
                        ->first();

                    if(!$sourceLine) {
                        throw new \Exception('missing_mandatory_misc_operation_line', EQ_ERROR_INVALID_CONFIG);
                    }
                }
                elseif(isset($accountingEntryLine['fund_usage_line_id'])) {
                    // condominium fund usage (accounting entry line related to a class 7 account) must not be considered
                    // this is handled in ownership accounting with paid amount on previous expense statements
                    continue;
                }
                // FundRequestExecutionLine (ExpenseStatementOwnerLine have been excluded above while testing on expense_statement_id)
                elseif(isset($accountingEntryLine['sale_invoice_line_id'])) {
                    // we should not reach this point, either FundRequestExecutionLine or ExpenseStatementOwnerLine
                    // Expense Statements are discarded and Fund Requests are handled separately
                    continue;
                }
                else {
                    trigger_error("APP::unable to find source line for accounting entry line {$accountingEntryLine['id']}", EQ_REPORT_ERROR);
                    throw new \Exception('missing_mandatory_source_line', EQ_ERROR_INVALID_CONFIG);
                }


                $line_amount = ($accountingEntryLine['debit'] > 0) ? $accountingEntryLine['debit'] : -$accountingEntryLine['credit'];
                $vat_amount = $line_amount * $sourceLine['vat_rate'];

                $common_total += $line_amount;

                $apportionment = $map_apportionments[$sourceLine['apportionment_id']];

                foreach($ownerships as $ownership_id => $ownership) {
                    foreach($ownership['property_lots'] as $property_lot_id => $propertyLotOwnership) {

                        if($propertyLotOwnership['date_to'] && $propertyLotOwnership['date_to'] < $fiscalPeriod['date_from']) {
                            continue;
                        }
                        if(!isset($apportionment[$property_lot_id])) {
                            continue;
                        }

                        $prorata = $propertyLotOwnership['nb_days'] / $nb_days;
                        $shares = $apportionment[$property_lot_id];
                        $total_shares = $apportionments[$sourceLine['apportionment_id']]['total_shares'];

                        $amount = $prorata * ($line_amount * $shares / $total_shares);

                        if(!isset($map_result[$ownership_id][$property_lot_id]['common_expense'][$sourceLine['apportionment_id']][$accountingEntryLine['account_id']])) {
                            $map_result[$ownership_id][$property_lot_id]['common_expense'][$sourceLine['apportionment_id']][$accountingEntryLine['account_id']] = [
                                    'shares'        => $shares,
                                    'total_shares'  => $total_shares,
                                    'total_amount'  => $line_amount,
                                    'owner'         => 0.0,
                                    'tenant'        => 0.0,
                                    'vat'           => 0.0,
                                    'assigned_delta'=> 0.0
                                ];
                        }
                        else {
                            $map_result[$ownership_id][$property_lot_id]['common_expense'][$sourceLine['apportionment_id']][$accountingEntryLine['account_id']]['total_amount'] += $line_amount;
                        }

                        $amount_vat = round($prorata * ($vat_amount * $shares / $total_shares), 2);
                        $amount_owner = round(round($amount, 2) * ($sourceLine['owner_share'] / 100), 2);
                        $amount_tenant = round(round($amount, 2) * (1 - $sourceLine['owner_share'] / 100), 2);
                        // if there is delta in the shares, allocate it to the owner
                        $adjust = round($amount, 2) - $amount_owner - $amount_tenant;
                        $amount_owner += $adjust;
                        // add up the delta (cents to reinvoice later): if delta is < 0, it will be reimbursed at some point
                        $delta_total += $amount - ($amount_owner + $amount_tenant);

                        $map_result[$ownership_id][$property_lot_id]['common_expense'][$sourceLine['apportionment_id']][$accountingEntryLine['account_id']]['vat'] += $amount_vat;
                        $map_result[$ownership_id][$property_lot_id]['common_expense'][$sourceLine['apportionment_id']][$accountingEntryLine['account_id']]['owner'] += $amount_owner;
                        $map_result[$ownership_id][$property_lot_id]['common_expense'][$sourceLine['apportionment_id']][$accountingEntryLine['account_id']]['tenant'] += $amount_tenant;
                        $map_result[$ownership_id][$property_lot_id]['common_expense'][$sourceLine['apportionment_id']][$accountingEntryLine['account_id']]['assigned_delta'] += $delta_total;

                        $map_property_lots_ids[$property_lot_id] = true;
                    }
                }
                $map_accounts_ids[$accountingEntryLine['account_id']] = true;
            }

        }

        // generate output response
        $result = [
                'provisions_total'           => self::normalizeMoneyAmount($provisions_total),
                'private_total'              => self::normalizeMoneyAmount($private_total),
                'common_total'               => self::normalizeMoneyAmount($common_total),
                // #memo - a positive amount means that a part of the purchase invoice was not allocated to owners
                'assigned_delta'             => self::normalizeMoneyAmount($delta_total),
                'accounting_entry_lines_ids' => array_keys($map_accounting_entry_lines_ids),
                'owners'                     => []
            ];

        foreach($map_result as $ownership_id => $list_property_lots) {
            if($ownerships[$ownership_id]['nb_days'] <= 0) {
                continue;
            }

            $owner = [
                    'id'                    => $ownership_id,
                    'nb_days'               => $ownerships[$ownership_id]['nb_days'],
                    'date_from'             => $ownerships[$ownership_id]['date_from'] ?? null,
                    'date_to'               => $ownerships[$ownership_id]['date_to'] ?? null,
                    'lines'                 => []
                ];

            // linearize resulting lines
            foreach($list_property_lots as $property_lot_id => $list_expenses) {
                foreach($list_expenses as $expense_type => $list_apportionments) {
                    foreach($list_apportionments as $apportionment_id => $list_accounts) {
                        foreach($list_accounts as $account_id => $account) {
                            // special case for private expense (same account with several entries)
                            if(!isset($account['owner']) && count($account)) {
                                foreach($account as $account_entry) {
                                    $owner['lines'][] = [
                                            'account_id'        => $account_id,
                                            'property_lot_id'   => $property_lot_id,
                                            'apportionment_id'  => $apportionment_id,
                                            'expense_type'      => $expense_type,
                                            'owner'             => self::normalizeMoneyAmount($account_entry['owner']),
                                            'tenant'            => self::normalizeMoneyAmount($account_entry['tenant']),
                                            'vat'               => self::normalizeMoneyAmount($account_entry['vat']),
                                            'description'       => $account_entry['description'] ?? null,
                                            'date'              => $account_entry['date'] ?? null,
                                            'date_from'         => $ownerships[$ownership_id]['property_lots'][$property_lot_id]['date_from'],
                                            'date_to'           => $ownerships[$ownership_id]['property_lots'][$property_lot_id]['date_to'],
                                            'nb_days'           => $ownerships[$ownership_id]['property_lots'][$property_lot_id]['nb_days']
                                        ];
                                }
                            }
                            else {
                                $owner['lines'][] = [
                                        'account_id'        => $account_id,
                                        'property_lot_id'   => $property_lot_id,
                                        'apportionment_id'  => $apportionment_id,
                                        'expense_type'      => $expense_type,
                                        'owner'             => self::normalizeMoneyAmount($account['owner']),
                                        'tenant'            => self::normalizeMoneyAmount($account['tenant']),
                                        'vat'               => self::normalizeMoneyAmount($account['vat'] ?? 0.0),
                                        'description'       => $account['description'] ?? null,
                                        'date'              => $account['date'] ?? null,
                                        'date_from'         => $ownerships[$ownership_id]['property_lots'][$property_lot_id]['date_from'],
                                        'date_to'           => $ownerships[$ownership_id]['property_lots'][$property_lot_id]['date_to'],
                                        'nb_days'           => $ownerships[$ownership_id]['property_lots'][$property_lot_id]['nb_days'],
                                        'shares'            => $account['shares'] ?? null,
                                        'total_amount'      => self::normalizeMoneyAmount($account['total_amount']),
                                    ];
                            }
                        }
                    }
                }
            }

            $result['owners'][] = $owner;
        }

        return $result;
    }

    /**
     * Generates a structure that holds all information for generating closing accounting entries and expense statement report (addressed to owners).
     *
     */
    public static function calcSchema($self) {
        $result = [];

        $self->read([
                'id',
                'common_total',
                'private_total',
                'provisions_total',
                'fiscal_period_id' => ['date_from', 'date_to'],
                'statement_owners_ids' => ['schema']
            ]);

        foreach($self as $id => $statement) {

            $response = [
                    'date_from'         => $statement['fiscal_period_id']['date_from'],
                    'date_to'           => $statement['fiscal_period_id']['date_to'],
                    'common_total'      => $statement['common_total'],
                    'private_total'     => $statement['private_total'],
                    'provisions_total'  => $statement['provisions_total'],
                    'nb_days'           => round(($statement['fiscal_period_id']['date_to'] - $statement['fiscal_period_id']['date_from']) / 86400, 0) + 1,
                    'owners'            => []
                ];

            foreach($statement['statement_owners_ids'] as $statement_owner_id => $statementOwner) {
                $response['owners'][] = $statementOwner['schema'];
            }

            $result[$id] = $response;
        }

        return $result;
    }

    protected static function canupdate($self, $values) {
        $self->read(['status']);
        $allowed_fields = ['status'];

        foreach($self as $id => $invoice) {
            if($invoice['status'] === 'posted') {
                if( count(array_diff(array_keys($values), $allowed_fields)) ) {
                    return ['status' => ['non_editable' => 'Expense Statement cannot be updated after recording.']];
                }
            }
        }
        return parent::canupdate($self, $values);
    }

}
