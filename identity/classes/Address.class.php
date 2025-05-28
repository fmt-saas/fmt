<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;
use equal\orm\Model;


class Address extends Model {

    public static function getName() {
        return "Address";
    }

    public static function getDescription() {
        return "An address is a physical location at which an identity can be contacted.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => 'The display name of the address.'
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'The identity that the address relates to.'
            ],

            'is_primary' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the account as primary account.',
                'help'              => 'When a primary address is updated, sync is automatically replicated on related identity.',
                'default'           => false
            ],

            'role' => [
                'type'              => 'string',
                'selection'         => [ 'legal', 'invoice', 'delivery', 'other' ],
                'description'       => 'The main purpose for which the address is to be preferred.',
                'default'           => 'legal'
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.',
                'dependents'        => ['name']
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => 'Optional info for mail dispatch (appartment, box, floor, ...).'
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => 'City.',
                'dependents'        => ['name']
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => 'Postal code.',
                'dependents'        => ['name']
            ],

            'address_state' => [
                'type'              => 'string',
                'description'       => 'State or region.'
            ],

            'address_country' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'Country.'
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['address_street', 'address_city', 'address_zip', 'address_country' ]);
        foreach($self as $id => $address) {
            $result[$id] = "{$address['address_street']} {$address['address_zip']} {$address['address_city']}";
        }
        return $result;
    }

    /**
     * Synchronize the primary address of the identity.
     *
     */
    public static function onafterupdate($self, $values) {
        $self->read(['is_primary', 'owner_identity_id', 'address_street', 'address_dispatch', 'address_city', 'address_zip', 'address_country']);
        foreach($self as $id => $address) {
            if($address['is_primary']) {
                Identity::id($address['owner_identity_id'])
                    ->update([
                        'address_street'    => $address['bank_account_iban'],
                        'address_dispatch'  => $address['address_dispatch'],
                        'address_city'      => $address['bank_account_bic'],
                        'address_zip'       => $address['bank_account_bic'],
                        'address_country'   => $address['bank_account_bic']
                    ]);
            }
        }
    }

}
