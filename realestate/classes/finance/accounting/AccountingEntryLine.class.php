<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\finance\accounting;


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
                'foreign_object'    => 'realestate\finance\accounting\AccountingEntry',
                'description'       => "Accounting entry the line relates to.",
                'required'          => true,
                'readonly'          => true,
                'ondelete'          => 'cascade'
            ],

            'purchase_invoice_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoiceLine',
                'description'       => 'Invoice line the entry line relates to, if any.',
                'help'              => 'This is necessary for retrieving the invoice line corresponding to the entry line and, further, the apportionment and ratio to use for owner statement.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            // #memo - in realestate package, 'purchase_invoice_line_id' targets `ExpenseStatementOwnerLine` and `FundRequestExecutionLine`

            'clearing_expense_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\ExpenseStatement',
                'description'       => "Expense Statement in which the record has been reinvoiced.",
                'help'              => "This field can be set several times and does not have a meaning while the `is_cleared` status is not set to true.",
                'ondelete'          => 'null',
                'domain'            => [ ['condo_id', '=', 'object.condo_id'] ],
                'visible'           => ['is_cleared', '=', true]
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false]],
                'dependents'        => ['account_code', 'account_class', 'ownership_id', 'suppliership_id']
            ],

            'ownership_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'relation'          => ['account_id' => 'ownership_id'],
                'foreign_object'    => 'realestate\ownership\Ownership',
                'ondelete'          => 'null',
                'description'       => "The ownership that the account refers to, if any.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'suppliership_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'relation'          => ['account_id' => 'suppliership_id'],
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'ondelete'          => 'null',
                'description'       => 'The supplier the account relates to, if any.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ]

        ];
    }

    public static function canupdate($self, $values) {
        $self->read(['accounting_entry_id' => ['status']]);
        $allowed_fields = ['status', 'matching_id', 'matching_level', 'clearing_expense_statement_id', 'is_cleared'];
        $updated_fields = array_keys($values);

        if(count(array_diff($updated_fields, $allowed_fields)) > 0) {
            foreach($self as $id => $accountingEntryLine) {
                if($accountingEntryLine['accounting_entry_id']['status'] == 'validated') {
                    return ['accounting_entry_id' => ['not_allowed' => 'Accounting entry cannot be modified once validated.']];
                }
            }
        }
        // do not call parent which raises an error on unknown fields of the class
        return [];
    }


}