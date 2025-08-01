<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\catalog;

use equal\orm\Model;

class Group extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the product model group (used for all variants).",
                'required'          => true
            ],

            'product_models_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'foreign_field'     => 'groups_ids',
                'rel_table'         => 'sale_catalog_product_rel_productmodel_group',
                'rel_foreign_key'   => 'productmodel_id',
                'rel_local_key'     => 'group_id'
            ],

            'products_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Product',
                'foreign_field'     => 'groups_ids',
                'rel_table'         => 'sale_catalog_product_rel_product_group',
                'rel_foreign_key'   => 'product_id',
                'rel_local_key'     => 'group_id'
            ],

            'family_id' => [
                'type'              => 'many2one',
                'description'       => "Product Family which current group belongs to.",
                'foreign_object'    => 'sale\catalog\Family'
            ]

        ];
    }
}
