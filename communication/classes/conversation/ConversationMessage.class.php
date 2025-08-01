<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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
