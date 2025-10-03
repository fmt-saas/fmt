<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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

            'bank_account_type' => [
                'type'              => 'string',
                'description'       => 'Type of bank account (current of savings).',
                'help'              => 'Identifiers of this list should match the operation_assignment codes used in the chart of Accounts.',
                'default'           => 'bank_current'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'description'       => "Ownership the bank account belongs to.",
                'required'          => true,
                'dependents'        => ['ownership_code', 'description']
            ],

            'description' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['ownership_id' => 'name'],
                'description'       => 'Short description of the account (purpose).',
            ],

            'ownership_code' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'description'       => "Unique code of the Ownership.",
                'relation'          => ['ownership_id' => 'code'],
                'store'             => true
            ],

            // #todo - an ownership is not directly linked to an identity
            'owner_identity_id' => [
                'type'              => 'many2one',
                'description'       => "The Identity the bank account in attached to.",
                'foreign_object'    => 'identity\Identity'
            ],

            'accounting_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'function'          => 'calcAccountingAccountId',
                'store'             => true,
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'is_validated' => [
                'type'              => 'boolean',
                'description'       => "The bank account has been confirmed by the Owner(ship).",
                'help'              => "Validation is intended to be made by the Owner through the self-service platform. This is used to limit reimbursement to Ownership bank account that have been validated",
                'default'           => false
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
