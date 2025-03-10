<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class Tenant extends \equal\orm\Model {

    public static function getDescription() {
        return "A tenant is a renter of a property lot. There can be multiple cohabitants for the same rental. The cohabitants (adults) are jointly responsible for the rental and the associated charges.";
    }

    public static function getColumns() {

        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the tenancy relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => "The Property Lot the tenancy relates to.",
            ],

            'tenancy_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Tenancy',
                'description'       => "The Tenancy the tenant relates to.",
            ],

            'tenant_identity_id' => [
                'type'              => 'many2one',
                'description'       => "The identity of the person holding the tenant.",
                'foreign_object'    => 'identity\Identity',
                'required'          => true
            ]

        ];
    }
}