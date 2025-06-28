<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\sale\pay;

use core\setting\Setting;
use equal\data\DataFormatter;

class Funding extends \sale\pay\Funding {

    public static function getDescription() {
        return 'Funding for tracking fund requests and expense statements (funds to be received).';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of funding.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'is_sent' => [
                'type'              => 'boolean',
                'description'       => 'Flag indicating if a SEPA order has been generated (once or more) from the Funding.',
                'default'           => false
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Payment',
                'foreign_field'     => 'funding_id',
                'description'       => 'Payments of the funding.',
                'dependents'        => ['paid_amount', 'is_paid']
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'The Bank account the funding relates to.',
                'help'              => 'This is the bank account to which payments are expected to be received or from which payment is expected to be made.',
                'readonly'          => true
            ],

            'counterpart_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'Counterpart bank account, when applying.',
                'help'              => 'The bank account used as the counterpart in a transfer. Required when the funding represents an internal transfer between two bank accounts.',
                'readonly'          => true
            ],

            'funding_type' => [
                'type'              => 'string',
                'selection'         => [
                    'installment',
                    'refund',
                    'transfer',
                    'invoice',
                    'fund_request',
                    'expense_statement'
                ],
                'required'          => true,
                'dependents'        => ['payment_reference'],
                'description'       => "Type of funding. Either an installment, a specific invoice, a fund request, or an expense statement."
            ],

            'fund_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => 'The fund request targeted by the funding, if any.',
                'readonly'          => true,
                'visible'           => ['funding_type', '=', 'fund_request']
            ],

            'fund_request_execution_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'description'       => 'The fund request execution targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to a fund request. Fund request executions are sale invoices (with invoice_type set to fund_request).',
                'visible'           => ['funding_type', '=', 'fund_request'],
                'readonly'          => true
            ],

            'expense_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\ExpenseStatement',
                'description'       => 'The fund request execution targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to a fund request. Fund request executions are sale invoices (with invoice_type set to fund_request).',
                'visible'           => ['funding_type', '=', 'expense_statement'],
                'readonly'          => true
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\Invoice',
                'description'       => 'The invoice targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to an invoice: either because the funding has been invoiced (downpayment or balance invoice), or because it is an installment (deduced from the due amount).',
                'readonly'          => true,
                'visible'           => ['funding_type', 'in', ['installment', 'invoice']],
            ],

            'money_transfer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\finance\accounting\MoneyTransfer',
                'description'       => 'Miscellaneous operation targeted by the funding, if any.',
                'help'              => 'Money transfer is a particular case of misc operation.',
                'readonly'          => true,
                'visible'           => ['funding_type', 'in', ['transfer']],
            ],

            'money_refund_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\finance\accounting\MoneyRefund',
                'description'       => 'Miscellaneous operation targeted by the funding, if any.',
                'help'              => 'Money refund is a particular case of misc operation.',
                'readonly'          => true,
                'visible'           => ['funding_type', 'in', ['refund']],
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the funding refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'ondelete'          => 'cascade',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'readonly'          => true
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'description'       => 'The supplier the funding relates to.',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'readonly'          => true
            ],

            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Message for identifying the purpose of the transaction.',
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcPaymentReference'
            ],

        ];
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['state', 'due_amount', 'payment_reference', 'fund_request_execution_id' => ['name'],  'invoice_id' => ['name']]);
        foreach($self as $id => $funding) {
            if($funding['state'] === 'draft') {
                continue;
            }
            if(!$funding['due_amount']) {
                continue;
            }

            $result[$id] = Setting::format_number_currency($funding['due_amount']);

            if($funding['payment_reference']) {
                $result[$id] .= '  ' . DataFormatter::format($funding['payment_reference'], 'scor');
            }

            if($funding['invoice_id']) {
                $result[$id] .= '  ' . $funding['invoice_id']['name'];
            }

            if($funding['fund_request_execution_id']) {
                $result[$id] .= '  ' . $funding['fund_request_execution_id']['name'];
            }

        }

        return $result;
    }

    /**
     * Generate payment reference according to SCOR/VCS logic
     *
     */
    public static function calcPaymentReference($self) {
        $result = [];
        $self->read(['funding_type', 'invoice_id', 'money_transfer_id', 'condo_id' => ['code'], 'ownership_id' => 'code']);
        foreach($self as $id => $funding) {
            if(!$funding['funding_type']) {
                continue;
            }

            $reference = str_pad('', 12, '0');

            if($funding['funding_type'] === 'transfer') {
                $reference = sprintf("%010s", $funding['money_transfer_id']);
            }
            elseif(in_array($funding['funding_type'], ['fund_request', 'expense_statement'], true)) {
                $reference =
                    substr(str_pad((int) $funding['condo_id']['code'], 6, '0', STR_PAD_LEFT), 0, 6) .
                    substr(str_pad((int) $funding['ownership_id']['code'], 4, '0', STR_PAD_LEFT), 0, 4);
            }
            elseif($funding['funding_type'] === 'invoice') {
                // #todo - confirm strategy
                $reference = sprintf("%010s", $funding['invoice_id']);
            }

            $prefix = substr($reference, 0, 3);
            $suffix = substr($reference, 3);

            $result[$id] = self::computePaymentReference($prefix, $suffix);
        }
        return $result;
    }

}
