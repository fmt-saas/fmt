<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\pay;

use equal\orm\Model;

class FundingPlan extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'The name of the plan.',
                'required'          => true,
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'The name of the plan.',
                'multilang'         => true
            ],

            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The rate class that applies to the payment plan."
            ],

            'payment_deadlines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\PaymentDeadline',
                'foreign_field'     => 'payment_plan_id',
                'description'       => 'List of deadlines related to the plan, if any.',
                'ondetach'          => 'delete'
            ]

        ];
    }
}
