<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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