<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\sale\pay;

use finance\bank\BankStatement;

class Payment extends \sale\pay\Payment {

    public static function getColumns() {
        return [
            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'description'       => 'The funding the payment relates to, if any.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ]
        ];
    }

    public static function getWorkflow() {
        return array_merge(parent::getWorkflow(), [
            'proforma' => [
                'description' => 'Payment being created.',
                'icon'        => 'draw',
                'transitions' => [
                    'publish' => [
                        'description' => 'Update the payment status to `payment`.',
                        'onafter'     => 'onafterPublish',
                        'status'      => 'payment'
                    ]
                ]
            ]
        ]);
    }

    protected static function onafterPublish($self) {
        $self->read(['funding_id', 'statement_line_id']);
        foreach($self as $id => $payment) {
            if($payment['funding_id']) {
                Funding::id($payment['funding_id'])
                    ->update(['paid_amount' => null, 'is_paid' => null])
                    ->do('attempt_posting');
            }
            if($payment['statement_line_id']) {
                BankStatement::id($payment['statement_line_id'])->update(['remaining_amount' => null]);
            }
        }
    }
}
