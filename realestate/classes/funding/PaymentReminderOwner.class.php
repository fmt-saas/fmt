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

            'payment_reminder_id' => [
                'type'              => 'many2one',
                'description'       => "The funding reminder relates to.",
                'foreign_object'    => 'realestate\funding\PaymentReminder',
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

        ];
    }


}
