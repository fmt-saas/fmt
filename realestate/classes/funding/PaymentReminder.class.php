<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

class PaymentReminder extends \equal\orm\Model {

    public static function getDescription(): string {
        return "A funding reminder streamlines the process of alerting customers when a funding due date has passed and the corresponding payment remains outstanding.";
    }

    public static function getColumns(): array {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the reminder relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true,
                'required'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of funding reminder.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'description'       => "The funding reminder relates to.",
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'readonly'          => true,
                'required'          => true
            ],

            'due_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The amount that is due from the funding.",
                'store'             => true,
                'relation'          => ['funding_id' => 'due_amount']
            ],

            'due_date' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'usage'             => 'date/plain',
                'description'       => "Deadline before which the funding is expected.",
                'store'             => true,
                'relation'          => ['funding_id' => 'due_date']
            ],

            'ownership_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'description'       => "The ownership that the funding reminder refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'store'             => true,
                'relation'          => ['funding_id' => 'ownership_id']
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'description'       => "Emails sent to remind that the overdue funding is waiting for payment.",
                'help'              => "Should be only one.",
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', 'realestate\funding\PaymentReminder']
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'not_sent',
                    'sent',
                    'cancelled'
                ],
                'description'       => "The current status of the reminder.",
                'default'           => 'not_sent'
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['state', 'funding_id' => ['name']]);
        foreach($self as $id => $fund_reminder) {
            if($fund_reminder['state'] === 'draft') {
                continue;
            }

            $result[$id] = $fund_reminder['funding_id']['name'];
        }

        return $result;
    }
}
