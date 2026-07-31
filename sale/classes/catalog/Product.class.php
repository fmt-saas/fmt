<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\catalog;

use equal\orm\Model;

class Product extends Model {

    public static function getName(): string {
        return "Product";
    }

    public static function getDescription(): string {
        return "A Product is a variant of a Product Model. There is always at least one Product for a given Product Model."
            ." Within the organisation, a product is always referenced by a SKU code (assigned to each variant of a Product Model)."
            ." A SKU code identifies a single product with all its specific characteristics.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => "The full name of the product (label + sku)."
            ],

            'label' => [
                'type'              => 'string',
                'description'       => "Human readable memo for identifying the product. Allows duplicates.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'sku' => [
                'type'              => 'string',
                'description'       => "Stock Keeping Unit code for internal reference. Must be unique.",
                'required'          => true,
                'unique'            => true,
                'dependents'        => ['name']
            ],

            'ean' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.ean',
                'description'       => "IAN/EAN code for barcode generation."
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description of the variant (specifics)."
            ],

            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'description'       => "Product Model of this variant.",
                'required'          => true,
                'readonly'          => true
            ],

            'family_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'relation'          => ['product_model_id' => 'family_id'],
                'foreign_object'    => 'sale\catalog\Family',
                'description'       => "Product Family which current product belongs to.",
                'store'             => true
            ],

            'can_buy' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'can_buy'],
                'description'       => "Can this product be purchased? (from model)",
                'help'              => "Field can_buy is adapted when related value is changed in parent ProductModel.",
                'store'             => true
            ],

            'can_sell' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'can_sell'],
                'description'       => "Can this product be sold? (from model)",
                'help'              => "Field can_sell is adapted when related value is changed in parent ProductModel.",
                'store'             => true
            ],

            'stat_section_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'relation'          => ['product_model_id' => 'stat_section_id'],
                'foreign_object'    => 'finance\stats\StatSection',
                'description'       => "Statistics section (overloads the model one, if any).",
                'store'             => true
            ],

            'product_attributes_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\ProductAttribute',
                'foreign_field'     => 'product_id',
                'description'       => "Attributes set for the product.",
                'ondetach'          => 'delete'
            ],

            'prices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\price\Price',
                'foreign_field'     => 'product_id',
                'description'       => "Prices that are related to this product.",
                'help'              => "If the organisation uses price-lists, the price to use depends on the applicable price list at the moment of the sale.",
                'ondetach'          => 'delete'
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Group',
                'foreign_field'     => 'products_ids',
                'description'       => "Linked groups.",
                'rel_table'         => 'sale_catalog_product_rel_product_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'product_id'
            ],

            'subscriptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\subscription\Subscription',
                'foreign_field'     => 'product_id',
                'description'       => "The subscriptions needed for the product."
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['label', 'sku']);
        foreach($self as $id => $product) {
            $name = '';
            if(!empty($product['label'])) {
                $name .= $product['label'];
            }
            if(!empty($product['sku'])) {
                if(!empty($name)) {
                    $name .= ' ';
                }
                $name .= "[{$product['sku']}]";
            }

            $result[$id] = $name;
        }

        return $result;
    }
}
