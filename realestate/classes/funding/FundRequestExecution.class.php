<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;
use finance\accounting\Journal;
use finance\accounting\Account;
use realestate\finance\accounting\AccountingEntry;
use realestate\finance\accounting\AccountingEntryLine;
use realestate\ownership\Ownership;
use fmt\setting\Setting;
use realestate\ownership\OwnershipCommunicationPreference;
use realestate\sale\pay\Funding;
use sale\pay\Payment;

#memo - Fund requests executions are handled as sales invoices
class FundRequestExecution extends \realestate\sale\accounting\invoice\SaleInvoice {

    protected const MAP_DEBIT_OPERATION_ASSIGNMENTS = [
        'reserve_fund'         => 'co_owners_reserve_fund',
        'special_reserve_fund' => 'co_owners_reserve_fund',
        'working_fund'         => 'co_owners_working_fund',
        'expense_provisions'   => 'co_owners_working_fund',
        'work_provisions'      => 'co_owners_working_fund',
    ];

    public static function getName() {
        return 'Fund Request Execution';
    }

    public static function getDescription() {
        return "A Fund Request Execution represents the actual execution of a fund request. It is handled as a sale invoice, generating accounting entries based on the predefined allocation plan.";
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

            'invoice_type' => [
                'type'              => 'string',
                'description'       => 'Document type (fund requests are handled as sale invoices).',
                'default'           => 'fund_request',
                'readonly'          => true
            ],

            /* from sale\accounting\invoice\Invoice: */
            // 'funding_id'
            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => 'There is no customer for fund requests.',
            ],

            /* additional fields*/

