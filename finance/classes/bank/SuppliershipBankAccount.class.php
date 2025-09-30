<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use equal\orm\Model;

// #memo - since a same supplier and a same account can be used across several Condominiums, this class does not inherit from `finance\bank\BankAccount` but has a dedicated field `bank_account_id`
class SuppliershipBankAccount extends Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the Suppliership refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "IBAN of the targeted bank account.",
                'relation'          => ['bank_account_id' => ['name']],
                'instant'           => true,
                'store'             => true
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'description'       => 'The supplier the invoice relates to.',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true
            ],

            'is_primary' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the account as primary account.',
                'default'           => false
            ],

            'supplier_identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'The supplier the invoice relates to.',
                'relation'          => ['suppliership_id' => ['supplier_id' => 'identity_id']],
                'store'             => true,
                'instant'           => true
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'description'       => "The Identity the bank account in attached to.",
                'foreign_object'    => 'identity\Identity',
                'domain'            => ['id', '=', 'object.supplier_identity_id']
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'The bank account of the supplier to be used.',
                'help'              => 'This field and derived computed fields are used to identify paiements.',
                // #memo - it must be possible to select the bank account of another supplier (IBAN) ex. refactoring
                // 'domain'            => ['owner_identity_id', '=', 'object.supplier_identity_id'],
                'required'          => true,
                'onupdate'          => 'onupdateBankAccountId'
            ],

            'bank_account_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Type of bank account (current of savings).',
                'help'              => 'Identifiers of this list should match the operation_assignment codes used in the chart of Accounts.',
                'relation'          => ['bank_account_id' => ['bank_account_type']],
                'store'             => true,
                'readonly'          => true
            ],

            'bank_account_iban' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'The IBAN number of the bank account.',
                'help'              => 'The IBAN number is a unique identifier for the bank account. Example: BE54000000000097',
                'relation'          => ['bank_account_id' => ['bank_account_iban']],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'bank_account_bic' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The BIC code of the bank related to the organization\'s bank account.',
                'relation'          => ['bank_account_id' => ['bank_account_bic']],
                'store'             => true,
                'readonly'          => true
            ]

        ];
    }

    public static function getActions() {
        return [
            'refresh_bank_account_fields' => [
                'description'   => 'Open the fiscal year.',
                'function'      => 'doRefreshBankAccountFields'
            ],
        ];
    }

    protected static function onupdateBankAccountId($self) {
        $self->do('doRefreshBankAccountFields');
    }

    protected static function doRefreshBankAccountFields($self) {
        $self->update([
            'name'              => null,
            'bank_account_type' => null,
            'bank_account_iban' => null,
            'bank_account_bic'  => null
        ]);
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['bank_account_id'])) {
            $bankAccount = BankAccount::id($event['bank_account_id'])->read(['bank_account_type', 'bank_account_iban', 'bank_account_bic'])->first();
            $result['bank_account_iban'] = $bankAccount['bank_account_iban'];
            $result['bank_account_bic'] = $bankAccount['bank_account_bic'];
            $result['bank_account_type'] = $bankAccount['bank_account_type'];
        }
        return $result;
    }

    public function getUnique() {
        return [
            ['condo_id', 'suppliership_id', 'bank_account_id']
        ];
    }

}
