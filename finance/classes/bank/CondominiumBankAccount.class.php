<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use finance\accounting\Account;
use finance\accounting\CurrentBalanceLine;
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
                'dependents'        => ['condominium_identity_id', 'accounting_account_id']
            ],

            'bank_account_type' => [
                'type'              => 'string',
                'description'       => 'Type of bank account (current of savings).',
                'help'              => 'Identifiers of this list should match the operation_assignment codes used in the chart of Accounts.',
                'selection'         => [
                    'bank_current',
                    'bank_savings'
                ],
                'default'           => 'bank_current',
                'dependents'        => ['accounting_account_id']
            ],

            'condominium_identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The condominium identity this bank account belongs to.",
                'relation'          => ['condo_id' => 'identity_id'],
                'store'             => true
            ],

            'accounting_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'function'          => 'calcAccountingAccountId',
                'store'             => true,
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
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
            ]

        ];
    }

    protected static function calcCurrentBalance($self) {
        $result = [];
        $self->read(['accounting_account_id', 'bank_account_type', 'condo_id' => ['id', 'current_fiscal_year_id']]);
        foreach($self as $id => $bankAccount) {
            $balance = 0.0;

            $balanceLines = CurrentBalanceLine::search([['condo_id', '=', $bankAccount['condo_id']['id']], ['fiscal_year_id', '=', $bankAccount['condo_id']['current_fiscal_year_id']], ['account_id', '=', $bankAccount['accounting_account_id']]])->read(['debit', 'credit']);
            foreach($balanceLines as $balanceLine) {
                $balance += $balanceLine['debit'];
                $balance -= $balanceLine['credit'];
            }

            $result[$id] = $balance;
        }
        return $result;
    }

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

    // #todo - add support for multiple accounts of the same type
    // #memo - for now, only a single account is handled for each account type (current, saving, loan)
    protected static function calcAccountingAccountId($self) {
        $result = [];
        $self->read(['bank_account_type', 'bank_account_iban', 'condo_id']);
        foreach($self as $id => $bankAccount) {
            if($bankAccount['condo_id'] && $bankAccount['bank_account_type']) {
                $account = self::computeAccountingAccount($bankAccount['bank_account_type'], $bankAccount['condo_id']);
                if($account) {
                    $result[$id] = $account['id'];
                }
            }
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
