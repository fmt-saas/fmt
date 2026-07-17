<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\ownership;

use hr\role\Role;
use hr\role\RoleAssignment;
use identity\Identity;

class Owner extends Identity {

    public function getTable() {
        return 'realestate_ownership_owner';
    }

    public static function getDescription() {
        return "Individual owner from ownership.";
    }

    public static function getColumns() {

        return [
            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current entity .',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'realestate\ownership\Owner'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the Owner.",
                'relation'          => ['identity_id' => 'name'],
                'store'             => true,
                'readonly'          => true,
                'onrevert'          => 'onrevertName'
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                // 'required'          => true,
                'readonly'          => true
            ],

            'vat_number' => [
                'type'              => 'string',
                'usage'             => 'text/plain:14{10,14}',
                'description'       => 'Value Added Tax identification number, if any.',
                'visible'           => [ ['has_vat', '=', true], ['type', '<>', 'IN'], ['has_parent', '=', false] ],
                'onupdate'          => 'onupdateVatNumber',
                'help'              => 'There might be several owners pointing to the same Identity, therefore vat_number can be duplicated.',
                'unique'            => false
            ],

            'registration_number' => [
                'type'              => 'string',
                'description'       => 'Organization registration number (company number).',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'dependents'        => ['hash_sha256'],
                'onupdate'          => 'onupdateRegistrationNumber',
                'help'              => 'There might be several owners pointing to the same Identity, therefore vat_number can be duplicated.',
                'unique'            => false
            ],

            'citizen_identification' => [
                'type'              => 'string',
                'usage'             => 'text/plain:30',
                'description'       => 'Citizen registration number, if any.',
                'visible'           => [ ['type', '=', 'IN'] ],
                'dependents'        => ['hash_sha256'],
                'onupdate'          => 'onupdateCitizenIdentification',
                'help'              => 'There might be several owners pointing to the same Identity, therefore vat_number can be duplicated.',
                'unique'            => false
            ],

            'date_to' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'relation'          => ['ownership_id' => 'date_to'],
                'store'             => true,
                'instant'           => true,
                'description'       => "Date at which the last owned lot was sold by the owners.",
                'help'              => "If set, targeted owner no longer own any lot in the condominium. But we keep it for consistency and historical purposes.",
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Identity the object relates to.',
                'help'              => 'Meant for entities that inherit from `identity\Identity` and must be synced with parent Identity. Classes that inherit from Identity must implement `onupdateIdentityId()` method.',
                'onupdate'          => 'onupdateIdentityId',
                'domain'            => ['type_id', '=', 1],
                'visible'           => ['object_class', '<>', 'identity\Identity']
            ],

