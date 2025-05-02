<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace fmt\sync;

use equal\orm\Model;

class EntitySyncStatus extends Model {

    public static function getColumns() {
        return [

            'entity' => [
                'type'              => 'string',
                'description'       => 'Targeted private entity with full namespace.',
                'required'          => true,
                'unique'            => true
            ],

            'last_sync' => [
                'type'              => 'datetime',
                'description'       => 'Last successful sync.'
            ]

        ];
    }

}
