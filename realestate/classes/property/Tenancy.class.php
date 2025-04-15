<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class Tenancy extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name representing the tenancy (one or more persons).",
                'function'          => 'calcName',
                'readonly'          => true,
                'store'             => true
            ],

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

            'tenants_ids' => [
                'type'              => 'one2many',
                'description'       => "The identity of the person holding the tenant.",
                'foreign_object'    => 'realestate\property\Tenant',
                'foreign_field'     => 'tenancy_id'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "The date from which the tenancy is valid.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "The date from which the tenancy is valid.",
            ],

            'transfer_from_id' => [
                'type'              => 'many2one',
                'description'       => "The property purchase transfer file.",
                'foreign_object'    => 'realestate\property\TenancyTransfer'
            ],

            'transfer_to_id' => [
                'type'              => 'many2one',
                'description'       => "The property sale transfer file.",
                'foreign_object'    => 'realestate\property\TenancyTransfer'
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['tenants_ids' => ['name']]);
        foreach($self as $id => $tenancy) {
            $names = [];
            foreach($tenancy['tenants_ids'] as $tenant_id => $tenant) {
                $names[] = $tenant['name'];
            }
            $name = implode(', ', $names);
            if(strlen($name) > 128) {
                $name = substr($name, 0, 128) . '...';
            }
            if(strlen($name) > 0) {
                $result[$id] = $name;
            }
        }
        return $result;
    }
}