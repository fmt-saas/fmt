<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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
