<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace communication;

use equal\orm\Model;

class Message extends Model {

    public static function getColumns() {
        return [

            'moment' => [
                'type'              => 'datetime',
                'description'       => 'Message emission date and time.',
                'default'           => function () { return time(); },
                'required'          => true
            ],

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\User',
                'description'       => 'Author of the message.'
            ],

            'content' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Text of the message.',
                'required'          => true
            ]

        ];
    }
}
