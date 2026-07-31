<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\catalog;

use equal\orm\Model;

class ProductModel extends Model {

    public static function getName(): string {
        return "Product Model";
    }

    public static function getDescription(): string {
        return "Product Models act as common denominator for products variants (referred to as \"Products\")."
            ." These objects are used for catalogs generation: for instance, if a picture is related to a Product, it is associated on the Product Model level."
            ." A Product Model has at minimum one variant, which means at minimum one SKU.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the product model (used for all variants).",
                'required'          => true
            ],

            'family_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Family',
                'description'       => "Product Family which current product belongs to.",
                'onupdate'          => 'onupdateFamilyId',
                'required'          => true
            ],

            'selling_accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule',
                'description'       => "Accounting rule to use in case of sell."
            ],

            'buying_accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule',
                'description'       => "Accounting rule to use in case of purchase."
            ],

            'stat_section_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\stats\StatSection',
                'description'       => "Statistics section to which relates the product, if any."
            ],

            'can_buy' => [
                'type'              => 'boolean',
                'description'       => "Can this product be purchased?",
                'default'           => false,
                'onupdate'          => 'onupdateCanBuy'
            ],

            'can_sell' => [
                'type'              => 'boolean',
                'description'       => "Can this product be sold?",
                'default'           => true,
                'onupdate'          => 'onupdateCanSell'
            ],

            'type' => [
                'type'              => 'string',
                'description'       => "Is the product a consumable or a service.",
                'selection'         => [
                    'consumable',
                    'service'
                ],
                'required'          => true,
                'default'           => 'service'
            ],

            'consumable_type' => [
                'type'              => 'string',
                'description'       => "Is the consumable product storable.",
                'selection'         => [
                    'simple',
                    'storable'
                ],
                'visible'           => ['type', '=', 'consumable']
            ],

            'description_delivery' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description for delivery notes.",
                'multilang'         => true
            ],

            'description_receipt' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description for reception vouchers.",
                'multilang'         => true
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Group',
                'foreign_field'     => 'product_models_ids',
                'description'       => "Linked groups.",
                'rel_table'         => 'sale_catalog_product_rel_productmodel_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'productmodel_id',
                'onupdate'          => 'onupdateGroupsIds'
            ],

            'categories_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Category',
                'foreign_field'     => 'product_models_ids',
                'description'       => "Linked categories",
                'rel_table'         => 'sale_product_rel_productmodel_category',
                'rel_foreign_key'   => 'category_id',
                'rel_local_key'     => 'productmodel_id'
            ],

            'products_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\Product',
                'foreign_field'     => 'product_model_id',
                'description'       => "Product variants that are related to this model.",
            ],

            'options_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\Option',
                'foreign_field'     => 'product_model_id',
                'description'       => "Product options that are related to this model.",
            ]

        ];
    }

    public static function onupdateCanSell($self): void {
        $self->read(['products_ids', 'can_sell']);
        foreach($self as $model) {
            Product::ids($model['products_ids'])->update(['can_sell' => $model['can_sell']]);
        }
    }

    public static function onupdateCanBuy($self): void {
        $self->read(['products_ids', 'can_buy']);
        foreach($self as $model) {
            Product::ids($model['products_ids'])->update(['can_buy' => $model['can_buy']]);
        }
    }

    public static function onupdateFamilyId($self): void {
        $self->read(['products_ids', 'family_id']);
        foreach($self as $model) {
            Product::ids($model['products_ids'])->update(['family_id' => $model['family_id']]);
        }
    }

    public static function onupdateGroupsIds($self): void {
        $self->read(['products_ids', 'groups_ids']);
        foreach($self as $model) {
            $products = Product::ids($model['products_ids'])
                ->read(['groups_ids'])
                ->get();

            foreach($products as $pid => $product) {
                if(!$product['groups_ids']) {
                    continue;
                }

                $groups_ids = array_map(function($a) {return "-$a";}, $product['groups_ids']);
                $groups_ids = array_merge($groups_ids, (array) $model['groups_ids']);

                Product::id($pid)->update(['groups_ids' => $groups_ids]);
            }
        }
    }
}
