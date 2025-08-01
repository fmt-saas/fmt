<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace sale\autosale;
use equal\orm\Model;

class AutosaleListCategory extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the automatic sale category."
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Reason of the categorization of children lists.",
                'multilang'         => true
            ],

            'autosale_lists_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\autosale\AutosaleList',
                'foreign_field'     => 'autosale_list_category_id',
                'description'       => 'The autosale lists that are assigned to the category.'
            ]

        ];
    }

}