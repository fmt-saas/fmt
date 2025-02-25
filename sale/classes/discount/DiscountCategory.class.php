<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\discount;
use equal\orm\Model;

class DiscountCategory extends Model {

    public static function getName() {
        return "Discount category";
    }

    public static function getDescription() {
        return "Discounts inside a list are exclusive, according to their category.";
    }
    

    public static function getColumns() {
        /**
         */

        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Short name of the category.',
                'multilang'         => true,
                'required'          => true
            ],
            
            'description' => [
                'type'              => 'string',
                'description'       => "Reason of the discount category.",
                'multilang'         => true
            ],

            'discounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\discount\Discount',
                'foreign_field'     => 'discount_category_id',
                'description'       => 'The discounts that are assigned to the category.'
            ]


        ];
    }

}