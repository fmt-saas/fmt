<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace automation\agent;

use equal\orm\Model;

class Message extends Model {

    public static function getColumns(): array {
        return [

            'conversation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'automation\agent\Conversation',
                'description'       => 'Conversation to which the message belongs.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'sequence' => [
                'type'              => 'integer',
                'description'       => 'Position of the message in the conversation.',
                'required'          => true
            ],

            'role' => [
                'type'              => 'string',
                'usage'             => 'text/plain:32',
                'selection'         => [
                    'user',
                    'agent'
                ],
                'description'       => 'Business role of the message author.',
                'required'          => true
            ],

            'status' => [
                'type'              => 'string',
                'usage'             => 'text/plain:32',
                'selection'         => [
                    'pending',
                    'running',
                    'completed',
                    'failed'
                ],
                'description'       => 'Current processing status of the message.',
                'default'           => 'pending'
            ],

            'content' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Content of the message.'
            ],

            'message_steps_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'automation\agent\MessageStep',
                'foreign_field'     => 'message_id',
                'description'       => 'Processing steps belonging to the message.',
                'order'             => 'sequence'
            ]

        ];
    }

    public function getUniques(): array {
        return [
            ['conversation_id', 'sequence']
        ];
    }
}
