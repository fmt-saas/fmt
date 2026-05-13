<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\sale\pay;

use equal\orm\Model;
use finance\accounting\Journal;
use finance\bank\BankStatement;
use finance\bank\BankStatementLine;

class FundingAllocation extends Model {

    public static function getDescription() {
        return 'A payment is an amount of money that was paid by a customer for a product or service.'
            .' It can origin form the cashdesk or a bank transfer. If it is from a bank transfer it is linked to a bank statement line.';
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the payment relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true,
                'dependents'        => ['journal_id']
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the payment originates from, if any.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'ondelete'          => 'cascade',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'readonly'          => true
            ],

            'journal_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'function'          => 'calcJournalId',
                'description'       => "The BANK accounting journal according to Condominium.",
                'readonly'          => true,
                'store'             => true
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount paid or received (whatever the origin).',
                'dependents'        => ['funding_id' => ['is_paid', 'paid_amount', 'remaining_amount']]
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Description of the operation.",
                'help'              => "This can be a message from the counterpart or a description for the accounting entry.",
            ],

            'receipt_date' => [
                'type'              => 'datetime',
                'description'       => "Time of reception of the payment.",
                'default'           => time()
            ],

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

            'misc_operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\MiscOperation',
                'description'       => 'Miscellaneous operation targeted by the funding, if any.',
                'help'              => 'This is for the unexpected movements, for which the Funding was created at bank statement line reconcile.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'visible'           => ['origin_object_class', '=', 'finance\accounting\MiscOperation']
            ],

            'purchase_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Invoice the entry relates to, if any.',
                'ondelete'          => 'null',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'visible'           => ['origin_object_class', '=', 'realestate\purchase\accounting\invoice\PurchaseInvoice']
            ],

            'sale_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\sale\accounting\invoice\SaleInvoice',
                'description'       => 'Invoice the accounting entry is related to.',
                'ondelete'          => 'null',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'visible'           => ['origin_object_class', '=', 'realestate\purchase\accounting\invoice\SaleInvoice']
            ],

            'fund_request_execution_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'description'       => 'Invoice the entry relates to, if any.',
                'ondelete'          => 'null',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'visible'           => ['origin_object_class', '=', 'realestate\funding\FundRequestExecution']
            ],

            'expense_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\ExpenseStatement',
                'description'       => "Expense Statement the entry relates to, if any.",
                'ondelete'          => 'null',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'visible'           => ['origin_object_class', '=', 'realestate\funding\ExpenseStatement']
            ],

            'bank_statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'description'       => 'The bank statement line the payment originates from.',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'visible'           => ['origin_object_class', '=', 'finance\bank\BankStatementLine']
            ],

            'accounting_entry_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntryLine',
                'description'       => 'Accounting entry line the allocation targets (related to Matching).',
                'help'              => "This is one of the accounting entry line generated by the accounting document the Affectation relates to.",
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id']
                ]
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'description'       => 'The funding the payment relates to, if any.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'order'             => 'issue_date',
                'sort'              => 'asc'
            ],

            // #memo - for compatibility with Payment
            'status' => [
                'type'              => 'string',
                'default'           => 'posted',
                'description'       => 'Status of the Allocation (not meant to be set manually).',
                'readonly'          => true
            ]
        ];
    }

    protected static function oncreate($self) {
        $self->read(['funding_id', 'bank_statement_line_id']);
        foreach($self as $id => $fundingAllocation) {
            if($fundingAllocation['funding_id']) {
                Funding::id($fundingAllocation['funding_id'])->update(['paid_amount' => null, 'remaining_amount' => null, 'is_paid' => null]);
            }
            if($fundingAllocation['bank_statement_line_id']) {
                BankStatementLine::id($fundingAllocation['bank_statement_line_id'])->update(['remaining_amount' => null]);
            }
        }
    }

    protected static function onafterupdate($self) {
        $self->read(['funding_id', 'bank_statement_line_id']);
        foreach($self as $id => $fundingAllocation) {
            if($fundingAllocation['funding_id']) {
                Funding::id($fundingAllocation['funding_id'])->update(['paid_amount' => null, 'remaining_amount' => null, 'is_paid' => null]);
            }
            if($fundingAllocation['bank_statement_line_id']) {
                BankStatement::id($fundingAllocation['bank_statement_line_id'])->update(['remaining_amount' => null]);
            }
        }
    }

    protected static function calcJournalId($self) {
        $result = [];
        $self->read(['condo_id']);
        foreach($self as $id => $payment) {
            $journal = Journal::search([['condo_id', '=', $payment['condo_id']], ['journal_type', '=', 'BANK']])->first();
            if($journal) {
                $result[$id] = $journal['id'];
            }
        }
        return $result;
    }

}
