<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use equal\orm\Model;
use finance\accounting\Account;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;

/*
    #memo - an ownership bank account is a contextual link between an Ownership
    and a BankAccount owned by one of its owners or by its external representative.
*/
class OwnershipBankAccount extends Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => 'The condominium the ownership belongs to.',
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'dependents'        => ['accounting_account_id']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of the linked bank account.',
                'relation'          => ['bank_account_id' => ['name']],
                'instant'           => true,
                'store'             => true,
                'readonly'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'description'       => 'Ownership the bank account is available for.',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true,
                'dependents'        => ['description', 'ownership_code', 'allowed_owner_identity_ids']
            ],

            'description' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['ownership_id' => 'name'],
                'description'       => 'Display name of the ownership.',
                'store'             => true,
                'readonly'          => true
            ],

            'ownership_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Unique code of the ownership.',
                'relation'          => ['ownership_id' => 'code'],
                'store'             => true,
                'readonly'          => true
            ],

            'allowed_owner_identity_ids' => [
                'type'              => 'computed',
                'result_type'       => 'one2many',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Identities allowed to own the linked bank account.',
                'function'          => 'calcAllowedOwnerIdentityIds',
                'store'             => false,
                'instant'           => true,
                'readonly'          => true
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'description'       => 'Owner or external representative that owns the bank account.',
                'foreign_object'    => 'identity\Identity',
                'domain'            => ['id', 'in', 'object.allowed_owner_identity_ids'],
                'required'          => true
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'Bank account made available to the ownership.',
                'help'              => 'The account must belong to an owner or to the external representative of the ownership.',
                'domain'            => [
                    ['owner_identity_id', '=', 'object.owner_identity_id'],
                    ['is_active', '=', true]
                ],
                'required'          => true,
                'onupdate'          => 'onupdateBankAccountId',
                'dependents'        => ['accounting_account_id']
            ],

            'bank_account_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Type of the linked bank account.',
                'relation'          => ['bank_account_id' => ['bank_account_type']],
                'store'             => true,
                'readonly'          => true
            ],

            'bank_account_iban' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'IBAN of the linked bank account.',
                'relation'          => ['bank_account_id' => ['bank_account_iban']],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'bank_account_bic' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'BIC of the linked bank account.',
                'relation'          => ['bank_account_id' => ['bank_account_bic']],
                'store'             => true,
                'readonly'          => true
            ],

            'bank_account_suffix' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Suffix of the linked bank account.',
                'relation'          => ['bank_account_id' => ['bank_account_suffix']],
                'store'             => true,
                'readonly'          => true
            ],

            'bank_country' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'Country of the linked bank account.',
                'relation'          => ['bank_account_id' => ['bank_country']],
                'store'             => true,
                'readonly'          => true
            ],

            'bank_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Name of the bank holding the linked account.',
                'relation'          => ['bank_account_id' => ['bank_name']],
                'store'             => true,
                'readonly'          => true
            ],

            'bank_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\bank\Bank',
                'description'       => 'Bank holding the linked account.',
                'relation'          => ['bank_account_id' => ['bank_id']],
                'store'             => true,
                'readonly'          => true
            ],

            'is_active' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag indicating whether the linked bank account is active.',
                'relation'          => ['bank_account_id' => ['is_active']],
                'store'             => true,
                'readonly'          => true
            ],

            'is_primary' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the account as primary for the ownership.',
                'default'           => false
            ],

            'accounting_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => 'Accounting account associated with the bank account type.',
                'function'          => 'calcAccountingAccountId',
                'store'             => true,
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null]
                ]
            ],

            'is_validated' => [
                'type'              => 'boolean',
                'description'       => 'Flag indicating that the ownership confirmed the bank account.',
                'help'              => 'Only confirmed accounts should be used for ownership reimbursements.',
                'default'           => false
            ]
        ];
    }

    public static function getActions() {
        return [
            'refresh_bank_account_fields' => [
                'description'   => 'Refresh fields copied from the linked bank account.',
                'policies'      => [],
                'function'      => 'doRefreshBankAccountFields'
            ],
            'validate_owner_identity' => [
                'description'   => 'Validate the ownership, owner identity and bank account relationship.',
                'policies'      => [],
                'function'      => 'doValidateOwnerIdentity'
            ],
            'ensure_primary_account' => [
                'description'   => 'Ensure that only one primary account exists per ownership and account type.',
                'policies'      => [],
                'function'      => 'doEnsurePrimaryAccount'
            ]
        ];
    }

    public function getUniques(): array {
        return [
            ['condo_id', 'ownership_id', 'bank_account_id']
        ];
    }

    protected static function onafterinstantiate($self) {
        $self
            ->do('validate_owner_identity')
            ->do('ensure_primary_account');
    }

    protected static function onbeforeupdate($self, $values) {
        if(
            array_key_exists('ownership_id', $values)
            || array_key_exists('owner_identity_id', $values)
            || array_key_exists('bank_account_id', $values)
        ) {
            $self->do('validate_owner_identity', ['values' => $values]);
        }
    }

    protected static function onupdateBankAccountId($self) {
        $self->do('refresh_bank_account_fields');
    }

    protected static function onafterupdate($self, $values) {
        if(isset($values['is_primary']) && $values['is_primary']) {
            $self->do('ensure_primary_account');
        }
    }

    protected static function doRefreshBankAccountFields($self) {
        $self->update([
            'name'                  => null,
            'bank_account_type'     => null,
            'bank_account_iban'     => null,
            'bank_account_bic'      => null,
            'bank_account_suffix'   => null,
            'bank_country'          => null,
            'bank_name'             => null,
            'bank_id'               => null,
            'is_active'             => null,
            'accounting_account_id' => null
        ]);
    }

    protected static function doValidateOwnerIdentity($self, $values=[]) {
        $self->read(['ownership_id', 'owner_identity_id', 'bank_account_id']);

        foreach($self as $ownershipBankAccount) {
            $ownership_id = $values['ownership_id'] ?? $ownershipBankAccount['ownership_id'];
            $owner_identity_id = $values['owner_identity_id'] ?? $ownershipBankAccount['owner_identity_id'];
            $bank_account_id = $values['bank_account_id'] ?? $ownershipBankAccount['bank_account_id'];

            if(!self::isAllowedOwnerIdentity($ownership_id, $owner_identity_id)) {
                throw new \Exception('invalid_owner_identity_id', EQ_ERROR_INVALID_PARAM);
            }

            $bankAccount = BankAccount::id($bank_account_id)
                ->read(['owner_identity_id'])
                ->first();

            if(!$bankAccount || $bankAccount['owner_identity_id'] !== $owner_identity_id) {
                throw new \Exception('invalid_bank_account_owner', EQ_ERROR_INVALID_PARAM);
            }
        }
    }

    protected static function doEnsurePrimaryAccount($self) {
        $self->read(['ownership_id', 'bank_account_type', 'is_primary']);

        foreach($self as $id => $ownershipBankAccount) {
            if(!$ownershipBankAccount['is_primary']) {
                continue;
            }

            OwnershipBankAccount::search([
                    ['id', '<>', $id],
                    ['ownership_id', '=', $ownershipBankAccount['ownership_id']],
                    ['bank_account_type', '=', $ownershipBankAccount['bank_account_type']],
                    ['is_primary', '=', true]
                ])
                ->update(['is_primary' => false]);
        }
    }

    protected static function calcAllowedOwnerIdentityIds($self) {
        $result = [];
        $self->read(['ownership_id']);

        foreach($self as $id => $ownershipBankAccount) {
            $result[$id] = self::getAllowedOwnerIdentityIds($ownershipBankAccount['ownership_id']);
        }

        return $result;
    }

    protected static function calcAccountingAccountId($self) {
        $result = [];
        $self->read([
            'condo_id',
            'bank_account_id' => ['bank_account_type']
        ]);

        foreach($self as $id => $ownershipBankAccount) {
            if(!$ownershipBankAccount['condo_id'] || !$ownershipBankAccount['bank_account_id']) {
                continue;
            }

            $bank_account_type = $ownershipBankAccount['bank_account_id']['bank_account_type'] ?? null;
            if(!$bank_account_type) {
                continue;
            }

            $account = Account::search([
                    ['condo_id', '=', $ownershipBankAccount['condo_id']],
                    ['operation_assignment', '=', $bank_account_type]
                ])
                ->first();

            if($account) {
                $result[$id] = $account['id'];
            }
        }

        return $result;
    }

    private static function isAllowedOwnerIdentity($ownership_id, $identity_id): bool {
        if(!$ownership_id || !$identity_id) {
            return false;
        }

        return in_array($identity_id, self::getAllowedOwnerIdentityIds($ownership_id), true);
    }

    private static function getAllowedOwnerIdentityIds($ownership_id): array {
        if(!$ownership_id) {
            return [];
        }

        $owners = Owner::search([
                ['ownership_id', '=', $ownership_id],
                ['identity_id', '<>', null]
            ])
            ->read(['identity_id']);

        $result = [];
        foreach($owners as $owner) {
            if($owner['identity_id']) {
                $result[] = $owner['identity_id'];
            }
        }

        $ownership = Ownership::id($ownership_id)
            ->read(['has_external_representative', 'representative_identity_id'])
            ->first();

        if(
            $ownership
            && $ownership['has_external_representative']
            && $ownership['representative_identity_id']
        ) {
            $result[] = $ownership['representative_identity_id'];
        }

        return array_values(array_unique($result));
    }

    public static function onchange($event, $values) {
        $result = [];

        if(array_key_exists('owner_identity_id', $event)) {
            $result['bank_account_id'] = null;
        }

        if(isset($event['bank_account_id'])) {
            $bankAccount = BankAccount::id($event['bank_account_id'])
                ->read([
                    'name',
                    'bank_account_type',
                    'bank_account_iban',
                    'bank_account_bic',
                    'bank_account_suffix',
                    'bank_country',
                    'bank_name',
                    'bank_id',
                    'is_active'
                ])
                ->first();

            if($bankAccount) {
                foreach([
                    'name',
                    'bank_account_type',
                    'bank_account_iban',
                    'bank_account_bic',
                    'bank_account_suffix',
                    'bank_country',
                    'bank_name',
                    'bank_id',
                    'is_active'
                ] as $field) {
                    $result[$field] = $bankAccount[$field] ?? null;
                }
            }
        }

        return $result;
    }

    public static function candelete($self) {
        $self->read(['is_primary']);
        foreach($self as $ownershipBankAccount) {
            if($ownershipBankAccount['is_primary']) {
                return [
                    'id' => [
                        'non_removable' => 'The primary ownership bank account cannot be removed.'
                    ]
                ];
            }
        }

        return parent::candelete($self);
    }

}
