<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\price;
use equal\orm\Model;

class PriceListCategory extends Model {
    public static function getColumns() {
        /**
         */

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Short label to ease identification of the list."
            ],
            
            'price_list_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\price\PriceList',
                'foreign_field'     => 'price_list_category_id',
                'description'       => "Lists that are related to this category, if any."
            ]            
        ];
    }
}