            'fund_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => "Fund request the line relates to.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'called_amount' => [
                'type'              => 'alias',
                'alias'             => 'price',
                'usage'             => 'amount/money:2'
            ],

            'execution_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestExecutionLine',
                'foreign_field'     => 'invoice_id',
                'description'       => "Lines of the Fund request execution."
            ],

            'execution_line_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestExecutionLineEntry',
                'foreign_field'     => 'request_execution_id',
                'description'       => "Lines of the Fund request execution."
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'fund_request_execution_id',
                'domain'            => ['funding_type', '=', 'fund_request'],
                'description'       => 'The fundings that relate to the execution (sale invoice).'
            ],

            'fund_request_execution_correspondences_ids' => [
                'type'              => 'one2many',
                'description'       => "Invitations sent for the assembly.",
                'foreign_object'    => 'realestate\funding\FundRequestExecutionCorrespondence',
                'foreign_field'     => 'fund_request_execution_id'
            ],

            'exporting_tasks_ids' => [
                'type'              => 'one2many',
                'description'       => "Reference to the tasks for exporting paper mails for expense statement, if any.",
                'help'              => "This is a helper relation to allow generic handling in views.",
                'foreign_object'    => 'documents\export\ExportingTask',
                'foreign_field'     => 'object_id',
                'domain'            => [
                    ['object_class', '=', 'realestate\funding\FundRequestExecution']
                ]
            ],

            'fundings_exporting_task_id' => [
                'type'              => 'many2one',
                'description'       => "Reference to the task for exporting paper mails for funding request, if any.",
                'foreign_object'    => 'documents\export\ExportingTask'
            ],

            'with_due_balance' =>  [
                'type'              => 'boolean',
                'description'       => 'Take into account the balance status of the co-owners.',
                'help'              => "If set to true, the payment request will be base on Ownership due balance instead of theoretical Funding due amount.",
                'default'           => true
            ],

            'logs' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Logs of the accounting entry generation.'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Draft fund request execution, waiting to reach execution date.',
                'icon'        => 'draw',
                'transitions' => [
                    'call' => [
                        'description' => 'Update the fund request execution to `invoice`.',
                        'help'        => 'This is a substitute to the parent sale invoice workflow (there is a single accounting entry for a fund request execution).',
                        'policies'    => ['is_proforma', 'can_perform_execution'],
                        'onbefore'    => 'onbeforeCall',
                        'status'      => 'posted'
                    ]
                ]
            ],
            'posted' => [
                'description' => 'Draft fund request execution, waiting to reach execution date.',
                'icon'        => 'done',
                'transitions' => [
                    'cancel' => [
                        'description' => 'Update the fund request execution to `cancelled`.',
                        'policies'    => [],
                        'onafter'     => 'onafterCancelled',
                        'status'      => 'cancelled'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'perform_execution' => [
                'description'   => 'Perform the fund request execution by creating and validating resulting Accounting entries and Fundings.',
                'help'          => 'This action is for emitting the invoice, and can be either called by `onbeforeCall` or through UI.',
                'policies'      => ['can_perform_execution'],
                'function'      => 'doPerformExecution'
            ],
            'generate_accounting_entry' => [
                'description'   => 'Generate a draft of the resulting accounting entry and entry lines.',
                'policies'      => ['can_generate_accounting_entry'],
                'function'      => 'doGenerateAccountingEntry'
            ],
            'generate_fundings' => [
                'description'   => 'Generate fundings for each involved ownership.',
                'policies'      => [],
                'function'      => 'doGenerateFundings'
            ],
            'cancel_execution' => [
                'description'   => 'Void the execution, and cancel subsequent accounting entry.',
                'policies'      => ['can_cancel'],
                'function'      => 'doCancelExecution'
            ],
            'assign_invoice_number' => [
                'help'          => 'For FundRequestExecution, invoice number is assigned during `perform_execution`.',
                'policies'      => ['is_proforma'],
                'function'      => 'doAssignInvoiceNumber'
            ],
            'generate_fund_request_execution_correspondences' => [
                'description'   => 'Generate individual correspondences for requesting the fundings.',
                'policies'      => [],
                'function'      => 'doGenerateFundRequestExecutionCorrespondences'
            ],
            'send_fund_requests' => [
                'description'   => 'Generate individual correspondences for requesting the fundings.',
                'policies'      => [],
                'function'      => 'doSendFundRequests'
            ]
        ]);
    }


    public static function getPolicies(): array {
        return [
            'can_perform_execution' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanPerformExecution'
            ],
            'can_generate_accounting_entry' => [
                'description' => 'Verifies that an FundRequest execution invoice is still a draft (proforma).',
                'help'        => 'This policy is duplicated to overwrite parent.',
                'function'    => 'policyCanGenerateAccountingEntry'
            ],
            'is_proforma' => [
                'description' => 'Verifies that an FundRequest execution invoice is still a draft (proforma).',
                'function'    => 'policyIsProforma'
            ]
        ];
    }

    public static function policyIsProforma($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $requestExecution) {
            if($requestExecution['status'] != 'proforma') {
                $result[$id] = [
                    'invalid_status' => 'Request Execution status must be proforma.'
                ];
                continue;
            }
        }
        return $result;
    }

    /**
     * For FundRequestExecution invoices, accounting entry is generated upon funds being called, i.e. when invoice is validated and assigned to a number (there is no draft accounting entry).
     */
    public static function policyCanGenerateAccountingEntry($self): array {
        $result = [];
        $self->read(['status', 'accounting_entry_id']);
        foreach($self as $id => $requestExecution) {
            if($requestExecution['accounting_entry_id']) {
                $result[$id] = [
                    'invalid_status' => 'Request Execution already has an accounting entry.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function policyCanPerformExecution($self): array {
        $result = [];
        $self->read([
                'status', 'emission_date', 'posting_date',
                'fiscal_period_id' => ['status', 'fiscal_year_status']
            ]);

        foreach($self as $id => $requestExecution) {
            if($requestExecution['status'] != 'proforma') {
                $result[$id] = [
                    'invalid_status' => 'Request Execution status must be proforma.'
                ];
                continue;
            }
            if($requestExecution['emission_date'] != $requestExecution['posting_date']) {
                $result[$id] = [
                    'dates_mismatch' => 'Posting and Emission dates must be the same.'
                ];
                continue;
            }
            if(!$requestExecution['fiscal_period_id']) {
                $result[$id] = [
                    'missing_fiscal_period' => 'Fiscal period is mandatory (could not resolve).'
                ];
                continue;
            }

            if($requestExecution['fiscal_period_id']['status'] !== 'open') {
                $result[$id] = [
                    'invalid_fiscal_period' => 'Cannot perform fund request on a non-open fiscal period.'
                ];
                continue;
            }

            if(!in_array($requestExecution['fiscal_period_id']['fiscal_year_status'], ['preopen', 'open'], true)) {
                $result[$id] = [
                    'invalid_fiscal_year' => 'Cannot perform fund request on a non-open fiscal year.'
                ];
                continue;
            }

        }
        return $result;
    }

    public static function onbeforeCall($self) {
        $self->do('perform_execution');
    }

    public static function doAssignInvoiceNumber($self) {
        $self->read(['organisation_id', 'condo_id', 'fiscal_year_id' => ['code'], 'fiscal_period_id' => ['code']]);
        foreach($self as $id => $requestExecution) {
            $format = Setting::get_value(
                    'sale',
                    'accounting',
                    'invoice.sequence_format',
                    '%2d{year}_%05d{sequence}',
                    [
                        'condo_id' => $requestExecution['condo_id']
                    ]
                );

            $fiscal_year_code = $requestExecution['fiscal_year_id']['code'];
            $fiscal_period_code = $requestExecution['fiscal_period_id']['code'];

            $sequence = Setting::fetch_and_add(
                    'sale',
                    'accounting',
                    "invoice.sequence.{$fiscal_year_code}.{$fiscal_period_code}",
                    1,
                    [
                        'condo_id' => $requestExecution['condo_id']
                    ]
                );

            if(!$sequence) {
                trigger_error("APP::missing mandatory sale.accounting.invoice.sequence.{$fiscal_year_code} for condominium {$requestExecution['condo_id']}.", EQ_REPORT_ERROR);
                throw new \Exception('missing_mandatory_sequence', EQ_ERROR_INVALID_CONFIG);
            }

            $invoice_number = Setting::parse_format($format, [
                    'year'      => $fiscal_year_code,
                    'period'    => $fiscal_period_code,
                    'org'       => $requestExecution['organisation_id'],
                    'condo'     => $requestExecution['condo_id'],
                    'sequence'  => $sequence
                ]);

            self::id($id)->update(['invoice_number' => $invoice_number]);
        }
    }

    public static function onafterCancelled($self) {
        $self->do('cancel_execution');
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['fund_request_id' => ['name'], 'posting_date']);
        foreach($self as $id => $requestExecution) {
            if($requestExecution['fund_request_id']) {
                $result[$id] = $requestExecution['fund_request_id']['name'] . ' ('. date('d/m/Y', $requestExecution['posting_date']) . ')';
            }
        }
        return $result;
    }

    public static function doPerformExecution($self) {
        $self
            ->do('assign_invoice_number')
            ->do('generate_accounting_entry')
            ->do('generate_fundings')
            ->do('generate_fund_request_execution_correspondences')
            ->do('send_fund_requests')
            // automatically validate accounting entry
            ->read(['accounting_entry_id'])
            ->each(function($id, $requestExecution) {
                AccountingEntry::id($requestExecution['accounting_entry_id'])->transition('validate');
            });
    }

    /**
     * Generate invites for each ownership.
     */
    protected static function doGenerateFundRequestExecutionCorrespondences($self) {
        $self->read(['condo_id', 'execution_lines_ids' => ['ownership_id']]);
        foreach($self as $id => $fundRequestExecution) {
            // remove any previously created invite
            FundRequestExecutionCorrespondence::search(['fund_request_execution_id', '=', $id])->delete(true);

            $ownerships_ids = array_column($fundRequestExecution['execution_lines_ids']->get(true), 'ownership_id');
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
                        ['condo_id', '=', $fundRequestExecution['condo_id']],
                        ['ownership_id', '=', $ownership_id],
                        ['communication_reason', '=', 'fund_request']
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

                    FundRequestExecutionCorrespondence::create([
                        'condo_id'                  => $fundRequestExecution['condo_id'],
                        'fund_request_execution_id' => $id,
                        'ownership_id'              => $ownership_id,
                        'owner_id'                  => $ownership['representative_owner_id'],
                        'communication_method'      => $communication_method
                    ]);
                }
            }
        }
    }

    protected static function doSendFundRequests($self, $cron) {

        $self->read([
            'name',
            'condo_id',
            'fundings_exporting_task_id',
            'fund_request_execution_correspondences_ids' => ['communication_method']
        ]);

        foreach($self as $id => $fundRequestExecution) {

            // remove previously created exporting task (and lines), if any
            if($fundRequestExecution['fundings_exporting_task_id']) {
                ExportingTask::id($fundRequestExecution['fundings_exporting_task_id'])->delete(true);
            }

            $map_communication_methods = [];

            foreach($fundRequestExecution['fund_request_execution_correspondences_ids'] as $fundRequestExecutionCorrespondence) {
                // update global map to acknowledge that at least one invitation uses that communication method
                $map_communication_methods[$fundRequestExecutionCorrespondence['communication_method']] = true;
            }

            if(isset($map_communication_methods['email'])) {
                // schedule queuing of invite emails
                $cron->schedule(
                    "realestate.fundrequest.send-fundings.{$id}",
                    time() + (5 * 60),
                    'realestate_funding_FundRequestExecution_send-fundings',
                    [
                        'id'  => $id
                    ]
                );
            }

            // handle non-digital communication methods
            if(count(array_diff(array_keys($map_communication_methods), ['email'])) > 0) {

                // schedule generation of a zip archive containing printable documents
                $exportingTask = ExportingTask::create([
                        'name'          => "{$fundRequestExecution['name']} - Export des courriers de l'appel de fonds",
                        'condo_id'      => $fundRequestExecution['condo_id'],
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
                            'name'              => "{$fundRequestExecution['name']} - Export du PV - {$communication_method}",
                            'controller'        => 'realestate_funding_FundRequestExecution_export-fundings',
                            'params'            => json_encode([
                                    'id'                    => $id,
                                    'communication_method'  => $communication_method
                                ])
                        ]);
                }

                self::id($id)->update([
                        'fundings_exporting_task_id' => $exportingTask['id']
                    ]);
            }
        }

    }

    public static function doCancelExecution($self) {
        $self->read(['execution_lines_ids' => ['ownership_id'], 'fund_request_id', 'accounting_entry_id']);

        foreach($self as $id => $requestExecution) {
            // retrieve accounting entry and cancel it
            AccountingEntry::id($requestExecution['accounting_entry_id'])->do('cancel');

            foreach($requestExecution['execution_lines_ids'] as $execution_line_id => $executionLine) {
                // remove related fundings with no payments
                $fundings = Funding::search([
                        ['ownership_id', '=', $executionLine['ownership_id']],
                        ['fund_request_id', '=', $requestExecution['fund_request_id']]
                    ])
                    ->read(['payments_ids']);

                foreach($fundings as $funding_id => $funding) {
                    // remove empty fundings
                    if(empty($funding['payments_ids'])) {
                        Funding::id($funding_id)->delete(true);
                    }
                    // detach non-empty ones from current execution
                    else {
                        Funding::id($funding_id)->update(['fund_request_execution_id' => null]);
                    }
                }
            }
        }

        self::id($id)->update([
            'status' => 'cancelled',
            'accounting_entry_id' => null
        ]);
    }

    /**
     * Generate accounting entries related to the fund request execution.
     * When an execution has been called, an accounting entry has been generated, and it can no longer be modified.
     * However, it is possible to cancel it by passing a cancellation entry (reversal).
     */
    public static function doGenerateAccountingEntry($self) {

        $self->read([
                'name',
                'fiscal_year_id',
                'accounting_entry_id',
                'emission_date',
                'called_amount',
                'condo_id',
                'fund_request_id' => ['request_type', 'request_account_id'],
                'execution_lines_ids' => ['ownership_id', 'called_amount']
            ]);

        foreach($self as $id => $requestExecution) {
            $logs = [];

            if($requestExecution['accounting_entry_id'] !== null) {
                AccountingEntry::id($requestExecution['accounting_entry_id'])->delete(true);
            }

            $journal = Journal::search([['condo_id', '=', $requestExecution['condo_id']], ['journal_type', '=', 'SALE']])->first();

            $logs[] = "Retrieved SALE journal id {$journal['id']}";

            // create an accounting entry
            $accountingEntry = AccountingEntry::create([
                    'condo_id'                  => $requestExecution['condo_id'],
                    'journal_id'                => $journal['id'],
                    'invoice_id'                => $id,
                    'fiscal_year_id'            => $requestExecution['fiscal_year_id'],
                    'entry_date'                => $requestExecution['emission_date'],
                    'origin_object_class'       => self::getType(),
                    'origin_object_id'          => $id,
                    'sale_invoice_id'           => $id,
                    'fund_request_execution_id' => $id
                ])
                ->first();

            $logs[] = "Created accounting entry id {$accountingEntry['id']}";

            // create the credit line
            AccountingEntryLine::create([
                    'condo_id'              => $requestExecution['condo_id'],
                    'accounting_entry_id'   => $accountingEntry['id'],
                    'description'           => $requestExecution['name'],
                    'account_id'            => $requestExecution['fund_request_id']['request_account_id'],
                    'debit'                 => 0.0,
                    'credit'                => $requestExecution['called_amount']
                ]);

            //create the debit lines
            $debit_operation_assignment = static::MAP_DEBIT_OPERATION_ASSIGNMENTS[$requestExecution['fund_request_id']['request_type']];
            $logs[] = "Retrieved debit operation assignment {$debit_operation_assignment}";

            foreach($requestExecution['execution_lines_ids'] as $execution_line_id => $executionLine) {

                $ownership_id = $executionLine['ownership_id'];
                // find the account based on operation_assignment
                $logs[] = "Fetching account for ownership {$ownership_id}";
                $ownershipAccount = Account::search([
                        ['condo_id', '=', $requestExecution['condo_id']],
                        ['ownership_id', '=', $ownership_id],
                        ['operation_assignment', '=', $debit_operation_assignment]
                    ])
                    ->first();

                if(!$ownershipAccount) {
                    throw new \Exception('missing_ownership_accounting_account', EQ_ERROR_INVALID_PARAM);
                }

                $logs[] = "Retrieved owner account {$ownershipAccount['id']}";

                AccountingEntryLine::create([
                        'condo_id'              => $requestExecution['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'sale_invoice_line_id'  => $execution_line_id,
                        'description'           => $requestExecution['name'],
                        'account_id'            => $ownershipAccount['id'],
                        'debit'                 => $executionLine['called_amount'],
                        'credit'                => 0.0
                    ]);
            }
            self::id($id)->update([
                    'accounting_entry_id' => $accountingEntry['id'],
                    'logs'  => implode("\n", $logs)
                ]);
        }

    }

    public static function doGenerateFundings($self) {

        $self->read([
                'posting_date',
                'due_date',
                'fiscal_year_id' => ['date_from'],
                'fiscal_period_id' => ['date_from', 'date_to'],
                'condo_id' => ['id', 'code'],
                'fund_request_id' => [
                    'id', 'name', 'request_type', 'request_account_id', 'request_bank_account_id'
                ],
                'execution_lines_ids' => ['ownership_id' => ['code'], 'called_amount', 'funding_id']
            ]);

        foreach($self as $id => $requestExecution) {

            $debit_operation_assignment = static::MAP_DEBIT_OPERATION_ASSIGNMENTS[$requestExecution['fund_request_id']['request_type']];

            foreach($requestExecution['execution_lines_ids'] as $execution_line_id => $executionLine) {
                $ownership_id = $executionLine['ownership_id']['id'];

                // find the account based on operation_assignment
                $logs[] = "Fetching account for ownership {$ownership_id}";
                $ownershipAccount = Account::search([
                        ['condo_id', '=', $requestExecution['condo_id']['id']],
                        ['ownership_id', '=', $ownership_id],
                        ['operation_assignment', '=', $debit_operation_assignment]
                    ])
                    ->first();

                if(!$ownershipAccount) {
                    throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                }

                // retrieve detached-non-empty fundings relating to the targeted ownership and fund request, if any
                $fund_request_id = $requestExecution['fund_request_id']['id'];
                $fundings = Funding::search([
                        ['ownership_id', '=', $ownership_id],
                        ['funding_type', '=', 'fund_request'],
                        ['fund_request_id', '=', $fund_request_id],
                        ['fund_request_execution_id', '=', null]
                    ])
                    ->read(['payments_ids']);

                $paid_amount = 0;
                foreach($fundings as $funding_id => $funding) {
                    // #memo - empty fundings have been removed at cancellation of previous execution(s)
                    // compute already paid/reimbursed amounts
                    $payments = Payment::ids($funding['payments_ids'])->read(['amount'])->get(true);
                    $paid_amount += round(array_sum(array_column($payments, 'amount')), 2);
                    // attached funding to current execution
                    Funding::id($funding_id)->update(['fund_request_execution_id' => $id]);
                }

                // #memo - for importing historical data, we must be able to issue a funding in the past
                $issue_date = $requestExecution['posting_date'];
                $due_date = $requestExecution['due_date'];

                // 1) generate theoretical Funding
                $due_amount = $executionLine['called_amount'] - $paid_amount;

                Funding::create([
                        'condo_id'                          => $requestExecution['condo_id']['id'],
                        'description'                       => $requestExecution['fund_request_id']['name'],
                        'fund_request_id'                   => $requestExecution['fund_request_id']['id'],
                        'fund_request_execution_id'         => $id,
                        'ownership_id'                      => $ownership_id,
                        'accounting_account_id'             => $ownershipAccount['id'],
                        'bank_account_id'                   => $requestExecution['fund_request_id']['request_bank_account_id'],
                        'issue_date'                        => $issue_date,
                        'due_date'                          => $due_date,
                        'due_amount'                        => $due_amount,
                        'funding_type'                      => 'fund_request'
                    ]);

                // 2) generate instant Funding based on current account statement
                $data = \eQual::run('get', 'finance_accounting_ownerAccountStatement_collect', [
                    'ownership_id'      => $ownership_id,
                    'date_from'         => $requestExecution['fiscal_period_id']['date_from'],
                    'date_to'           => $requestExecution['fiscal_period_id']['date_to']
                ]);

                $closing_balance = 0;

                if(count($data)) {
                    $closing_balance = end($data)['balance'] ?? 0;
                }

                Funding::create([
                        'condo_id'                          => $requestExecution['condo_id']['id'],
                        'description'                       => $requestExecution['fund_request_id']['name'],
                        'funding_type'                      => 'due_balance',
                        'fund_request_id'                   => $requestExecution['fund_request_id']['id'],
                        'fund_request_execution_id'         => $id,
                        'ownership_id'                      => $ownership_id,
                        'bank_account_id'                   => $requestExecution['fund_request_id']['request_bank_account_id'],
                        'accounting_account_id'             => $ownershipAccount['id'],
                        'issue_date'                        => $issue_date,
                        'due_date'                          => $due_date,
                        'due_amount'                        => $closing_balance
                    ]);

            }
        }
    }



}
