<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace purchase\supplier;

use finance\bank\BankAccount;
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
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the Supplier.",
                'relation'          => ['identity_id' => 'name'],
                'store'             => true,
                'readonly'          => true,
                'onrevert'          => 'onrevertName'
            ],

            'uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                // #memo - commented for testing because items are on the same instance
                // #todo - uncomment for PROD
                // 'unique'            => true,
                'description'       => 'Unique identifier from the Master instance.'
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current Identity.',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'purchase\supplier\Supplier'
            ],

            'supplier_type_code' => [
                'type'              => 'string',
                'default'           => 'supplier'
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\IdentityType',
                'onupdate'          => 'onupdateTypeId',
                'default'           => 3,
                'dependents'        => ['type', 'name', 'identity_slug', 'slug_hash'],
                'description'       => 'Type of identity.'
            ],

            /**
             * Specific Supplier columns
             */

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\accounting\invoice\PurchaseInvoice',
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

            'supplier_types_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'purchase\supplier\SupplierType',
                'foreign_field'     => 'suppliers_ids',
                'description'       => "Supplier types assigned to the Supplier.",
                'rel_table'         => 'purchase_supplier_rel_suppliertype',
                'rel_foreign_key'   => 'type_id',
                'rel_local_key'     => 'supplier_id'
            ],

            'recording_rules_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'documents\recording\RecordingRule',
                'foreign_field'     => 'suppliers_ids',
                'description'       => "Recording Rule assigned to the Supplier.",
                'rel_table'         => 'purchase_supplier_rel_recordingrule',
                'rel_foreign_key'   => 'recording_rule_id',
                'rel_local_key'     => 'supplier_id'
            ],

            'bank_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\BankAccount',
                // #memo - foreign_field cannot be used here, since it should be identity_id, which points back to current object's `id` instead of `identity_id`
                'description'       => 'List of the bank account of the supplier.',
                'domain'            => ['owner_identity_id', '=', 'object.identity_id']
            ],

            'addresses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Address',
                'description'       => 'List of addresses related to the supplier.',
                'domain'            => ['owner_identity_id', '=', 'object.identity_id']
            ],

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Contact',
                // #memo - there is no direct relation, so we use domain to point `owner_identity_id` to the supplier's identity
                // 'foreign_field'     => '',
                'description'       => 'List of contacts related to the supplier.',
                'domain'            => ['owner_identity_id', '=', 'object.identity_id']
            ],

            /* duplicated from Identity for marking as mandatory */

            'address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.',
                'onupdate'          => 'onupdateAddressStreet',
                'required'          => true,
                'dependents'        => ['address_hash', 'address']
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => 'City.',
                'onupdate'          => 'onupdateAddressCity',
                'required'          => true,
                'dependents'        => ['address_hash', 'address']
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => 'Postal code.',
                'onupdate'          => 'onupdateAddressZip',
                'required'          => true,
                'dependents'        => ['address_hash', 'identity_slug', 'slug_hash']
            ],

            'address_country' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'Country.',
                'required'          => true,
                'default'           => 'BE',
                'dependents'        => ['address_hash', 'identity_slug', 'slug_hash'],
                'onupdate'          => 'onupdateAddressCountry'
            ],

        ];
    }

    protected static function doSyncUuidLinks($self) {
        $self->read(['identity_id', 'identity_uuid']);
        foreach($self as $id => $supplier) {
            if(!empty($supplier['identity_uuid'])) {
                $identity = Identity::search(['uuid', '=', $supplier['identity_uuid']])
                    ->first();

                if($identity && $identity['identity_id'] !== $identity['id']) {
                    self::id($id)->update(['identity_id' => $identity['id']]);
                }
            }
        }
    }

    protected static function calcSupplierTypeCode($self) {
        $result = [];
        $self->read(['object_class']);
        foreach($self as $id => $supplier) {
            switch($supplier['object_class']) {
                case 'finance\bank\Bank':
                    $result[$id] = 'bank';
                    break;
                case 'realestate\property\NotaryOffice':
                    $result[$id] = 'notary_office';
                    break;
                case 'realestate\management\ManagingAgent':
                    $result[$id] = 'managing_agent';
                    break;
                case 'purchase\supplier\Supplier':
                default:
                    $result[$id] = 'supplier';
                break;
            }
        }
        return $result;
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
