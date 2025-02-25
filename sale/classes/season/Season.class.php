<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\season;
use equal\orm\Model;

class Season extends Model {
    public static function getColumns() {
        /**
         */

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Short mnemo of the season.",
                'required'          => true
            ],

            'year' => [
                'type'              => 'integer',
                'usage'             => 'date/year:4',
                'description'       => "Year the season applies to.",
                'required'          => true
            ],

            'season_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\season\SeasonCategory',
                'description'       => "The category the season relates to.",
                'required'          => true,
                'onupdate'          => 'onupdateSeasonCategoryId'
            ],

            'has_rate_class' => [
                'type'              => 'boolean',
                'description'       => "Is the season specific to a given Rate Class?",
                'default'           => false
            ],

            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The rate class that applies to this Season definition.",
                'visible'           => ['has_rate_class', '=', true]
            ],

            'season_periods_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\season\SeasonPeriod',
                'foreign_field'     => 'season_id',
                'description'       => 'Periods that are part of the season (on a yearly basis).',
                'ondetach'          => 'delete'
            ]

        ];
    }

    public static function onupdateSeasonCategoryId($self) {
        $self->read(['season_periods_ids']);
        foreach($self as $season) {
            SeasonPeriod::ids($season['season_periods_ids'])->update(['season_category_id' => null]);
        }
    }

}