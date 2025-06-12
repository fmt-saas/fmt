<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\bank;

use finance\accounting\Account;


class OwnershipBankAccount extends BankAccount {

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

            'ownership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'description'       => "Ownership the bank account belongs to.",
                'required'          => true,
                'dependents'        => ['ownership_code']
            ],

            'ownership_code' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'description'       => "Unique code of the Ownership.",
                'relation'          => ['ownership_id' => 'code'],
                'store'             => true
            ],

            'accounting_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'function'          => 'calcAccountingAccountId',
                'store'             => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ]

        ];
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
