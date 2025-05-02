<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;
use realestate\funding\FundRequestExecution;
use realestate\ownership\Ownership;

class FiscalPeriod extends Model {

    public static function getName() {
        return "Fiscal Period";
    }

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the fiscal period refers to.",
                'help'              => "When a fiscal year is not linked to a condominium, it relates to the organisation itself.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => "The organisation the chart belongs to.",
                'default'           => 1
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "The organisation the chart belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => 'Label for identifying the fiscal year.',
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => 'First day (included) of the fiscal year.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'Last day (included) of the period.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'code' => [
                'type'              => 'integer',
                'description'       => 'Order of the period, based on its date within the fiscal year.',
                'help'              => 'This value is assigned by parent Fiscal Year, and is needed for purchase invoice sequence numbering.',
                'dependents'        => ['name']
            ],

            'status' => [
                'type'        => 'string',
                'selection'   => [
                    'pending',
                    'closed'
                ],
                'default'     => 'pending',
                'description' => 'Status of the accounting period.',
                'help'        => 'Status is `closed` once the expense statement has been validated.'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Pending fiscal period, that can be used for recording new accounting entries.',
                'icon' => 'draw',
                'transitions' => [
                    'close' => [
                        'description' => 'Close the fiscal period.',
                        'help' => 'A fiscal period is meant to be closed the sooner at the day matching date stored in `date_to` field.',
                        'policies' => [
                            'can_be_closed'
                        ],
                        'onbefore' => 'onbeforeClose',
                        'status' => 'close'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return [
            'generate_accounting_entries' => [
                'description'   => 'Generate the accounting entries for closing the fiscal period.',
                'policies'      => ['is_balanced', 'can_generate_executions'],
                'function'      => 'doGenerateAccountingEntries'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_be_closed' => [
                'description' => 'Verifies that a fiscal period can be closed according its configuration.',
                'function'    => 'policyCanBeClosed'
            ]
        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['code', 'date_from', 'date_to', 'condo_id' => ['name']]);
        foreach($self as $id => $period) {
            if(!$period['date_from'] || !$period['date_to']) {
                continue;
            }
            $result[$id] = $period['code'] . ' - ' . date('Y-m-d', $period['date_from']) . ' - ' . date('Y-m-d', $period['date_to']) . " ({$period['condo_id']['name']})";
        }
        return $result;
    }


    public static function policyCanBeClosed($self) {
        $result = [];
        return $result;
    }

    public static function onbeforeClose($self) {
        $self->do('generate_accounting_entries');
    }

    /**
     * Create accounting entries for closing  the period.
     *
     */
    public static function doGenerateAccountingEntries($self) {
        $self->read([
                'condo_id',
                'date_from',
                'date_to',
                'fiscal_year_id'
            ]);

        foreach($self as $id => $fiscalPeriod) {
            $miscJournal = Journal::search([['condo_id', '=', $fiscalPeriod['condo_id']], ['code', '=', 'MISC']])->first();
            if(!$miscJournal) {
                throw new \Exception('missing_misc_journal', EQ_ERROR_INVALID_CONFIG);
            }

            $expenseProvisionAccount = Account::search([['condo_id', '=', $fiscalPeriod['condo_id']], ['operation_assignment', '=', 'expense_provisions']])
                ->read(['id'])
                ->first();

            $accountingEntry = AccountingEntry::create([
                        'condo_id'          => $fiscalPeriod['condo_id'],
                        'journal_id'        => $miscJournal['id'],
                        'description'       => 'Extourne des provisions pour charges',
                        'is_temp'           => true,
                        'fiscal_year_id'    => $fiscalPeriod['fiscal_year_id'],
                        'entry_date'        => time()
                    ])
                    ->first();

            // #memo - execution can still be 'proforma' or can have been cancelled at some point in the period
            $requestExecutions = FundRequestExecution::search([
                    ['posting_date', '>=', $fiscalPeriod['date_from']],
                    ['posting_date', '<=', $fiscalPeriod['date_to']],
                    ['status', '=', 'invoice']
                ])
                ->read(['execution_lines_ids' => ['ownership_id', 'price']]);

            $map_ownership_amounts = [];
            foreach($requestExecutions as $requestExecution) {
                foreach($requestExecution['execution_lines_ids'] as $requestExecutionLine) {
                    $ownership_id = $requestExecutionLine['ownership_id'];
                    $map_ownership_amounts[$ownership_id] = ($map_ownership_amounts[$ownership_id] ?? 0) + $requestExecutionLine['line'];
                }
            }

            $ownerships = Ownership::ids(array_keys($map_ownership_amounts))->read(['ownership_account_id'])->get();

            foreach($map_ownership_amounts as $ownership_id => $amount) {

                // debit account 701
                AccountingEntryLine::create([
                        'condo_id'              => $fiscalPeriod['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'account_id'            => $expenseProvisionAccount['id'],
                        'debit'                 => $amount,
                        'credit'                => 0.0
                    ]);

                // credit owner account
                AccountingEntryLine::create([
                        'condo_id'              => $fiscalPeriod['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'account_id'            => $ownerships[$ownership_id]['ownership_account_id'],
                        'debit'                 => 0.0,
                        'credit'                => $amount
                    ]);

            }

            // validate accounting entry
            AccountingEntry::id($accountingEntry['id'])->transition('validate');
        }
    }

}