<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

class PaymentReminder extends \sale\pay\PaymentReminder {

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

            'emission_date' => [
                'type'              => 'date',
                'description'       => "Date at which the reminder was emitted."
            ],

            // #todo - calc based on PaymentReminderOwnerLine
            'fundings_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'payment_reminders_ids',
                'rel_table'         => 'realestate_ownership_transfer_rel_documents',
                'rel_foreign_key'   => 'funding_id',
                'rel_local_key'     => 'payment_reminder_id'
            ],


            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'pending',
                    'sent',
                    'cancelled'
                ],
                'description'       => 'The current status of the reminder.',
                'help'              => "The reminders are first created and then are published only if candidate to be sent.",
                'default'           => 'draft'
            ]

        ];
    }


}
