<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\ownership;

class Owner extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true
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
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'description'       => "The identity of the owner.",
                'foreign_object'    => 'identity\Identity',
                'required'          => true
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

}