<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\pay;

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
                'relation'          => ['condo_id' => 'name'],
                'store'             => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'description'       => "The funding reminder relates to.",
                'foreign_object'    => 'sale\pay\Funding',
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

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'sent',
                    'cancelled'
                ],
                'description'       => "The current status of the reminder.",
                'default'           => 'pending'
            ]

        ];
    }

}
