<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use equal\orm\Model;
use realestate\funding\ExpenseStatement;
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
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Order of the period, based on its date within the fiscal year.',
                'help'              => 'This value is assigned by parent Fiscal Year, and is needed for purchase invoice sequence numbering.',
                'dependents'        => ['name'],
                'store'             => true,
                'function'          => 'calcCode'
            ],

            'status' => [
                'type'        => 'string',
                'selection'   => [
                    'open',
                    'preclosed',
                    'closed'
                ],
                'default'     => 'open',
                'description' => 'Status of the accounting period.',
                'help'        => 'Status is `closed` once the expense statement has been validated.'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'open' => [
                'description' => 'Pending fiscal period, that can be used for recording new accounting entries.',
                'icon' => 'draw',
                'transitions' => [
                    'preclose' => [
                        'description' => 'Mark fiscal period as pre-closed.',
                        'help' => 'This transition creates a related expense statement draft (must still be validated).',
                        'policies' => [
                            'can_be_preclose'
                        ],
                        'onbefore' => 'onafterPreclose',
                        'status' => 'preclosed'
                    ],
                    'close' => [
                        'description' => 'Close the fiscal period.',
                        'help' => 'A fiscal period is meant to be closed the sooner at the day matching date stored in `date_to` field.',
                        'policies' => [
                            'can_close'
                        ],
                        'onbefore' => 'onbeforeClose',
                        'status' => 'closed'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return [
            'generate_expense_statement' => [
                'description'   => 'Generate the accounting entries for closing the fiscal period.',
                'policies'      => [],
                'function'      => 'doGenerateExpenseStatement'
            ],

            'generate_accounting_entries' => [
                'description'   => 'Generate the accounting entries for closing the fiscal period.',
                'policies'      => ['is_balanced', 'can_generate_executions'],
                'function'      => 'doGenerateAccountingEntries'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_close' => [
                'description' => 'Verifies that a fiscal period can be closed according its configuration.',
                'function'    => 'policyCanClose'
            ],
            'can_preclose' => [
                'description' => 'Verifies that a fiscal period can be pre-closed according its configuration.',
                'function'    => 'policyCanPreclose'
            ]
        ];
    }

    /**
     * s'il existe déjà, on le laisse (proforma)
     * sinon on le créé en brouillon
     */
    protected static function onafterPreclose($self) {
        $self->read(['condo_id', 'fiscal_year_id', 'date_from', 'date_to']);
        foreach($self as $id => $fiscalPeriod) {
            $existingExpenseStatement = ExpenseStatement::search([
                    ['condo_id', '=', $fiscalPeriod['condo_id']],
                    ['fiscal_year_id', '=', $fiscalPeriod['fiscal_year_id']],
                    ['fiscal_period_id', '=', $id]
                ])
                ->read(['status'])
                ->first();

            if($existingExpenseStatement) {
                continue;
            }

            // create a draft exepense statement if not exist
            ExpenseStatement::create([
                    'condo_id'          => $fiscalPeriod['condo_id'],
                    'fiscal_period_id'  => $id,
                    'request_date'      => time(),
                    'has_date_range'    => true,
                    'date_from'         => $fiscalPeriod['date_from'],
                    'date_to'           => $fiscalPeriod['date_to']
                ]);
        }

    }

    protected static function doGenerateExpenseStatement($self) {
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['code', 'date_from', 'date_to', 'condo_id' => ['name']]);
        foreach($self as $id => $period) {
            if(!$period['date_from'] || !$period['date_to']) {
                continue;
            }
            $result[$id] = (strlen($period['code']) > 0) ? ($period['code'] . ' - ') : '';
            $result[$id] .= date('Y-m-d', $period['date_from']) . ' - ' . date('Y-m-d', $period['date_to']) . " ({$period['condo_id']['name']})";
        }
        return $result;
    }

    protected static function calcCode($self) {
        $result = [];
        $self->read(['date_to', 'fiscal_year_id' => ['fiscal_periods_ids' => ['date_from']]]);
        foreach($self as $id => $fiscalPeriod) {
            $periods = $fiscalPeriod['fiscal_year_id']['fiscal_periods_ids'] ?? [];

            if(!is_array($periods) || !count($periods) || !$fiscalPeriod['date_to']) {
                continue;
            }

            $nb_greater = 0;
            foreach($periods as $period) {
                if(!isset($period['date_from'])) {
                    continue;
                }
                if($period['date_from'] > $fiscalPeriod['date_to']) {
                    ++$nb_greater;
                }
            }

            $code = count($periods) - $nb_greater;
            if($code > 0) {
                $result[$id] = $code;
            }
        }
        return $result;
    }

    /**
     * If an ExpenseStatement already exists for the period, it must be in draft (proforma) to allow pre-closing.
     * Parent fiscal Year must be in  ['preopen', 'open'].
     */
    protected static function policyCanPreclose($self) {
        $result = [];
        $self->read(['status', 'condo_id', 'date_from', 'date_to', 'fiscal_year_id' => ['status']]);

        foreach($self as $id => $fiscalPeriod) {

            if(!$fiscalPeriod['condo_id']) {
                $result[$id] = [
                    'missing_condo' => 'Condominium is mandatory.'
                ];
                continue;
            }
            if(!$fiscalPeriod['fiscal_year_id']) {
                $result[$id] = [
                    'missing_fiscal_year' => 'Fiscal Year is mandatory.'
                ];
                continue;
            }

            if(!$fiscalPeriod['date_from']) {
                $result[$id] = [
                    'missing_date_from' => 'Start date is mandatory.'
                ];
                continue;
            }

            if(!$fiscalPeriod['date_to']) {
                $result[$id] = [
                    'missing_date_to' => 'End date is mandatory.'
                ];
                continue;
            }

            if($fiscalPeriod['status'] === 'open') {
                $result[$id] = [
                    'invalid_status' => 'Period not open.'
                ];
                continue;
            }

            if(!in_array($fiscalPeriod['fiscal_year_id']['status'], ['preopen', 'open'])) {
                $result[$id] = [
                    'invalid_fiscal_year_status' => 'Fiscal Year must be open or preopen for a period to be closed.'
                ];
                continue;
            }

            $existingExpenseStatement = ExpenseStatement::search([
                    ['condo_id', '=', $fiscalPeriod['condo_id']],
                    ['fiscal_year_id', '=', $fiscalPeriod['fiscal_year_id']],
                    ['fiscal_period_id', '=', $id]
                ])
                ->read(['status'])
                ->first();

            if($existingExpenseStatement && $existingExpenseStatement['status'] !== 'proforma') {
                $result[$id] = [
                    'invalid_expense_statement_status' => 'An expense statement already exist for this period and must be in "proforma" status to preclose the period.'
                ];
                continue;
            }
        }
        return $result;
    }

    protected static function policyCanClose($self) {
        $result = [];
        $self->read(['status', 'fiscal_year_id' => ['status']]);
        foreach($self as $id => $fiscalPeriod) {
            if(!$fiscalPeriod['fiscal_year_id']) {
                $result[$id] = [
                    'missing_fiscal_year' => 'Fiscal Year is mandatory.'
                ];
                continue;
            }

            if(!in_array($fiscalPeriod['status'], ['open', 'preclosed'])) {
                $result[$id] = [
                    'invalid_status' => 'Period already closed.'
                ];
                continue;
            }
            if(!in_array($fiscalPeriod['fiscal_year_id']['status'], ['preopen', 'open'])) {
                $result[$id] = [
                    'invalid_fiscal_year_status' => 'Fiscal Year must be open or preopen for a period to be closed.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function onbeforeClose($self) {
        // #todo #memo - on fait ca dans les décompte de charge et/ou dans la FiscalYear
        // $self->do('generate_accounting_entries');
    }

    /**
     * Create accounting entries for closing the period.
     * #todo - to be completed
     *
     * - empty account expense_provisions with owners accounts
     *
     * il y a une question en cours sur la pertinence de faire cela (a priori uniquement 1) par tradition et 2) pour faciliter les calculs pour des infos sur des intervalles de dates arbitraires)
     */
    public static function doGenerateAccountingEntries($self) {
        $self->read([
                'condo_id',
                'date_from',
                'date_to',
                'fiscal_year_id'
            ]);

        foreach($self as $id => $fiscalPeriod) {
            $miscJournal = Journal::search([['condo_id', '=', $fiscalPeriod['condo_id']], ['journal_type', '=', 'MISC']])->first();
            if(!$miscJournal) {
                throw new \Exception('missing_misc_journal', EQ_ERROR_INVALID_CONFIG);
            }

            // retrieve expense provision account
            $expenseProvisionAccount = Account::search([['condo_id', '=', $fiscalPeriod['condo_id']], ['operation_assignment', '=', 'expense_provisions']])
                ->read(['id'])
                ->first();

            // #memo - execution can still be 'proforma' or can have been cancelled at some point in the period
            $requestExecutions = FundRequestExecution::search([
                    ['posting_date', '>=', $fiscalPeriod['date_from']],
                    ['posting_date', '<=', $fiscalPeriod['date_to']],
                    ['status', '=', 'posted']
                ])
                ->read([
                    'fund_request_id' => ['request_type'],
                    'execution_lines_ids' => ['ownership_id', 'price']
                ]);

            $map_ownership_amounts = [];
            foreach($requestExecutions as $requestExecution) {
                if($requestExecution['fund_request_id']['request_type'] !== 'expense_provisions') {
                    continue;
                }
                foreach($requestExecution['execution_lines_ids'] as $requestExecutionLine) {
                    $ownership_id = $requestExecutionLine['ownership_id'];
                    $map_ownership_amounts[$ownership_id] = ($map_ownership_amounts[$ownership_id] ?? 0) + $requestExecutionLine['line'];
                }
            }

            if(count($map_ownership_amounts)) {
                $accountingEntry = AccountingEntry::create([
                        'condo_id'          => $fiscalPeriod['condo_id'],
                        'journal_id'        => $miscJournal['id'],
                        'description'       => 'Extourne des provisions pour charges',
                        'is_temp'           => true,
                        'fiscal_year_id'    => $fiscalPeriod['fiscal_year_id'],
                        'entry_date'        => time()
                    ])
                    ->first();

                foreach($map_ownership_amounts as $ownership_id => $amount) {
                    $ownershipAccount = Account::search([
                            ['condo_id', '=', $fiscalPeriod['condo_id']],
                            ['ownership_id', '=', $ownership_id],
                            ['operation_assignment', '=', 'co_owners_working_fund']
                        ])
                        ->first();

                    if(!$ownershipAccount) {
                        throw new \Exception('missing_suppliership_accounting_account', EQ_ERROR_INVALID_PARAM);
                    }

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
                            'account_id'            => $ownershipAccount['id'],
                            'debit'                 => 0.0,
                            'credit'                => $amount
                        ]);

                }

                // validate accounting entry
                AccountingEntry::id($accountingEntry['id'])->transition('validate');
            }

        }
    }

}
