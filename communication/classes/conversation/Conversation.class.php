<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace communication\conversation;

use equal\orm\Model;

class Conversation extends Model {

    public static function getColumns() {
        return [

            'channel_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\conversation\Channel',
                'description'       => 'From which channel the conversation is a part of.'
            ],

            'is_private' => [
                'type'              => 'boolean',
                'description'       => 'Is the conversation between two users only.',
                'default'           => false
            ],

            'user1_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\User',
                'description'       => 'First user that is part of the conversation.'
            ],

            'user2_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\User',
                'description'       => 'Second user that is part of the conversation.'
            ]

        ];
    }
}
