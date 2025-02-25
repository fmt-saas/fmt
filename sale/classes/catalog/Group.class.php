<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
