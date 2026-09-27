<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace automation\agent;

use equal\orm\Model;

class MessageStep extends Model {

    public static function getColumns(): array {
        return [

            'message_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'automation\agent\Message',
                'description'       => 'Message to which the processing step belongs.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'sequence' => [
                'type'              => 'integer',
                'description'       => 'Position of the step in the message processing flow.',
                'required'          => true
            ],

            'type' => [
                'type'              => 'string',
                'usage'             => 'text/plain:32',
                'selection'         => [
                    'llm',
                    'tool'
                ],
                'description'       => 'Type of processing performed by the step.',
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
                'description'       => 'Current processing status of the step.',
                'default'           => 'pending'
            ],

            'data' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => 'JSON encoded data produced or consumed by the step.'
            ]

        ];
    }

    public function getUniques(): array {
        return [
            ['message_id', 'sequence']
        ];
    }
}
