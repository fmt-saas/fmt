<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
