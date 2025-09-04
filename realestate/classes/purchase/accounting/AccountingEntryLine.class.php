<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\purchase\accounting;


class AccountingEntryLine extends \finance\accounting\AccountingEntryLine {

    public static function getName() {
        return "Accounting entry line";
    }

    public static function getDescription() {
        return "Accounting entries lines map invoices lines into records of financial transactions in the accounting books.";
    }

    public static function getColumns() {
        return [

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\AccountingEntry',
                'description'       => "Accounting entry the line relates to.",
                'required'          => true,
                'readonly'          => true,
                'ondelete'          => 'cascade'
            ],

            'has_invoice_line' => [
                'type'              => 'boolean',
                'description'       => "Is the accounting entry line linked to an invoice line ?",
                'default'           => true
            ],

            'invoice_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoiceLine',
                'description'       => 'Invoice line the entry line relates to, if any.',
                'help'              => 'This is necessary for retrieving the invoice line corresponding to the entry line and, further, the apportionment and ratio to use for owner statement.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'bank_statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'description'       => 'Bank Statement line the entry line relates to, if any.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

        ];
    }

}