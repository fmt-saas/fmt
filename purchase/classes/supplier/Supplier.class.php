<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace purchase\supplier;

use identity\Identity;

class Supplier extends Identity {

    public function getTable() {
        return 'purchase_supplier_supplier';
    }

    public static function getName() {
        return 'Supplier';
    }

    public static function getDescription() {
        return "A supplier is a company from which the organisation buys goods and services.";
    }

    public static function getColumns() {

        return [
            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current entity .',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'purchase\supplier\Supplier'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the Supplier.",
                'relation'          => ['identity_id' => 'name'],
                'store'             => true,
                'readonly'          => true,
                'onrevert'          => 'onrevertName'
            ],

            /**
             * Specific Supplier columns
             */

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\accounting\invoice\Invoice',
                'foreign_field'     => 'supplier_id',
                'description'       => 'Purchase invoices from the supplier.'
            ],

            'condominiums_ids' => [
                'type'              => 'many2many',
                'description'       => "Condominiums that have (or had) the supplier amongst their service providers.",
                'foreign_object'    => 'realestate\property\Condominium',
                'foreign_field'     => 'suppliers_ids',
                'rel_table'         => 'purchase_supplier_suppliership',
                'rel_foreign_key'   => 'condo_id',
                'rel_local_key'     => 'supplier_id'
            ],

            'supplierships_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'foreign_field'     => 'supplier_id',
                'description'       => "Suppliership items relating to the Supplier."
            ],

            'supplier_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\SupplierType',
                'description'       => "Suppliership items relating to the Supplier.",
                'dependents'        => ['supplier_type_code']
            ],

            'supplier_type_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['supplier_type_id' => 'code'],
                'store'             => true,
                'instant'           => true,
                'description'       => "Code of the supplier type assigned to supplier."
            ]

        ];
    }

    public static function onrevertName($self) {
        $self->read(['supplierships_ids']);
        foreach($self as $id => $supplier) {
            Suppliership::ids($supplier['supplierships_ids'])->update(['name' => null]);
        }
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $supplier) {
            if($supplier['identity_id']) {
                Identity::id($supplier['identity_id'])->update(['supplier_id' => $id]);
            }
        }
    }

}
