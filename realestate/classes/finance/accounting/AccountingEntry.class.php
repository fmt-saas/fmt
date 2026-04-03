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

            'entry_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'function'          => 'calcEntryReference',
                'description'       => 'Info to display as reference (links to accoutning doc).'
            ],

            'purchase_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Invoice the entry relates to, if any.',
                'ondelete'          => 'null',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'sale_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\sale\accounting\invoice\SaleInvoice',
                'description'       => 'Invoice the accounting entry is related to.',
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

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'cancel' => [
                'description'   => 'Delete the proforma and set receivables statuses back to pending.',
                'help'          => 'A fiscal year can be opened before the previous one is definitely closed.',
                'policies'      => ['can_cancel'],
                'function'      => 'doCancel'
            ]
        ]);
    }


    protected static function calcEntryReference($self) {
        $result = null;

        $self->read([
                'status',
                'entry_number',
                'misc_operation_id',
                'purchase_invoice_id'       => ['invoice_number'],
                'sale_invoice_id'           => ['invoice_number'],
                'fund_request_execution_id' => ['invoice_number'],
                'expense_statement_id'      => ['invoice_number'],
                'bank_statement_id'         => ['statement_number']
            ]);

        foreach($self as $id => $accountingEntry) {
            if($accountingEntry['status'] !== 'validated') {
                continue;
            }
            if(isset($accountingEntry['purchase_invoice_id'])) {
                $result[$id] = $accountingEntry['purchase_invoice_id']['invoice_number'];
            }
            elseif(isset($accountingEntry['sale_invoice_id'])) {
                $result[$id] = $accountingEntry['sale_invoice_id']['invoice_number'];
            }
            elseif(isset($accountingEntry['fund_request_execution_id'])) {
                $result[$id] = $accountingEntry['fund_request_execution_id']['invoice_number'];
            }
            elseif(isset($accountingEntry['expense_statement_id'])) {
                $result[$id] = $accountingEntry['expense_statement_id']['invoice_number'];
            }
            elseif(isset($accountingEntry['misc_operation_id'])) {
                // #todo - use operation_number once it will be available
                $result[$id] = preg_replace('/^[^\/]+\//', '', $accountingEntry['entry_number']);
            }
            elseif(isset($accountingEntry['bank_statement_id'])) {
                $result[$id] = $accountingEntry['bank_statement_id']['statement_number'];
            }
        }

        return $result;
    }


    /**
     * Policy ensures: status = 'validated' AND reversed_entry_id is null AND fiscal year is not closed, etc.
     */
    protected static function doCancel($self) {

        $self->read([
            'condo_id',
            'fiscal_year_id',
            'journal_id',
            'entry_date',
            'description',
            'origin_object_class',
            'origin_object_id',
            'purchase_invoice_id',
            'sale_invoice_id',
            'misc_operation_id',
            'bank_statement_id',
            'bank_statement_line_id',
            'fund_request_execution_id',
            'expense_statement_id',
            'entry_lines_ids' => [
                'account_id', 'debit', 'credit',
                'description',
                'sale_invoice_line_id',
                'purchase_invoice_line_id',
                'misc_operation_line_id',
                'bank_statement_line_id',
                'fund_usage_line_id',
                'ownership_id',
                'suppliership_id'
            ],
        ]);

        foreach($self as $id => $entry) {

            // 1) Create reversal entry (B)
            $reversal = self::create([
                    'condo_id'                  => $entry['condo_id'],
                    'journal_id'                => $entry['journal_id'],
                    'fiscal_year_id'            => $entry['fiscal_year_id'],
                    'description'               => 'reverse - ' . $entry['description'],
                    // #memo #important - same date for strict cancellation
                    'entry_date'                => $entry['entry_date'],
                    'origin_object_class'       => $entry['origin_object_class'],
                    'origin_object_id'          => $entry['origin_object_id'],
                    'purchase_invoice_id'       => $entry['purchase_invoice_id'],
                    'sale_invoice_id'           => $entry['sale_invoice_id'],
                    'misc_operation_id'         => $entry['misc_operation_id'],
                    'bank_statement_id'         => $entry['bank_statement_id'],
                    'bank_statement_line_id'    => $entry['bank_statement_line_id'],
                    'fund_request_execution_id' => $entry['fund_request_execution_id'],
                    'expense_statement_id'      => $entry['expense_statement_id']
                ])
                ->first();

            // 2) Create reversal lines (swap debit/credit)
            foreach($entry['entry_lines_ids'] ?? [] as $line) {
                AccountingEntryLine::create([
                    'condo_id'                  => $entry['condo_id'],
                    'accounting_entry_id'       => $reversal['id'],
                    'account_id'                => $line['account_id'],
                    'debit'                     => $line['credit'],
                    'credit'                    => $line['debit'],
                    'description'               => $line['description'],
                    'sale_invoice_line_id'      => $line['sale_invoice_line_id'],
                    'purchase_invoice_line_id'  => $line['purchase_invoice_line_id'],
                    'misc_operation_line_id'    => $line['misc_operation_line_id'],
                    'bank_statement_line_id'    => $line['bank_statement_line_id'],
                    'fund_usage_line_id'        => $line['fund_usage_line_id'],
                    'ownership_id'              => $line['ownership_id'],
                    'suppliership_id'           => $line['suppliership_id']
                ]);
            }

            // 3) Validate reversal (will post lines once => update AccountBalanceChange)
            self::id($reversal['id'])
                ->transition('validate');

            // 4) Link original to reversal
            self::id($id)
                ->update([
                    'reversed_entry_id'  => $reversal['id'],
                    'status'            => 'reversed'
                ]);

            // 5) Link reversal to original
            self::id($reversal['id'])
                ->update([
                    'reversed_entry_id'  => $id,
                    'status'            => 'reversed'
                ]);

            // 6) Mark all lines as reversed
            AccountingEntryLine::search(['accounting_entry_id', 'in', [$id, $reversal['id']]])
                ->update(['status' => 'reversed']);

        }
    }
}