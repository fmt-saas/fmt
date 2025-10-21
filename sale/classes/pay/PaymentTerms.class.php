<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\pay;

use equal\orm\Model;

class PaymentTerms extends Model {

    public static function getDescription() {
        return 'Payment terms of an invoice specify the conditions under which your organisation will accept payment from customers.'
            .' They include the payment due date, any discounts for early payment, and penalties for late payment.';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Short memo of the terms.',
                'multilang'         => true,
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Description of the terms (1 sentence, displayed in docs).',
                'multilang'         => true,
                'required'          => true
            ],

            'delay_from' => [
                'type'              => 'string',
                'selection'         => ['created', 'next_month'],
                'description'       => "Event from which the delay is relative to."
            ],

            'delay_count' => [
                'type'              => 'integer',
                'description'       => "Number of days before reaching the deadline."
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Can these terms be used?",
                'default'           => true
            ]

        ];
    }

}