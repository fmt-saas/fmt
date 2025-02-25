<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace communication\conversation;

class ConversationMessage extends \communication\Message {

    public static function getColumns() {
        return [

            'conversation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\conversation\Conversation',
                'description'       => 'From which conversation the message is a part of.',
                'required'          => true
            ]

        ];
    }
}
