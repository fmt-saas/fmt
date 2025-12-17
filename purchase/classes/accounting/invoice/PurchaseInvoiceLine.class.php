<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace purchase\accounting\invoice;

use finance\accounting\AccountingEntryLine;

class PurchaseInvoiceLine extends \finance\accounting\invoice\InvoiceLine {

    public function getTable() {
        return 'purchase_accounting_invoice_invoiceline';
    }

    public static function getColumns() {
        return [

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Invoice the line is related to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'expense_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null',
                'domain'            => [['is_control_account', '=', false]],
                'dependents'        => ['name']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['expense_account_id' => ['name']],
                'description'       => 'Default label of the line.',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Complementary description of the line (independent from product).',
                'multilang'         => true,
                'onupdate'          => 'onupdateDescription'
            ],

            'total' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the line.',
                'help'              => "For purchase invoice, total is arbitrary, so there is no strict constraint for user to encode the details about the computation (unit price and quantity).",
                'dependents'        => ['invoice_id' => ['total', 'price']],
                'default'           => 0.0
            ],

        ];
    }

    protected static function onupdateDescription($self) {
        $self->read(['description']);
        foreach($self as $id => $purchaseInvoiceLine) {
            AccountingEntryLine::search([['purchase_invoice_line_id', '=', $id]])
                ->update(['description' => $purchaseInvoiceLine['description']]);
        }
    }
}
