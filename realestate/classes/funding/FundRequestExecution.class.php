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

class FundRequestExecution extends \equal\orm\Model {

    public static function getName() {
        return 'Fund Request Execution';
    }

    public static function getDescription() {
        return "A Fund Request Execution represents the actual execution of a fund request, generating accounting entries based on the predefined allocation plan.";
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

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'fund_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => "Fund request the line relates to.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the fund request relates to.",
                'required'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'execution_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the execution is planned.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Accounting entry the line relates to."
            ],

            'called_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcCalledAmount',
                'store'             => true,
                'description'       => 'Total amount requested to co-owners.'
            ],

            'execution_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestExecutionLine',
                'foreign_field'     => 'request_execution_id',
                'description'       => "Lines of the Fund request execution."
            ],

            'logs' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Logs of the accounting entry generation'
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the request execution.',
                'selection'         => [
                    'waiting',
                    'called',
                    'cancelled'
                ],
                'default'           => 'waiting'
            ],


        ];
    }

    public static function getWorkflow() {
        return [
            'waiting' => [
                'description' => 'Draft fund request execution, waiting to reach execution date.',
                'icon'        => 'draw',
                'transitions' => [
                    'call' => [
                        'description' => 'Update the fund request execution to `called`.',
                        'policies'    => [],
                        'onafter'     => 'onafterCall',
                        'status'      => 'called'
                    ]
                ]
            ],
        ];
    }

    public static function getActions() {
        return [
            'generate_accounting_entries' => [
                'description'   => 'Generate the request lines according to the property lots of the condominium and their respective shares.',
                'policies'      => [],
                'function'      => 'doGenerateAccountingEntries'
            ]
        ];
    }

    public static function onafterCall($self) {
        $self->do('generate_accounting_entries');
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['fund_request_id' => ['name'], 'execution_date']);
        foreach($self as $id => $requestExecution) {
            $result[$id] = $requestExecution['fund_request_id']['name'] . ' ('. date('d/m/Y', $requestExecution['execution_date']) . ')';
        }
        return $result;
    }

    public static function calcCalledAmount($self) {
        $result = [];
        $self->read(['execution_lines_ids' => ['called_amount']]);
        foreach($self as $id => $requestExecution) {
            if(empty($requestExecution['execution_lines_ids'])) {
                continue;
            }
            $result[$id] = 0.0;
            foreach($requestExecution['execution_lines_ids'] as $executionLine) {
                $result[$id] += $executionLine['called_amount'];
            }
        }
        return $result;
    }

    /**
     * Generate accounting entries related to the fund request execution.
     * When an execution has been called, an accounting entry has been generated, and it can no longer be modified.
     * However, it is possible to cancel it by passing a cancellation entry (reversal).
     */
    public static function doGenerateAccountingEntries($self) {
        static $map_credit_operation_assignments = [
                'reserve'           => 'reserve_fund',
                'working'           => 'working_fund',
                'expense'           => 'expense_provisions',
                'unique_expense'    => 'work_provisions'
            ];

        static $map_debit_operation_assignments = [
                'reserve'           => 'co_owners_reserve_fund',
                'working'           => 'co_owners_working_fund',
                'expense'           => 'co_owners_working_fund',
                'unique_expense'    => 'co_owners_working_fund'
            ];

        $self->read([
                'name',
                'fiscal_year_id',
                'accounting_entry_id',
                'execution_date',
                'called_amount',
                'condo_id',
                'fund_request_id' => ['request_type'],
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
                'fiscal_year_id'        => $requestExecution['fiscal_year_id'],
                'entry_date'            => $requestExecution['execution_date'],
                'origin_object_class'   => self::getType(),
                'origin_object_id'      => $id
            ])
            ->first();

            $logs[] = "Created accounting entry id {$accountingEntry['id']}";

            // create the credit line
            $credit_operation_assignment = $map_credit_operation_assignments[$requestExecution['fund_request_id']['request_type']];
            $logs[] = "Retrieved credit operation assignment {$credit_operation_assignment}";

            // find the account based on operation_assignment
            $account = Account::search([
                    ['condo_id', '=', $requestExecution['condo_id']],
                    ['operation_assignment', '=', $credit_operation_assignment]
                ])
                ->first();

            if(!$account) {
                throw new \Exception('missing_mandatory_credit_account', EQ_ERROR_INVALID_CONFIG);
            }

            $logs[] = "Retrieved credit account id {$account['id']}";

            AccountingEntryLine::create([
                    'condo_id'              => $requestExecution['condo_id'],
                    'accounting_entry_id'   => $accountingEntry['id'],
                    'name'                  => $requestExecution['name'],
                    'account_id'            => $account['id'],
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
                $ownership = Ownership::id($executionLine['ownership_id'])->read(['ownership_code'])->first();
                $logs[] = "Searching account {$account['code']}{$ownership['ownership_code']}";
                $ownerAccount = Account::search([
                        ['condo_id', '=', $requestExecution['condo_id']],
                        ['code', '=', $account['code'] . $ownership['ownership_code']]
                    ])
                    ->first();

                if(!$ownerAccount) {
                    throw new \Exception('missing_mandatory_owner_account', EQ_ERROR_INVALID_CONFIG);
                }

                $logs[] = "Retrieved owner account {$ownerAccount['id']}";

                AccountingEntryLine::create([
                        'condo_id'              => $requestExecution['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'name'                  => $requestExecution['name'],
                        'account_id'            => $ownerAccount['id'],
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
}