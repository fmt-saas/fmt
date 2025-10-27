<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use finance\accounting\Account;
use finance\accounting\CurrentBalanceLine;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use realestate\property\Condominium;
use realestate\sale\pay\Funding;

class CondominiumBankAccount extends BankAccount {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                //'readonly'          => true
                'visible'           => ['organisation_id', '=', null],
                'dependents'        => ['condominium_identity_id'],
                'onupdate'          => 'onupdateCondoId'
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Explicit class name of the object.',
                'help'              => 'This is necessary to distinguish between different types of bank accounts since class uses same table as BankAccount.', 
                'readonly'          => true,
                'default'           => 'finance\bank\CondominiumBankAccount',
            ],

            'bank_account_type' => [
                'type'              => 'string',
                'description'       => 'Type of bank account (current of savings).',
                'help'              => 'Identifiers of this list should match the operation_assignment codes used in the chart of Accounts.',
                'selection'         => [
                    'bank_current',
                    'bank_savings',
                    'bank_tier',
                    // 'bank_loan',
                ],
                'default'           => 'bank_current'
            ],

            // #todo - replace bank_account_iban with 'iban' as computed (depends if account is tier or not)
            // search on CondominiumBankAccount::
            'condominium_identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The condominium identity this bank account belongs to.",
                'relation'          => ['condo_id' => 'identity_id'],
                'store'             => true
            ],

            'accounting_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['operation_assignment', '=', 'object.bank_account_type']]
            ],

            'last_statement_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'default'           => 0.0
            ],

            'last_statement_date' => [
                'type'              => 'date',
                'description'       => 'Date of the last imported bank statement.',
            ],

            'last_statement_id' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\BankStatement',
                'description'       => 'The last imported bank statement for this account.',
                'domain'            => ['bank_account_id', '=', 'object.id'],
            ],

            'bank_statements_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\BankStatement',
                'foreign_field'     => 'bank_account_id',
                'description'       => 'The lines that are assigned to the statement.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'current_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'function'          => 'calcCurrentBalance',
            ],

            'available_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'function'          => 'calcAvailableBalance',
            ],

            'is_primary' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the account as primary account of the Condominium.',
                'help'              => 'When a primary account is updated, sync is automatically replicated on related identity (from `owner_identity_id`).',
                'default'           => false,
                'visible'           => ['bank_account_type', '=', 'bank_current']
            ],

            'is_primary_reserve' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the account as primary reserve funds account.',
                'default'           => false,
                'visible'           => ['bank_account_type', '=', 'bank_savings']
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'description'       => "The Identity the bank account in attached to.",
                'foreign_object'    => 'identity\Identity',
                'domain'            => ['id', '=', 'object.condominium_identity_id']
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the Bank Account.',
                'selection'         => [
                    'pending',
                    'validated'
                ],
                'default'           => 'pending'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Condominium with details being completed, waiting to be validated.',
                'icon'        => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the Bank Account to `validated`.',
                        'policies'    => ['is_valid'],
                        'onafter'     => 'onafterValidate',
                        'status'      => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Validated Bank Account.',
                'icon'        => 'done',
                'transitions' => [
                ]
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the mandatory values are present for Condominium validation.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    public static function getActions() {
        return [
            'generate_accounts' => [
                'description'   => 'Generate mandatory accounting Accounts for Ownership.',
                'policies'      => [],
                'function'      => 'doGenerateAccounts'
            ]
        ];
    }

    protected static function onafterValidate($self) {
        $self
            ->read(['condo_id'])
            ->do('generate_accounts');

        // update sequences for existing fiscal years
        foreach($self as $id => $condominiumBankAccount) {
            FiscalYear::search(['condo_id', '=', $condominiumBankAccount['condo_id']])
                ->do('generate_sequences');
        }

    }

    protected static function policyIsValid($self) {
        $result = [];
        $self->read(['condo_id', 'managing_agent_id']);
        foreach($self as $id => $condominium) {

            if(!$condominium['condo_id']) {
                $result[$id] = [
                    'missing_cond_id' => 'The condominium must be provided.'
                ];
            }

        }
    }

    protected static function onupdateCondoId($self) {
        $self->read(['condo_id']);
        foreach($self as $id => $condominiumBankAccount) {
            Condominium::id($condominiumBankAccount['condo_id'])->do('sync_bank_suppliers');
        }
    }

    protected static function doGenerateAccounts($self) {
        $self->read(['condo_id', 'bank_account_type', 'name']);
        foreach($self as $id => $bankAccount) {
            if(!$bankAccount['condo_id']) {
                continue;
            }

            // find the account based on operation_assignment to use it as "template"
            $assignmentAccount = Account::search([
                    ['condo_id', '=', $bankAccount['condo_id']],
                    ['operation_assignment', '=', $bankAccount['bank_account_type']],
                    ['condo_bank_account_id', '=', null]
                ])
                ->read(['code', 'account_category', 'account_chart_id'])
                ->first();

            if(!$assignmentAccount) {
                trigger_error("APP::Could not find account candidate for condominium {$bankAccount['condo_id']} for operation assignment {$bankAccount['bank_account_type']}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_account", EQ_ERROR_INVALID_CONFIG);
            }

            $account_exists = (bool) count(Account::search([['condo_bank_account_id', '=', $id], ['condo_id', '=', $bankAccount['condo_id'] ]])->ids());

            if(!$account_exists) {
                $index = Account::search([
                        ['condo_id', '=', $bankAccount['condo_id']],
                        ['operation_assignment', '=', $bankAccount['bank_account_type']],
                        ['condo_bank_account_id', '<>', null]
                    ])
                    ->count();

                $account = Account::create([
                        'code'                  => $assignmentAccount['code'] . sprintf("%02d", $index + 1),
                        'condo_id'              => $bankAccount['condo_id'],
                        'ownership_id'          => $id,
                        'parent_account_id'     => $assignmentAccount['id'],
                        'account_chart_id'      => $assignmentAccount['account_chart_id'],
                        'account_category'      => $assignmentAccount['account_category'],
                        'description'           => $bankAccount['name'],
                        'operation_assignment'  => $bankAccount['bank_account_type'],
                        'condo_bank_account_id' => $id
                    ])
                    ->read(['name'])
                    ->first();

                $parentJournal = Journal::search([['condo_id', '=', $bankAccount['condo_id']], ['journal_type', '=', 'BANK'], ['has_parent', '=', false]])
                    ->read(['code', 'sub_journals_ids'])
                    ->first();

                if($parentJournal) {
                    $journal_code = $parentJournal['code'] . '/' . (count($parentJournal['sub_journals_ids']) + 1);
                    Journal::create([
                            'condo_id'              => $bankAccount['condo_id'],
                            'code'                  => $journal_code,
                            'description'           => $bankAccount['name'],
                            'journal_type'          => 'BANK',
                            'has_parent'            => true,
                            'parent_journal_id'     => $parentJournal['id'],
                            'accounting_account_id' => $account['id']
                        ]);
                }
                self::id($id)->update(['accounting_account_id' => $account['id']]);
            }

        }
    }

    protected static function calcCurrentBalance($self) {
        $result = [];
        $self->read(['accounting_account_id', 'bank_account_type', 'condo_id' => ['id', 'current_fiscal_year_id']]);
        foreach($self as $id => $bankAccount) {
            $balance = 0.0;

            $balanceLines = CurrentBalanceLine::search([
                    ['condo_id', '=', $bankAccount['condo_id']['id']],
                    ['fiscal_year_id', '=', $bankAccount['condo_id']['current_fiscal_year_id']],
                    ['account_id', '=', $bankAccount['accounting_account_id']]
                ])
                ->read(['debit', 'credit']);

            foreach($balanceLines as $balanceLine) {
                $balance += $balanceLine['debit'];
                $balance -= $balanceLine['credit'];
            }

            $result[$id] = $balance;
        }
        return $result;
    }


    /**
     * utilisé pour donner une idée des capacités de paiement
     */
    protected static function calcAvailableBalance($self) {
        $result = [];
        $self->read(['current_balance', 'condo_id']);
        foreach($self as $id => $bankAccount) {
            $balance = $bankAccount['current_balance'] ?? 0.0;

            $fundings = Funding::search([
                    [
                        ['condo_id', '=', $bankAccount['condo_id']],
                        ['status', '<>', 'balanced'],
                        ['funding_type', '=', 'transfer'],
                        ['due_amount', '<', 0.0],
                        ['bank_account_id', '=', $id]
                    ]
                ])
                ->read(['due_amount', 'paid_amount']);

            foreach($fundings as $funding) {
                $balance += $funding['due_amount'] - $funding['paid_amount'];
            }

            $result[$id] = $balance;
        }
        return $result;
    }

    private static function computeAccountingAccount($bank_account_type, $condo_id) {
        if($condo_id && $bank_account_type) {
            $account = Account::search([ ['condo_id', '=', $condo_id], ['operation_assignment', '=', $bank_account_type] ])->read(['id', 'name'])->first();
            if($account) {
                return [
                    'id'    => $account['id'],
                    'name'  => $account['name']
                ];
            }
        }
        return null;
    }


    // #todo -to complete
    public static function candelete($self) {
        $self->read(['is_primary']);
        foreach($self as $bankAccount) {
            if($bankAccount['is_primary']) {
                return ['id' => ['non_removable' => 'The primary bank account cannot be removed. Organizations must have at least one bank account.']];
            }
        }
        return parent::candelete($self);
    }

}
