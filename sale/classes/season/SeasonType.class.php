<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\season;
use equal\orm\Model;

class SeasonType extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Short code of the type."
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Explanation on when to use the type and its specifics.",
                'default'           => '',
                'multilang'         => true
            ]

        ];
    }

}