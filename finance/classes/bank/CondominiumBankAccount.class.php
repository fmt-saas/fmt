<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\bank;

use finance\accounting\Account;
use finance\accounting\CurrentBalanceLine;
use sale\pay\Funding;

class CondominiumBankAccount extends BankAccount {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                //'readonly'          => true
                'visible'           => ['organisation_id', '=', null],
                'dependents'        => ['accounting_account_id']
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

            'accounting_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'function'          => 'calcAccountingAccountId',
                'store'             => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'available_balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'function'          => 'calcAvailableBalance',
            ]

        ];
    }

    protected static function calcAvailableBalance($self) {
        $result = [];
        $self->read(['accounting_account_id', 'bank_account_type', 'condo_id']);
        foreach($self as $id => $bankAccount) {
            $balance = 0.0;
            $balanceLine = CurrentBalanceLine::search(['account_id', '=', $bankAccount['accounting_account_id']])->read(['debit', 'credit'])->first();
            if($balanceLine) {
                $balance += $balanceLine['debit'];
            }
            $fundings = Funding::search([ ['condo_id', '=', $bankAccount['condo_id']], ['bank_account_id', '=', $id], ['funding_type', '=', 'transfer'], ['status', '<>', 'balanced'] ])
                ->read(['due_amount']);

            foreach($fundings as $funding) {
                $balance -= $funding['due_amount'];
            }

            $result[$id] = $balance;
        }
        return $result;
    }

    protected static function calcAccountingAccountId($self) {
        $result = [];
        $self->read(['bank_account_type', 'condo_id']);
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
