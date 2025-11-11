<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace fmt\core\alert;


class Message extends \core\alert\Message {

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'description'       => 'Office the message relates to (for targeting the users).',
                'store'             => true,
                'relation'          => ['group_id']
            ],

            'alert' => [
                'type'              => 'computed',
                'usage'             => 'icon',
                'result_type'       => 'string',
                'function'          => 'calcAlert',
                'store'             => true
            ]

        ];
    }

    public static function calcAlert($self) {
        $result = [];
        $self->read(['severity']);

        foreach($self as $id => $message) {
            if(!$message['severity']) {
                continue;
            }
            switch($message['severity']) {
                case 'notice':
                    $result[$id] = 'info';
                    break;
                case 'warning':
                    $result[$id] = 'warn';
                    break;
                case 'important':
                    $result[$id] = 'major';
                    break;
                case 'error':
                default:
                    $result[$id] = 'error';
                    break;
            }
        }
        return $result;
    }
}
