<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace sale\season;
use equal\orm\Model;

class SeasonCategory extends Model {
    public static function getColumns() {
        /**
         */

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Short label to ease identification of the category."
            ],
            
            'seasons_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\season\Season',
                'foreign_field'     => 'season_category_id',
                'description'       => "Seasons that are related to this category, if any."
            ]
        ];
    }
}