            'user_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\User',
                'relation'          => ['identity_id' => 'user_id'],
                'store'             => false,
                'description'       => "User the Owner relates to, if any (through identity)."
            ],

            'shares_full_property' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Amount of shares the owner has on the ownership.",
                'default'           => 1
            ],

            'shares_bare_property' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Amount of shares the owner has on the ownership.",
                'default'           => 0
            ],

            'shares_usufruct' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Amount of shares the owner has on the ownership.",
                'default'           => 0
            ],

            'broadcasts_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'communication\broadcast\Broadcast',
                'foreign_field'     => 'owners_ids',
                'rel_table'         => 'realestate_owner_rel_broadcast',
                'rel_foreign_key'   => 'broadcast_id',
                'rel_local_key'     => 'owner_id',
                'description'       => 'Broadcasts to send to the owner identity email address.'
            ]

        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'assert_identity' => [
                'description'   => 'Create or assign the related Identity when missing.',
                'policies'      => [],
                'function'      => 'doAssertIdentity'
            ],
            'refresh_roles' => [
                'description'   => 'Refresh roles assignments based on related User account.',
                'policies'      => [],
                'function'      => 'doRefreshRoles'
            ]
        ]);
    }

    protected static function oninstantiate($self, $orm, $values=[]) {
        if(isset($values['identity_id']) && $values['identity_id']) {
            $self->do('refresh_roles');
            return;
        }
        $self->do('assert_identity');
    }

    protected static function doAssertIdentity($self) {
        static $identity_fields = [
            'source',
            'source_type',
            'type_id',
            'legal_name',
            'short_name',
            'firstname',
            'lastname',
            'gender',
            'title',
            'date_of_birth',
            'lang_id',
            'has_parent',
            'parent_id',
            'has_vat',
            'vat_number',
            'registration_number',
            'citizen_identification',
            'nationality',
            'bank_account_iban',
            'bank_account_bic',
            'email',
            'email_alt',
            'phone',
            'phone_alt',
            'mobile',
            'website',
            'address_street',
            'address_dispatch',
            'address_zip',
            'address_city',
            'address_state',
            'address_country'
        ];

        $self->read(array_merge(['identity_id'], $identity_fields));

        foreach($self as $id => $owner) {
            if($owner['identity_id']) {
                continue;
            }

            $identity_values = [];
            foreach($identity_fields as $field) {
                if(!array_key_exists($field, $owner)) {
                    continue;
                }
                if($owner[$field] === null || $owner[$field] === '') {
                    continue;
                }
                $identity_values[$field] = $owner[$field];
            }

            $identity_id = null;

            if(!$identity_id && !empty($identity_values['vat_number'])) {
                $identity = Identity::search(['vat_number', '=', $identity_values['vat_number']])->first();
                if($identity) {
                    $identity_id = $identity['id'];
                }
            }

            if(!$identity_id && !empty($identity_values['registration_number'])) {
                $identity = Identity::search(['registration_number', '=', $identity_values['registration_number']])->first();
                if($identity) {
                    $identity_id = $identity['id'];
                }
            }

            if(!$identity_id && !empty($identity_values['citizen_identification'])) {
                $identity = Identity::search(['citizen_identification', '=', $identity_values['citizen_identification']])->first();
                if($identity) {
                    $identity_id = $identity['id'];
                }
            }

            if(!$identity_id) {
                $identity = Identity::create($identity_values)
                    ->do('refresh_bank_accounts')
                    ->do('refresh_addresses')
                    ->first();

                if($identity) {
                    $identity_id = $identity['id'];
                }
            }

            if($identity_id) {
                self::id($id)->update(['identity_id' => $identity_id]);
            }
        }
    }

    protected static function doRefreshRoles($self) {
        $self->read(['condo_id', 'identity_id' => ['user_id']]);
        foreach($self as $id => $owner) {
            if(!$owner['condo_id']) {
                continue;
            }
            if(!isset($owner['identity_id']['user_id'])) {
                continue;
            }
            $has_owner_role = false;
            $roleAssignments = RoleAssignment::search([['condo_id', '=', $owner['condo_id']], ['user_id', '=', $owner['identity_id']['user_id']]])
                ->read(['role_code']);
            foreach($roleAssignments as $roleAssignment) {
                if($roleAssignment['role_code'] === 'owner') {
                    $has_owner_role = true;
                    break;
                }
            }
            if(!$has_owner_role) {
                $role = Role::search(['code', '=', 'owner'])->first();
                if($role) {
                    RoleAssignment::create([
                        'condo_id'      => $owner['condo_id'],
                        'role_id'       => $role['id'],
                        'is_external'   => true,
                        'user_id'       => $owner['identity_id']['user_id']
                    ]);
                }
            }
        }
    }

    public static function onrevertName($self) {
        $self->read(['ownership_id']);
        foreach($self as $id => $owner) {
            if($owner['ownership_id']) {
                Ownership::id($owner['ownership_id'])->update(['name' => null]);
            }
        }
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $owner) {
            if($owner['identity_id']) {
                Identity::id($owner['identity_id'])->update(['owner_id' => $id]);
            }
        }
        $self->do('refresh_roles');
    }

}

