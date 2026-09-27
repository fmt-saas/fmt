<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace automation\agent;

use equal\orm\Model;

class Conversation extends Model {

    public static function getColumns(): array {
        return [

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\User',
                'description'       => 'User who owns the conversation.',
                'required'          => true
            ],

            'status' => [
                'type'              => 'string',
                'usage'             => 'text/plain:32',
                'selection'         => [
                    'pending',
                    'running'
                ],
                'description'       => 'Current processing status of the conversation.',
                'default'           => 'pending'
            ],

            'context' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => 'JSON encoded durable context of the conversation.',
                'default'           => '{}'
            ],

            'messages_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'automation\agent\Message',
                'foreign_field'     => 'conversation_id',
                'description'       => 'Messages belonging to the conversation.',
                'order'             => 'sequence'
            ]

        ];
    }
}
