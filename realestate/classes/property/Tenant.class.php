<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

use identity\Identity;

class Tenant extends Identity {

    public function getTable() {
        // force table name to use distinct tables and ID columns
        return 'realestate_property_tenant';
    }

    public static function getDescription() {
        return "A tenant is a renter of a property lot. There can be multiple cohabitants for the same rental. The cohabitants (adults) are jointly responsible for the rental and the associated charges.";
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name of the tenant.",
                'relation'          => ['identity_id' => ['name']],
                'readonly'          => true,
                'store'             => true
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the tenancy relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current entity.',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'realestate\property\Tenant'
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
            ]

        ];
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $owner) {
            if($owner['identity_id']) {
                Identity::id($owner['identity_id'])->update(['tenant_id' => $id]);
            }
        }
    }
}