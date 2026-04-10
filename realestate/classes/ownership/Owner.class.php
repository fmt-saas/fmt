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

            // #deprecated
            'owner_shares' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Amount of shares the owner has on the ownership",
                'help'              => "Owners' 'full' & 'bare' `owner_shares` sum must match Ownership `total_shares`. Owners 'usufruct' `owner_shares` is only used to calculate their participation in the condominium's expenses.",
                'default'           => 100,
                'dependents'        => ['ownership_percentage']
            ],

            'ownership_percentage' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/percent',
                'function'          => 'calcOwnershipPercentage',
                'store'             => true,
                'description'       => "Share of the ownership, in percent (holders' shares sum must be 100%).",
                'default'           => 1.0
            ],

            // #deprecated
            'owner_type' => [
                'type'              => 'string',
                'selection'         => [
                    'full',
                    'bare',
                    'usufruct'
                ],
                'description'       => "Type of ownership that applies to the owner.",
                'default'           => 'full'
            ],

            'shares_full_property' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Amount of shares the owner has on the ownership",
                'default'           => 100,
                'dependents'        => ['ownership_percentage']
            ],

            'shares_bare_property' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Amount of shares the owner has on the ownership",
                'default'           => 0,
                'dependents'        => ['ownership_percentage']
            ],

            'shares_usufruct' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Amount of shares the owner has on the ownership",
                'default'           => 0,
                'dependents'        => ['ownership_percentage']
            ],


        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'refresh_roles' => [
                'description'   => 'Refresh roles assignments based on related User account.',
                'function'      => 'doRefreshRoles'
            ]
        ]);
    }

    protected static function oncreate($self, $orm, $values=[]) {
        if(isset($values['identity_id'])) {
            $self->do('refresh_roles');
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

    public static function calcOwnershipPercentage($self) {
        $result = [];
        $self->read(['owner_shares', 'shares_full_property', 'shares_bare_property', 'shares_usufruct', 'ownership_id' => ['ownership_shares']]);
        foreach($self as $id => $owner) {
            $owner_shares =
                ($owner['shares_full_property'] ?? 0) +
                ($owner['shares_bare_property'] ?? 0) +
                ($owner['shares_usufruct'] ?? 0);

            $total_shares = $owner['ownership_id']['ownership_shares'] ?? 0;

            if($total_shares > 0) {
                $result[$id] = round(($owner_shares / $total_shares) * 100, 2);
            }
            else {
                $result[$id] = 0;
            }
        }
        return $result;
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