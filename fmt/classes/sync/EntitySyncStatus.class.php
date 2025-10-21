<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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
