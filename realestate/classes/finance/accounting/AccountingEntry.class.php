<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\finance\accounting;

class AccountingEntry extends \finance\accounting\AccountingEntry {

    public static function getName() {
        return "Journal accounting entry";
    }

    public static function getDescription() {
        return "Accounting entries correspond to invoice lines mapped as records of financial transactions in the accounting books.";
    }

    public static function getColumns() {
        return [
            'origin_object_class' => [
                'type'              => 'string',
                'description'       => 'Entity class that the entry originates from.',
                'help'              => "The accounting document the accounting entry originates from.
                    Possible classes are (stored with full namespace):
                    PurchaseInvoice, FundRequestExecution, ExpenseStatement, BankStatementLine, MiscOperation (virtual document).",
            ],

            'origin_object_id' => [
                'type'              => 'integer',
                'description'       => 'Object identifier, as a complement to `origin_object_class`, the entry originates from.'
            ],

            'purchase_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Invoice the entry relates to, if any.',
                'ondelete'          => 'null',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'fund_request_execution_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'description'       => 'Invoice the entry relates to, if any.',
                'ondelete'          => 'null',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'expense_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\ExpenseStatement',
                'description'       => "Expense Statement the entry relates to, if any.",
                'ondelete'          => 'null',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'entry_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\finance\accounting\AccountingEntryLine',
                'foreign_field'     => 'accounting_entry_id',
                'description'       => "Lines of the accounting entry.",
                'dependents'        => ['debit', 'credit'],
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ]

        ];
    }
}