<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
                'foreign_object'    => 'realestate\purchase\accounting\invoice\InvoiceLine',
                'description'       => 'Detailed lines of the invoice.',
                'help'              => 'This is necessary for retrieving the invoice line corresponding to the entry line and, further, the apportionment and ratio to use for owner statement.',
                'readonly'          => true
            ]

        ];
    }

}