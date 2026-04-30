<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

class PaymentReminderOwner extends \equal\orm\Model {


    public static function getColumns(): array {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the reminder relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
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

            'payment_reminder_id' => [
                'type'              => 'many2one',
                'description'       => "The funding reminder relates to.",
                'foreign_object'    => 'realestate\funding\PaymentReminder',
                'readonly'          => true,
                'required'          => true,
                'ondelete'          => 'cascade',
            ],

            'payment_reminder_owner_lines_ids' => [
                'type'              => 'one2many',
                'description'       => "Owners present in the reminder.",
                'foreign_object'    => 'realestate\funding\PaymentReminderOwnerLine',
                'foreign_field'     => 'payment_reminder_owner_id'
            ],

            'due_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The amount that is due from the funding.",
                'store'             => true,
                'function'          => 'calcDueAmount'
            ],

            'due_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The actual balance ot the ownership at the time of the reminder."
            ],

            'due_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => "Deadline before which the funding is expected."
            ]

        ];
    }

    protected static function calcDueAmount($self) {
        $result = [];
        $self->read(['payment_reminder_owner_lines_ids' => ['due_amount']]);
        foreach($self as $id => $paymentReminderOwner) {
            $result[$id] = 0.0;
            foreach($paymentReminderOwner['payment_reminder_owner_lines_ids'] as $payment_reminder_owner_line_id => $paymentReminderOwnerLine) {
                $result[$id] += $paymentReminderOwnerLine['due_amount'];
            }
        }
        return $result;
    }

}
