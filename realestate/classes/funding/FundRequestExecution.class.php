<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\funding;

use finance\accounting\Journal;
use finance\accounting\Account;
use finance\accounting\AccountingEntry;
use finance\accounting\AccountingEntryLine;
use realestate\ownership\Ownership;
use fmt\setting\Setting;
use sale\pay\Funding;
use sale\pay\Payment;

class FundRequestExecution extends \sale\accounting\invoice\Invoice {

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
            // 'accounting_entry_id'
            // 'emission_date'
            // 'due_date'

            'emission_date' => [
                'type'              => 'datetime',
                'usage'             => 'date/plain',
                'description'       => 'Date at which the execution is planned.',
                'required'          => true,
                'dependents'        => ['name']
            ],

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

            'logs' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Logs of the accounting entry generation'
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Funding',
                'foreign_field'     => 'fund_request_execution_id',
                'description'       => 'The fundings that relate to the execution (sale invoice).'
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
                        'policies'    => [],
                        'onbefore'    => 'onbeforeCall',
                        'onafter'     => 'onafterCall',
                        'status'      => 'invoice'
                    ]
                ]
            ],
            'invoice' => [
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
        return [
            'generate_accounting_entry' => [
                'description'   => 'Generate a draft of the resulting accounting entry and entry lines.',
                'policies'      => [],
                'function'      => 'doGenerateAccountingEntries'
            ],
            'generate_fundings' => [
                'description'   => 'Generate fundings for each involved ownership.',
                'policies'      => [],
                'function'      => 'doGenerateFundings'
            ],
            'perform_execution' => [
                'description'   => 'Perform the fund request execution by creating and validating resulting Accounting entries and Fundings.',
                'policies'      => [],
                'function'      => 'doPerformExecution'
            ],
            'cancel_execution' => [
                'description'   => 'Void the execution, and cancel subsequent accounting entry.',
                'policies'      => [],
                'function'      => 'doCancelExecution'
            ]
        ];
    }


    public static function onbeforeCall($self) {
        $self->read(['organisation_id', 'condo_id', 'fiscal_year_id' => ['code']]);
        foreach($self as $id => $requestExecution) {
            $format = Setting::get_value(
                    'sale',
                    'accounting',
                    'invoice.sequence_format',
                    '%2d{year}_%05d{sequence}',
                    [
                        'condo_id'          => $requestExecution['condo_id']
                    ]
                );

            $fiscal_year_code = $requestExecution['fiscal_year_id']['code'];

            $sequence = Setting::fetch_and_add(
                    'sale',
                    'accounting',
                    'invoice.sequence.' . $fiscal_year_code,
                    1,
                    [
                        'condo_id'          => $requestExecution['condo_id']
                    ]
                );

            if(!$sequence) {
                trigger_error("APP::missing mandatory sale.accounting.invoice.sequence.{$fiscal_year_code} for condominium {$requestExecution['condo_id']}.", EQ_REPORT_ERROR);
                throw new \Exception('missing_mandatory_sequence', EQ_ERROR_INVALID_CONFIG);
            }

            $invoice_number = Setting::parse_format($format, [
                    'year'      => $fiscal_year_code,
                    'org'       => $requestExecution['organisation_id'],
                    'condo'     => $requestExecution['condo_id'],
                    'sequence'  => $sequence
                ]);

            self::id($id)->update(['invoice_number' => $invoice_number]);
        }
    }

    public static function onafterCall($self) {
        $self->do('perform_execution');
    }

    public static function onafterCancelled($self) {
        $self->do('cancel_execution');
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['fund_request_id' => ['name'], 'emission_date']);
        foreach($self as $id => $requestExecution) {
            $result[$id] = $requestExecution['fund_request_id']['name'] . ' ('. date('d/m/Y', $requestExecution['emission_date']) . ')';
        }
        return $result;
    }

    public static function doPerformExecution($self) {
        $self
            ->do('generate_accounting_entry')
            ->do('generate_fundings')
            ->read(['accounting_entry_id']);

        // automatically validate accounting entry
        foreach($self as $id => $requestExecution) {
            AccountingEntry::id($requestExecution['accounting_entry_id'])->transition('validate');
        }

    }

    public static function doCancelExecution($self) {
        $self->update(['price' => 0.0])
            ->read(['execution_lines_ids' => ['ownership_id'], 'fund_request_id', 'accounting_entry_id']);
        foreach($self as $id => $requestExecution) {
            // retrieve accounting entry and cancel it
            AccountingEntry::id($requestExecution['accounting_entry_id'])->transition('cancel');

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

    }

    /**
     * Generate accounting entries related to the fund request execution.
     * When an execution has been called, an accounting entry has been generated, and it can no longer be modified.
     * However, it is possible to cancel it by passing a cancellation entry (reversal).
     */
    public static function doGenerateAccountingEntries($self) {

        static $map_debit_operation_assignments = [
                'reserve_fund'           => 'co_owners_reserve_fund',
                'working_fund'           => 'co_owners_working_fund',
                'expense_provisions'     => 'co_owners_working_fund',
                'work_provisions'        => 'co_owners_working_fund'
            ];

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

            AccountingEntry::id($requestExecution['accounting_entry_id'])->delete(true);

            $journal = Journal::search([['condo_id', '=', $requestExecution['condo_id']], ['code', '=', 'SAL']])->first();

            $logs[] = "Retrieved SALE journal id {$journal['id']}";

            // create an accounting entry
            $accountingEntry = AccountingEntry::create([
                    'condo_id'              => $requestExecution['condo_id'],
                    'journal_id'            => $journal['id'],
                    'invoice_id'            => $id,
                    'fiscal_year_id'        => $requestExecution['fiscal_year_id'],
                    'entry_date'            => $requestExecution['emission_date'],
                    'origin_object_class'   => self::getType(),
                    'origin_object_id'      => $id
                ])
                ->first();

            $logs[] = "Created accounting entry id {$accountingEntry['id']}";

            // create the credit line
            AccountingEntryLine::create([
                    'condo_id'              => $requestExecution['condo_id'],
                    'accounting_entry_id'   => $accountingEntry['id'],
                    'name'                  => $requestExecution['name'],
                    'account_id'            => $requestExecution['fund_request_id']['request_account_id'],
                    'debit'                 => 0.0,
                    'credit'                => $requestExecution['called_amount']
                ]);

            //create the debit lines
            $debit_operation_assignment = $map_debit_operation_assignments[$requestExecution['fund_request_id']['request_type']];
            $logs[] = "Retrieved debit operation assignment {$debit_operation_assignment}";

            // find the account based on operation_assignment
            $account = Account::search([
                    ['condo_id', '=', $requestExecution['condo_id']],
                    ['operation_assignment', '=', $debit_operation_assignment]
                ])
                ->read(['code'])
                ->first();

            if(!$account) {
                throw new \Exception('missing_mandatory_debit_account', EQ_ERROR_INVALID_CONFIG);
            }

            foreach($requestExecution['execution_lines_ids'] as $execution_line_id => $executionLine) {
                $ownership = Ownership::id($executionLine['ownership_id'])->read(['ownership_account_id'])->first();
                $logs[] = "Fetching account for ownership {$executionLine['ownership_id']}";

                if(!$ownership || !$ownership['ownership_account_id']) {
                    throw new \Exception('missing_mandatory_owner_account', EQ_ERROR_INVALID_CONFIG);
                }

                $logs[] = "Retrieved owner account {$ownership['ownership_account_id']}";

                AccountingEntryLine::create([
                        'condo_id'              => $requestExecution['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'name'                  => $requestExecution['name'],
                        'account_id'            => $ownership['ownership_account_id'],
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
                'emission_date',
                'due_date',
                'fiscal_year_id' => ['date_from'],
                'condo_id',
                'fund_request_id' => [
                    'id', 'name', 'request_type', 'request_account_id', 'request_bank_account_id'
                ],
                'execution_lines_ids' => ['ownership_id', 'called_amount', 'funding_id']
            ]);

        foreach($self as $id => $requestExecution) {

            foreach($requestExecution['execution_lines_ids'] as $execution_line_id => $executionLine) {
                $ownership_id = $executionLine['ownership_id'];
                // retrieve detached-non-empty fundings relating to the targeted ownership and fund request, if any
                $fund_request_id = $requestExecution['fund_request_id']['id'];
                $fundings = Funding::search([
                        ['ownership_id', '=', $ownership_id],
                        ['fund_request_id', '=', $fund_request_id],
                        ['fund_request_execution_id', '=', null]
                    ])
                    ->read(['payments_ids']);
                foreach($fundings as $funding_id => $funding) {
                    // #memo - empty fundings have been removed at cancellation of previous execution(s)
                    // compute already paid/reimbursed amounts
                    $payments = Payment::ids($funding['payments_ids'])->read(['amount'])->get(true);
                    $paid_amount = round(array_sum(array_column($payments, 'amount')), 2);
                    // attached funding to current execution
                    Funding::id($funding_id)->update(['fund_request_execution_id' => $id]);
                }

                // a funding cannot be issued nor due in the past
                $issue_date = max(strtotime('today'), $requestExecution['emission_date']);
                $due_date = max(strtotime('today'), $requestExecution['issue_date']);

                // CCC/CCCO/OOOXX
                $reference =
                    substr(str_pad($requestExecution['condo_id'], 6, '0', STR_PAD_LEFT), 0, 6) .
                    substr(str_pad($ownership_id, 4, '0', STR_PAD_LEFT), 0, 4);

                $prefix = substr($reference, 0, 3);
                $suffix = substr($reference, 3);

                Funding::create([
                        'condo_id'                  => $requestExecution['condo_id'],
                        'description'               => $requestExecution['fund_request_id']['name'],
                        'fund_request_id'           => $requestExecution['fund_request_id']['id'],
                        'fund_request_execution_id' => $id,
                        'ownership_id'              => $ownership_id,
                        'request_bank_account_id'   => $requestExecution['fund_request_id']['request_bank_account_id'],
                        'issue_date'                => $issue_date,
                        'due_date'                  => $due_date,
                        'due_amount'                => $executionLine['called_amount'] - $paid_amount,
                        'funding_type'              => 'fund_request',
                        'payment_reference'         => self::computePaymentReference($prefix, $suffix)
                    ]);

            }
        }
    }



}
