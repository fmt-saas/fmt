<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\ownership;

use identity\Identity;

class Owner extends Identity {

    public function getTable() {
        // force table name to use distinct tables and ID columns
        return 'realestate_ownership_owner';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the Owner.",
                'relation'          => ['identity_id' => 'name'],
                'store'             => true,
                'readonly'          => true
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

            'owner_type' => [
                'type'              => 'string',
                'selection'         => [
                    'full',
                    'bare',
                    'usufruct'
                ],
                'description'       => "Type of ownership that applies to the owner.",
                'default'           => 'full'
            ]

        ];
    }

    public static function calcOwnershipPercentage($self) {
        $result = [];
        $self->read(['owner_shares', 'ownership_id' => ['total_shares']]);
        foreach($self as $id => $owner) {
            $result[$id] = round($owner['ownership_id']['total_shares'] / $owner['owner_shares'], 2);
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
    }

}