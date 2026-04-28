<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

class PaymentReminderOwnerLine extends \equal\orm\Model {


    public static function getColumns(): array {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the reminder relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true,
                'required'          => true
            ],

            'payment_reminder_id' => [
                'type'              => 'many2one',
                'description'       => "The funding reminder relates to.",
                'foreign_object'    => 'realestate\funding\PaymentReminder',
                'readonly'          => true,
                'required'          => true
            ],

            'payment_reminder_owner_id' => [
                'type'              => 'many2one',
                'description'       => "The funding reminder relates to.",
                'foreign_object'    => 'realestate\funding\PaymentReminderOwner',
                'readonly'          => true,
                'required'          => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'description'       => "The funding the reminder relates to.",
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'readonly'          => true,
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'description'       => "The ownership that the funding reminder refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'store'             => true,
                'relation'          => ['funding_id' => 'ownership_id']
            ],

            'days_overdue' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Amount of overdue days.",
                'function'          => 'calcDaysOverdue',
                'readonly'          => true,
                'store'             => false
            ],

            'due_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount missing at reminder date.',
                'readonly'          => true,
                'required'          => true
            ],

            'payment_reminder_status' => [
                'type'              => 'string',
                'description'       => "Status of the parent Payment Reminder.",
                'default'           => 'pending'
            ]
        ];
    }

    protected static function calcDaysOverdue($self) {
        $result = [];
        $self->read(['funding_id' => ['due_date']]);
        foreach($self as $id => $paymentReminderOwnerLine) {
            $result[$id] = floor((strtotime('today') - $paymentReminderOwnerLine['funding_id']['due_date']) / 86400);
        }
        return $result;
    }

}
