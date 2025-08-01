<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace sale\catalog;

use equal\orm\Model;

class Category extends Model {

    public static function getName() {
        return "Product Category";
    }

    public static function getDescription() {
        return "Product categories allow to group products in arbitrary ways."
            ." Categories are not related to Families nor Groups.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the product category (used for all variants).",
                'multilang'         => true,
                'required'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Unique code of the category (to ease searching).",
                'required'          => true,
                'unique'            => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Short string describing the purpose and usage of the category."
            ],

            'product_models_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'foreign_field'     => 'categories_ids',
                'rel_table'         => 'sale_product_rel_productmodel_category',
                'rel_foreign_key'   => 'productmodel_id',
                'rel_local_key'     => 'category_id',
                'description'       => 'List of product models assigned to this category.'
            ]

        ];
    }
}
